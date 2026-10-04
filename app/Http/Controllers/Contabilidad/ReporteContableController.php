<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoDetalle;
use App\Models\CentroCosto;
use App\Models\EjercicioContable;
use App\Models\PlanCuenta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReporteContableController extends Controller
{
    /**
     * Porcentajes legales del Ecuador usados en el Estado de Resultados.
     * Se dejan como constantes con nombre para que se vea de dónde salen y
     * puedan ajustarse en un solo lugar si cambia la norma.
     */
    private const PCT_PARTICIPACION_TRABAJADORES = 0.15; // Código del Trabajo, art. 97
    private const PCT_IMPUESTO_RENTA             = 0.25; // LRTI, sociedades

    public function index(): Response
    {
        $empresaId  = session('empresa_activa_id');
        $ejercicios = EjercicioContable::where('empresa_id', $empresaId)
            ->orderByDesc('anio')->orderByDesc('mes')
            ->get(['id','anio','mes','descripcion','estado']);

        // El plan de cuentas es compartido entre empresas (empresa_id en
        // plan_cuentas está siempre NULL en los datos reales — mismo
        // criterio que PlanCuentaController::index() y el resto de métodos
        // de este controller, ninguno filtra PlanCuenta por empresa_id).
        // Filtrar por empresa_id aquí dejaba `$cuentas` siempre vacío, que
        // es la causa real de "Sin resultados" en el buscador de Mayor
        // Contable — no un problema de mínimo de caracteres ni de LIKE.
        //
        // También se seleccionaba `descripcion` (columna secundaria,
        // vacía en el 100% de las 206 cuentas reales) en vez de `nombre`
        // (el campo que sí tiene el nombre real, ej. "Caja General").
        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id','codigo','nombre']);

        return Inertia::render('Contabilidad/Reportes/Index', [
            'ejercicios' => $ejercicios,
            'cuentas'    => $cuentas,
            'centros'    => CentroCosto::where('empresa_id', $empresaId)
                                ->where('estado', true)
                                ->orderBy('nombre')
                                ->get(['id','codigo','nombre']),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════
    // AGREGACIÓN — una sola consulta para todo el plan de cuentas
    //
    // Antes cada reporte recorría las ~200 cuentas del plan ejecutando una o
    // dos subconsultas SUM() POR CUENTA (400–500 queries por PDF). Ahora es
    // un único GROUP BY cuenta_id.
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Sumas de debe/haber por cuenta, filtradas por empresa y por los
     * criterios del request. Devuelve [cuenta_id => ['debe'=>x,'haber'=>y]].
     */
    private function sumasPorCuenta(
        int      $empresaId,
        ?string  $fechaDesde = null,
        ?string  $fechaHasta = null,
        ?int     $ejercicioId = null,
        ?int     $centroCostoId = null,
    ): array {
        $q = AsientoDetalle::query()
            ->join('asientos_contables as a', 'a.id', '=', 'asiento_detalles.asiento_id')
            ->where('a.empresa_id', $empresaId)
            ->where('a.estado', 1);

        if ($ejercicioId)   $q->where('a.ejercicio_id', $ejercicioId);
        if ($fechaDesde)    $q->where('a.fecha', '>=', $fechaDesde);
        if ($fechaHasta)    $q->where('a.fecha', '<=', $fechaHasta);
        if ($centroCostoId) $q->where('asiento_detalles.centro_costo_id', $centroCostoId);

        return $q->groupBy('asiento_detalles.cuenta_id')
            ->selectRaw('asiento_detalles.cuenta_id, COALESCE(SUM(asiento_detalles.debe),0) AS d, COALESCE(SUM(asiento_detalles.haber),0) AS h')
            ->get()
            ->mapWithKeys(fn($r) => [
                (int) $r->cuenta_id => ['debe' => (float) $r->d, 'haber' => (float) $r->h],
            ])
            ->all();
    }

    /** Cuentas que aceptan movimientos, opcionalmente filtradas por tipo. */
    private function cuentasPosteables(array $tipos = []): Collection
    {
        $q = PlanCuenta::where('permite_asientos', true)->where('estado', true);
        if ($tipos) $q->whereIn('tipo', $tipos);

        return $q->orderBy('codigo')->get(['id','codigo','nombre','tipo']);
    }

    /**
     * Rótulo del período que cubre el reporte. NINGÚN estado financiero lo
     * imprimía: solo decían "Generado: <hoy>", así que un PDF impreso no
     * permitía saber a qué corte corresponde ni qué filtros se aplicaron —
     * inservible para presentar a un tercero o al SRI.
     */
    private function rotuloPeriodo(Request $request, string $modo = 'rango'): string
    {
        $fmt = fn($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : null;

        if ($request->filled('ejercicio_id')) {
            $ej = EjercicioContable::find($request->ejercicio_id);
            if ($ej) return "Período: {$ej->periodo_label}";
        }

        $desde = $fmt($request->fecha_desde);
        $hasta = $fmt($request->fecha_hasta);

        if ($modo === 'corte') {
            return 'Al ' . ($hasta ?? now()->format('d/m/Y'));
        }

        if ($desde && $hasta) return "Del {$desde} al {$hasta}";
        if ($desde)           return "Desde {$desde}";
        if ($hasta)           return "Hasta {$hasta}";

        return 'Histórico completo (sin filtro de fechas)';
    }

    // ══════════════════════════════════════════════════════════════════════
    // LIBRO DIARIO
    // ══════════════════════════════════════════════════════════════════════

    private function queryLibroDiario(int $empresaId, Request $request)
    {
        $query = AsientoContable::with(['ejercicio','creadoPor','detalles.cuenta'])
            ->where('empresa_id', $empresaId)
            ->where('estado', 1);

        if ($request->filled('ejercicio_id')) {
            $query->where('ejercicio_id', $request->ejercicio_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        return $query;
    }

    public function libroDiario(Request $request): \Illuminate\Http\Response
    {
        ini_set('memory_limit', '2560M');

        $empresaId = session('empresa_activa_id');

        $asientos   = $this->queryLibroDiario($empresaId, $request)->orderBy('fecha')->orderBy('id')->get();
        $empresa    = \App\Models\Empresa::find($empresaId);
        $totalDebe  = $asientos->sum('total_debe');
        $totalHaber = $asientos->sum('total_haber');

        $pdf = Pdf::loadView('pdf.libro-diario', [
            'asientos'   => $asientos,
            'empresa'    => $empresa,
            'totalDebe'  => $totalDebe,
            'totalHaber' => $totalHaber,
            'periodo'    => $this->rotuloPeriodo($request),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream(
            'libro-diario-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // MAYOR CONTABLE
    // ══════════════════════════════════════════════════════════════════════

    public function mayor(Request $request): \Illuminate\Http\Response
    {
        // El Mayor de una cuenta muy activa sin filtro de fecha no tiene tope
        // natural de filas (a diferencia de Compras/Proveedores/CxP, acotados
        // por su propio módulo) — probado con la cuenta más activa real
        // (6,218 líneas de detalle) revienta 2560M; 4096M da margen medido
        // sobre ese caso real.
        ini_set('memory_limit', '4096M');

        $empresaId = session('empresa_activa_id');

        $request->validate([
            'cuenta_id' => 'required|exists:plan_cuentas,id',
        ]);

        $cuenta  = PlanCuenta::findOrFail($request->cuenta_id);
        $empresa = \App\Models\Empresa::find($empresaId);

        // SALDO ANTERIOR — faltaba por completo.
        //
        // Con un filtro de fechas (el front pone el mes en curso por defecto),
        // el mayor arrancaba en cero y el "Saldo final" que imprimía era en
        // realidad solo el movimiento del período, no el saldo de la cuenta.
        // Un mayor sin saldo de arranque no sirve para conciliar nada.
        $saldoAnterior = 0.0;
        if ($request->filled('fecha_desde')) {
            $prev = AsientoDetalle::query()
                ->join('asientos_contables as a', 'a.id', '=', 'asiento_detalles.asiento_id')
                ->where('a.empresa_id', $empresaId)
                ->where('a.estado', 1)
                ->where('asiento_detalles.cuenta_id', $cuenta->id)
                ->where('a.fecha', '<', $request->fecha_desde)
                ->selectRaw('COALESCE(SUM(asiento_detalles.debe),0) d, COALESCE(SUM(asiento_detalles.haber),0) h')
                ->first();
            $saldoAnterior = round((float) $prev->d - (float) $prev->h, 2);
        }

        // Se ordena por FECHA del asiento (y luego por id), no por el id del
        // detalle: con asientos retroactivos el mayor salía desordenado
        // cronológicamente, que es justo lo que un mayor no puede estar.
        $detalles = AsientoDetalle::query()
            ->with(['asiento'])
            ->join('asientos_contables as a', 'a.id', '=', 'asiento_detalles.asiento_id')
            ->where('a.empresa_id', $empresaId)
            ->where('a.estado', 1)
            ->where('asiento_detalles.cuenta_id', $cuenta->id)
            ->when($request->filled('fecha_desde'), fn($q) => $q->where('a.fecha', '>=', $request->fecha_desde))
            ->when($request->filled('fecha_hasta'), fn($q) => $q->where('a.fecha', '<=', $request->fecha_hasta))
            ->orderBy('a.fecha')->orderBy('a.id')->orderBy('asiento_detalles.id')
            ->select('asiento_detalles.*')
            ->get();

        // SALDO CORRIDO por línea — tampoco existía.
        $corrido = $saldoAnterior;
        $lineas  = $detalles->map(function ($d) use (&$corrido) {
            $corrido = round($corrido + (float) $d->debe - (float) $d->haber, 2);
            return [
                'fecha'       => $d->asiento?->fecha?->format('d/m/Y') ?? '—',
                'numero'      => $d->asiento?->numero ?? '—',
                'descripcion' => $d->descripcion ?? $d->asiento?->concepto ?? '—',
                'debe'        => (float) $d->debe,
                'haber'       => (float) $d->haber,
                'saldo'       => $corrido,
            ];
        });

        $totalDebe  = round((float) $detalles->sum('debe'), 2);
        $totalHaber = round((float) $detalles->sum('haber'), 2);
        $saldo      = round($saldoAnterior + $totalDebe - $totalHaber, 2);

        $pdf = Pdf::loadView('pdf.mayor-cuenta', [
            'cuenta'        => $cuenta,
            'lineas'        => $lineas,
            'saldoAnterior' => $saldoAnterior,
            'totalDebe'     => $totalDebe,
            'totalHaber'    => $totalHaber,
            'saldo'         => $saldo,
            'empresa'       => $empresa,
            'periodo'       => $this->rotuloPeriodo($request),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream(
            'mayor-' . $cuenta->codigo . '-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    // ══════════════════════════════════════════════════════════════════════
    // BALANCE DE COMPROBACIÓN
    // ══════════════════════════════════════════════════════════════════════

    public function balanceComprobacion(Request $request): \Illuminate\Http\Response
    {
        $empresaId = (int) session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $ejercicioId = $request->filled('ejercicio_id') ? (int) $request->ejercicio_id : null;

        // Saldos ANTERIORES al período, para presentar el balance en su forma
        // estándar de 8 columnas: Saldo anterior + Movimientos = Saldo actual.
        // Antes solo mostraba Sumas y Saldos del rango, sin punto de partida.
        $anteriores = [];
        if ($request->filled('fecha_desde') && !$ejercicioId) {
            $anteriores = $this->sumasPorCuenta(
                $empresaId, null,
                date('Y-m-d', strtotime($request->fecha_desde . ' -1 day'))
            );
        }

        $movimientos = $this->sumasPorCuenta(
            $empresaId,
            $request->fecha_desde,
            $request->fecha_hasta,
            $ejercicioId,
        );

        $cuentas = $this->cuentasPosteables()
            ->map(function ($cuenta) use ($anteriores, $movimientos) {
                $ant = $anteriores[$cuenta->id]  ?? ['debe' => 0, 'haber' => 0];
                $mov = $movimientos[$cuenta->id] ?? ['debe' => 0, 'haber' => 0];

                $saldoAnterior = round($ant['debe'] - $ant['haber'], 2);
                $sumaDebe      = round($mov['debe'], 2);
                $sumaHaber     = round($mov['haber'], 2);

                if ($saldoAnterior == 0.0 && $sumaDebe == 0.0 && $sumaHaber == 0.0) {
                    return null;
                }

                $saldo = round($saldoAnterior + $sumaDebe - $sumaHaber, 2);

                return [
                    'codigo'         => $cuenta->codigo,
                    'nombre'         => $cuenta->nombre,
                    'tipo'           => $cuenta->tipo,
                    'saldo_anterior' => $saldoAnterior,
                    'suma_debe'      => $sumaDebe,
                    'suma_haber'     => $sumaHaber,
                    'saldo_deudor'   => $saldo > 0 ? $saldo : 0,
                    'saldo_acreedor' => $saldo < 0 ? abs($saldo) : 0,
                ];
            })
            ->filter()
            ->values();

        $totales = [
            'debe'     => round($cuentas->sum('suma_debe'), 2),
            'haber'    => round($cuentas->sum('suma_haber'), 2),
            'deudor'   => round($cuentas->sum('saldo_deudor'), 2),
            'acreedor' => round($cuentas->sum('saldo_acreedor'), 2),
        ];

        // Un balance de comprobación que no cuadra es la señal de que algo
        // está mal en el libro; el reporte ahora lo dice en vez de callarlo.
        $totales['descuadre_sumas']  = round($totales['debe']   - $totales['haber'], 2);
        $totales['descuadre_saldos'] = round($totales['deudor'] - $totales['acreedor'], 2);

        $pdf = Pdf::loadView('pdf.balance-comprobacion', [
            'cuentas' => $cuentas,
            'empresa' => $empresa,
            'totales' => $totales,
            'periodo' => $this->rotuloPeriodo($request),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('balance-comprobacion-' . now()->format('Y-m-d') . '.pdf');
    }

    // ══════════════════════════════════════════════════════════════════════
    // ESTADO DE SITUACIÓN FINANCIERA (BALANCE GENERAL)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Resultado acumulado (ingresos − gastos) hasta una fecha de corte, sin
     * contar los asientos de cierre anual: es la "Utilidad del ejercicio en
     * curso" que debe figurar dentro del Patrimonio para que el balance
     * cuadre mientras el año no se ha cerrado.
     */
    private function resultadoAcumulado(int $empresaId, ?string $hasta, ?int $anioDesde = null): float
    {
        // El asiento de cierre anual SÍ entra en la suma: es justamente el que
        // encera las cuentas de resultado. Incluyéndolo, este método devuelve
        // la utilidad pendiente antes del cierre y exactamente 0 después, que
        // es lo que hace falta para que el balance no cuente el resultado dos
        // veces (una en esta línea y otra ya arrastrada a Ganancias
        // Acumuladas).
        $q = AsientoDetalle::query()
            ->join('asientos_contables as a', 'a.id', '=', 'asiento_detalles.asiento_id')
            ->join('plan_cuentas as p', 'p.id', '=', 'asiento_detalles.cuenta_id')
            ->where('a.empresa_id', $empresaId)
            ->where('a.estado', 1)
            ->whereIn('p.tipo', ['ingreso', 'gasto']);

        if ($hasta)     $q->where('a.fecha', '<=', $hasta);
        if ($anioDesde) $q->whereYear('a.fecha', '>=', $anioDesde);

        $row = $q->selectRaw("
            COALESCE(SUM(CASE WHEN p.tipo = 'ingreso' THEN asiento_detalles.haber - asiento_detalles.debe ELSE 0 END), 0) AS ingresos,
            COALESCE(SUM(CASE WHEN p.tipo = 'gasto'   THEN asiento_detalles.debe  - asiento_detalles.haber ELSE 0 END), 0) AS gastos
        ")->first();

        return round((float) $row->ingresos - (float) $row->gastos, 2);
    }

    public function balanceGeneral(Request $request): \Illuminate\Http\Response
    {
        $empresaId = (int) session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        // Un balance es una FOTO ACUMULADA a una fecha de corte, no los
        // movimientos de un mes. Antes el único filtro era `ejercicio_id`, que
        // en este sistema es un MES: pedir el balance de "Septiembre 2026"
        // devolvía solo lo movido en septiembre y lo rotulaba como estado de
        // situación financiera. Ahora se acumula todo hasta la fecha de corte.
        $corte = $request->filled('fecha_hasta')
            ? $request->fecha_hasta
            : ($request->filled('ejercicio_id')
                ? $this->finDeEjercicio((int) $request->ejercicio_id)
                : now()->toDateString());

        $sumas   = $this->sumasPorCuenta($empresaId, null, $corte);
        $cuentas = $this->cuentasPosteables(['activo', 'pasivo', 'patrimonio']);

        $activos = collect();
        $pasivos = collect();

        foreach ($cuentas as $c) {
            $s = $sumas[$c->id] ?? null;
            if (!$s) continue;

            $saldo = round($s['debe'] - $s['haber'], 2);
            if ($saldo == 0.0) continue;

            $fila = [
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
                'tipo'   => $c->tipo,
                // En activo se presenta el saldo deudor; en pasivo/patrimonio
                // el acreedor. Se conserva el signo para que una cuenta con
                // saldo contrario a su naturaleza se vea en negativo en lugar
                // de disfrazarse con abs() y descuadrar el total en silencio.
                'saldo'  => $c->tipo === 'activo' ? $saldo : -$saldo,
            ];

            $c->tipo === 'activo' ? $activos->push($fila) : $pasivos->push($fila);
        }

        // RESULTADO DEL EJERCICIO EN CURSO — la pieza que faltaba.
        //
        // El balance solo sumaba activo, pasivo y patrimonio. Como las cuentas
        // de resultado únicamente se enceran en el Cierre Fiscal Anual, el PDF
        // mostraba SIEMPRE "⚠ Diferencia: $X", siendo X exactamente la utilidad
        // todavía no cerrada. El balance nunca podía cuadrar.
        $anioCorte  = (int) date('Y', strtotime($corte));
        $resultado  = $this->resultadoAcumulado($empresaId, $corte, $anioCorte);

        if (abs($resultado) >= 0.01) {
            $pasivos->push([
                'codigo' => '—',
                'nombre' => $resultado >= 0
                    ? "Resultado del ejercicio {$anioCorte} (utilidad no cerrada)"
                    : "Resultado del ejercicio {$anioCorte} (pérdida no cerrada)",
                'tipo'   => 'patrimonio',
                'saldo'  => $resultado,
            ]);
        }

        $activos = $activos->sortBy('codigo')->values();
        $pasivos = $pasivos->sortBy('codigo')->values();

        $totales = [
            // Antes: sum(abs($saldo)) cuenta por cuenta. Un pasivo con saldo
            // deudor (p. ej. un proveedor sobrepagado) se sumaba en positivo
            // en lugar de restar, inflando el total y ocultando el error.
            'activos' => round($activos->sum('saldo'), 2),
            'pasivos' => round($pasivos->sum('saldo'), 2),
        ];

        $pdf = Pdf::loadView('pdf.balance-general', [
            'activos'   => $activos,
            'pasivos'   => $pasivos,
            'empresa'   => $empresa,
            'totales'   => $totales,
            'periodo'   => 'Al ' . \Carbon\Carbon::parse($corte)->format('d/m/Y'),
            'resultado' => $resultado,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('balance-general-' . now()->format('Y-m-d') . '.pdf');
    }

    /** Último día del mes que representa un ejercicio mensual. */
    private function finDeEjercicio(int $ejercicioId): string
    {
        $ej = EjercicioContable::find($ejercicioId);
        if (!$ej) return now()->toDateString();

        return \Carbon\Carbon::create($ej->anio, $ej->mes, 1)->endOfMonth()->toDateString();
    }

    // ══════════════════════════════════════════════════════════════════════
    // ESTADO DE RESULTADOS
    // ══════════════════════════════════════════════════════════════════════

    public function estadoResultados(Request $request): \Illuminate\Http\Response
    {
        $empresaId = (int) session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $sumas = $this->sumasPorCuenta(
            $empresaId,
            $request->fecha_desde,
            $request->fecha_hasta,
            $request->filled('ejercicio_id') ? (int) $request->ejercicio_id : null,
            $request->filled('centro_costo_id') ? (int) $request->centro_costo_id : null,
        );

        $ingresos  = collect(); // 4.01 / 4.02 — operacionales (netos de devoluciones)
        $otrosIng  = collect(); // el plan 2025 no tiene ingresos no operacionales
        $costos    = collect(); // clase 5 — costo de ventas y servicios
        $gastosOp  = collect(); // 6.01    — operativos
        $gastosFin = collect(); // 6.01.10 — financieros y comisiones bancarias
        $otrosGas  = collect(); // 6.02    — no deducibles

        foreach ($this->cuentasPosteables(['ingreso', 'gasto']) as $c) {
            $s = $sumas[$c->id] ?? null;
            if (!$s) continue;

            // Saldo en la naturaleza de la cuenta. Las regularizadoras de
            // ingreso (4.1.3 devoluciones y descuentos) tienen saldo deudor y
            // quedan en negativo, que es justo lo que se quiere: restan del
            // ingreso bruto.
            $saldo = $c->tipo === 'ingreso'
                ? round($s['haber'] - $s['debe'], 2)
                : round($s['debe']  - $s['haber'], 2);

            if ($saldo == 0.0) continue;

            $fila = ['codigo' => $c->codigo, 'nombre' => $c->nombre, 'saldo' => $saldo];

            match (true) {
                $c->tipo === 'ingreso'                 => $ingresos->push($fila),
                str_starts_with($c->codigo, '5.')      => $costos->push($fila),
                str_starts_with($c->codigo, '6.01.10') => $gastosFin->push($fila),
                str_starts_with($c->codigo, '6.02')    => $otrosGas->push($fila),
                default                                => $gastosOp->push($fila),
            };
        }

        // Estructura de varios pasos. Antes era un único "Ingresos − Gastos":
        // sin Costo de Ventas separado no existía Utilidad Bruta, y sin las
        // líneas del 15% de participación a trabajadores y del 25% de impuesto
        // a la renta el resultado no llegaba nunca a la utilidad neta, que es
        // lo que un estado de resultados ecuatoriano tiene que mostrar.
        $totalIngresos  = round($ingresos->sum('saldo'), 2);
        $totalCostos    = round($costos->sum('saldo'), 2);
        $utilidadBruta  = round($totalIngresos - $totalCostos, 2);

        $totalGastosOp  = round($gastosOp->sum('saldo'), 2);
        $utilidadOper   = round($utilidadBruta - $totalGastosOp, 2);

        $totalOtrosIng  = round($otrosIng->sum('saldo'), 2);
        $totalGastosFin = round($gastosFin->sum('saldo'), 2);
        $totalOtrosGas  = round($otrosGas->sum('saldo'), 2);

        $utilidadAntes  = round($utilidadOper + $totalOtrosIng - $totalGastosFin - $totalOtrosGas, 2);

        // La participación y el impuesto solo aplican sobre utilidad positiva.
        $participacion  = $utilidadAntes > 0 ? round($utilidadAntes * self::PCT_PARTICIPACION_TRABAJADORES, 2) : 0.0;
        $baseImponible  = round($utilidadAntes - $participacion, 2);
        $impuestoRenta  = $baseImponible > 0 ? round($baseImponible * self::PCT_IMPUESTO_RENTA, 2) : 0.0;
        $utilidadNeta   = round($baseImponible - $impuestoRenta, 2);

        $totales = [
            'ingresos'        => $totalIngresos,
            'costos'          => $totalCostos,
            'utilidad_bruta'  => $utilidadBruta,
            'gastos_op'       => $totalGastosOp,
            'utilidad_oper'   => $utilidadOper,
            'otros_ingresos'  => $totalOtrosIng,
            'gastos_fin'      => $totalGastosFin,
            'otros_gastos'    => $totalOtrosGas,
            'utilidad_antes'  => $utilidadAntes,
            'participacion'   => $participacion,
            'base_imponible'  => $baseImponible,
            'impuesto_renta'  => $impuestoRenta,
            'utilidad_neta'   => $utilidadNeta,
            'pct_participacion' => self::PCT_PARTICIPACION_TRABAJADORES * 100,
            'pct_impuesto'      => self::PCT_IMPUESTO_RENTA * 100,
            // Compatibilidad con la plantilla anterior
            'gastos'          => round($totalGastosOp + $totalGastosFin + $totalOtrosGas + $totalCostos, 2),
            'utilidad'        => $utilidadNeta,
        ];

        $pdf = Pdf::loadView('pdf.estado-resultados', [
            'ingresos'  => $ingresos,
            'otrosIng'  => $otrosIng,
            'costos'    => $costos,
            'gastosOp'  => $gastosOp,
            'gastosFin' => $gastosFin,
            'otrosGas'  => $otrosGas,
            'empresa'   => $empresa,
            'totales'   => $totales,
            'utilidad'  => $utilidadNeta,
            'periodo'   => $this->rotuloPeriodo($request),
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('estado-resultados-' . now()->format('Y-m-d') . '.pdf');
    }

    // ══════════════════════════════════════════════════════════════════════
    // FLUJO DE CAJA — método indirecto
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Cuentas que representan efectivo y equivalentes.
     *
     * Plan 2025: 1.01.01 (cajas) y 1.01.02 (bancos). Los vouchers Datafast ya no
     * tienen cuenta propia: van a Clientes Locales (1.01.03.01), que no es efectivo.
     */
    private function esEfectivo(string $codigo): bool
    {
        $c = $this->normalizar($codigo);

        // normalizar(): '1.01.01.x' → '1.1.1.x' (cajas) y '1.01.02.x' → '1.1.2.x' (bancos).
        return str_starts_with($c, '1.1.1.') || str_starts_with($c, '1.1.2.');
    }

    /**
     * Quita los ceros a la izquierda de cada segmento para poder comparar
     * prefijos sin depender del formato: '1.1.4.01' y '1.1.4.1' son lo mismo.
     */
    private function normalizar(string $codigo): string
    {
        return implode('.', array_map(
            fn($s) => ltrim($s, '0') === '' ? '0' : ltrim($s, '0'),
            explode('.', trim($codigo))
        ));
    }

    /** Clasifica una cuenta de balance en la sección del flujo que le toca. */
    private function seccionFlujo(string $codigo, string $tipo): ?string
    {
        $c = $this->normalizar($codigo);

        return match (true) {
            str_starts_with($c, '1.1.') => 'operativo_activo',
            str_starts_with($c, '1.2'),
            str_starts_with($c, '1.3')  => 'inversion',
            str_starts_with($c, '2.1.') => 'operativo_pasivo',
            str_starts_with($c, '2.2'),
            // El patrimonio (clase 3) es financiamiento: aportes de socios,
            // dividendos, capitalizaciones. Antes se excluía por completo, así
            // que el flujo neto calculado JAMÁS podía reconciliar con la
            // variación real del efectivo.
            str_starts_with($c, '3')    => 'financiamiento',
            default                     => null,
        };
    }

    /**
     * Arma el flujo de caja. Devuelve las secciones y el resumen, para que el
     * PDF y el Excel usen exactamente el mismo cálculo (antes estaban
     * duplicados y podían divergir).
     */
    private function armarFlujoCaja(int $empresaId, Request $request): array
    {
        $fechaDesde = $request->fecha_desde ?? now()->startOfYear()->toDateString();
        $fechaHasta = $request->fecha_hasta ?? now()->toDateString();
        $fechaAntes = date('Y-m-d', strtotime($fechaDesde . ' -1 day'));
        $centro     = $request->filled('centro_costo_id') ? (int) $request->centro_costo_id : null;

        // Dos agregaciones para todo el plan, en lugar de 2 consultas por cuenta.
        $hastaInicio = $this->sumasPorCuenta($empresaId, null, $fechaAntes, null, $centro);
        $hastaFin    = $this->sumasPorCuenta($empresaId, null, $fechaHasta, null, $centro);
        $delPeriodo  = $this->sumasPorCuenta($empresaId, $fechaDesde, $fechaHasta, null, $centro);

        // ── Utilidad del período ──
        $totalIngresos = 0.0;
        $totalGastos   = 0.0;
        foreach ($this->cuentasPosteables(['ingreso', 'gasto']) as $c) {
            $s = $delPeriodo[$c->id] ?? null;
            if (!$s) continue;
            if ($c->tipo === 'ingreso') $totalIngresos += $s['haber'] - $s['debe'];
            else                        $totalGastos   += $s['debe']  - $s['haber'];
        }
        $utilidad = round($totalIngresos - $totalGastos, 2);

        $operativoActivo = collect();
        $operativoPasivo = collect();
        $inversion       = collect();
        $financiamiento  = collect();
        $efectivoInicio  = 0.0;
        $efectivoFin     = 0.0;

        foreach ($this->cuentasPosteables(['activo', 'pasivo', 'patrimonio']) as $c) {
            $si = round(($hastaInicio[$c->id]['debe'] ?? 0) - ($hastaInicio[$c->id]['haber'] ?? 0), 2);
            $sf = round(($hastaFin[$c->id]['debe']    ?? 0) - ($hastaFin[$c->id]['haber']    ?? 0), 2);

            if ($si == 0.0 && $sf == 0.0) continue;

            if ($c->tipo === 'activo' && $this->esEfectivo($c->codigo)) {
                $efectivoInicio += $si;
                $efectivoFin    += $sf;
                continue;
            }

            $seccion = $this->seccionFlujo($c->codigo, $c->tipo);
            if (!$seccion) continue;

            if ($c->tipo === 'activo') {
                // Más activo = menos caja.
                $saldoI = $si; $saldoF = $sf;
                $efecto = -($sf - $si);
            } else {
                // Pasivo/patrimonio se presentan en su naturaleza acreedora;
                // más pasivo = más caja.
                $saldoI = -$si; $saldoF = -$sf;
                $efecto = $saldoF - $saldoI;
            }

            $item = [
                'codigo'       => $c->codigo,
                'nombre'       => $c->nombre,
                'saldo_inicio' => round($saldoI, 2),
                'saldo_fin'    => round($saldoF, 2),
                'variacion'    => round($saldoF - $saldoI, 2),
                'efecto_caja'  => round($efecto, 2),
            ];

            match ($seccion) {
                'operativo_activo' => $operativoActivo->push($item),
                'operativo_pasivo' => $operativoPasivo->push($item),
                'inversion'        => $inversion->push($item),
                'financiamiento'   => $financiamiento->push($item),
            };
        }

        $ajustes             = round($operativoActivo->sum('efecto_caja') + $operativoPasivo->sum('efecto_caja'), 2);
        $flujoOperativo      = round($utilidad + $ajustes, 2);
        $flujoInversion      = round($inversion->sum('efecto_caja'), 2);
        $flujoFinanciamiento = round($financiamiento->sum('efecto_caja'), 2);
        $flujoNeto           = round($flujoOperativo + $flujoInversion + $flujoFinanciamiento, 2);

        $variacionReal = round($efectivoFin - $efectivoInicio, 2);

        $resumen = [
            'utilidad'             => $utilidad,
            'total_ingresos'       => round($totalIngresos, 2),
            'total_gastos'         => round($totalGastos, 2),
            'ajustes_cap_trabajo'  => $ajustes,
            'flujo_operativo'      => $flujoOperativo,
            'flujo_inversion'      => $flujoInversion,
            'flujo_financiamiento' => $flujoFinanciamiento,
            'flujo_neto'           => $flujoNeto,
            'efectivo_inicio'      => round($efectivoInicio, 2),
            'efectivo_fin'         => round($efectivoFin, 2),
            'diferencia_efectivo'  => $variacionReal,
            // El reporte mostraba el flujo neto y la variación del efectivo sin
            // compararlos nunca. Si no coinciden, el flujo no está explicado y
            // hay que decirlo.
            'descuadre'            => round($flujoNeto - $variacionReal, 2),
        ];

        return [
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'resumen'         => $resumen,
            'operativoActivo' => $operativoActivo,
            'operativoPasivo' => $operativoPasivo,
            'inversion'       => $inversion,
            'financiamiento'  => $financiamiento,
        ];
    }

    public function flujoCaja(Request $request): \Illuminate\Http\Response
    {
        $empresaId = (int) session('empresa_activa_id');
        $datos     = $this->armarFlujoCaja($empresaId, $request);

        $pdf = Pdf::loadView('pdf.flujo-caja', array_merge($datos, [
            'empresa' => \App\Models\Empresa::find($empresaId),
            'periodo' => 'Del ' . \Carbon\Carbon::parse($datos['fecha_desde'])->format('d/m/Y')
                       . ' al ' . \Carbon\Carbon::parse($datos['fecha_hasta'])->format('d/m/Y'),
        ]))->setPaper('a4', 'portrait');

        return $pdf->stream('flujo-caja-' . now()->format('Y-m-d') . '.pdf');
    }

    public function flujoCajaExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        $datos     = $this->armarFlujoCaja($empresaId, $request);
        $r         = $datos['resumen'];

        $filas = collect()
            ->concat($datos['operativoActivo']->map(fn($i) => array_merge(['Operativo — Activo'], array_values($i))))
            ->concat($datos['operativoPasivo']->map(fn($i) => array_merge(['Operativo — Pasivo'], array_values($i))))
            ->concat($datos['inversion']->map(fn($i)       => array_merge(['Inversión'], array_values($i))))
            ->concat($datos['financiamiento']->map(fn($i)  => array_merge(['Financiamiento'], array_values($i))));

        return response()->streamDownload(function () use ($filas, $r, $datos) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ESTADO DE FLUJO DE EFECTIVO — MÉTODO INDIRECTO']);
            fputcsv($out, ['Del', $datos['fecha_desde'], 'al', $datos['fecha_hasta']]);
            fputcsv($out, []);
            fputcsv($out, ['Utilidad neta del período', $r['utilidad']]);
            fputcsv($out, []);
            fputcsv($out, ['Sección', 'Código', 'Cuenta', 'Saldo Inicio', 'Saldo Fin', 'Variación', 'Efecto en Caja']);
            foreach ($filas as $fila) fputcsv($out, $fila);
            fputcsv($out, []);
            fputcsv($out, ['Flujo operativo',            $r['flujo_operativo']]);
            fputcsv($out, ['Flujo de inversión',         $r['flujo_inversion']]);
            fputcsv($out, ['Flujo de financiamiento',    $r['flujo_financiamiento']]);
            fputcsv($out, ['FLUJO NETO DEL PERÍODO',     $r['flujo_neto']]);
            fputcsv($out, []);
            fputcsv($out, ['Saldo inicial efectivo/bancos', $r['efectivo_inicio']]);
            fputcsv($out, ['Saldo final efectivo/bancos',   $r['efectivo_fin']]);
            fputcsv($out, ['Variación real del efectivo',   $r['diferencia_efectivo']]);
            fputcsv($out, ['Descuadre (flujo neto − variación real)', $r['descuadre']]);
            fclose($out);
        }, 'flujo-caja-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
