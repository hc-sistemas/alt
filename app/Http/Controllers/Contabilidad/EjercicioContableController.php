<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoDetalle;
use App\Models\EjercicioContable;
use App\Models\PlanCuenta;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class EjercicioContableController extends Controller
{
    public function __construct(private AsientoService $asientos) {}

    public function index(): Response
    {
        $empresaId  = session('empresa_activa_id');
        $ejercicios = EjercicioContable::where('empresa_id', $empresaId)
            ->with('cerradoPor')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get()
            ->map(fn($e) => [
                'id'             => $e->id,
                'anio'           => $e->anio,
                'mes'            => $e->mes,
                'nombre_mes'     => $e->nombre_mes,
                'periodo_label'  => $e->periodo_label,
                'descripcion'    => $e->descripcion,
                'fecha_apertura' => $e->fecha_apertura?->format('d/m/Y'),
                'fecha_cierre'   => $e->fecha_cierre?->format('d/m/Y'),
                'estado'         => $e->estado,
                'cerrado_por'    => $e->cerradoPor?->nombre,
                'total_asientos' => $e->asientos()->count(),
            ]);

        return Inertia::render('Contabilidad/Ejercicios/Index', [
            'ejercicios'    => $ejercicios,
            'periodoActivo' => $empresaId
                ? EjercicioContable::periodoActivo((int)$empresaId)
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'anio'        => 'required|integer|min:2000|max:2100',
            'mes'         => 'required|integer|min:1|max:12',
            'descripcion' => 'nullable|string|max:100',
        ]);

        // Antes solo se permitía UN período abierto a la vez y reabrir era
        // imposible. La combinación era una trampa operativa: para poder
        // facturar en octubre había que cerrar septiembre, y a partir de ese
        // momento una factura de proveedor que llegara tarde con fecha de
        // septiembre ya no se podía registrar NUNCA — ni siquiera un ajuste.
        // Peor todavía, AsientoService le decía al usuario "reábralo en
        // Contabilidad → Ejercicios", una instrucción que el sistema no
        // permitía ejecutar.
        //
        // Ahora se pueden tener varios meses abiertos (lo normal mientras no
        // se cierra el ejercicio fiscal) y lo único que queda blindado es el
        // año ya cerrado fiscalmente.
        if ($this->anioCerradoFiscalmente((int) $empresaId, (int) $request->anio)) {
            return back()->with('error',
                "El ejercicio fiscal {$request->anio} ya fue cerrado. No se pueden abrir períodos de ese año.");
        }

        $existe = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $request->anio)
            ->where('mes',  $request->mes)
            ->exists();

        if ($existe) {
            return back()->with('error',
                "Ya existe un período para {$request->mes}/{$request->anio}.");
        }

        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',
                  5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',
                  9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];

        EjercicioContable::create([
            'empresa_id'    => $empresaId,
            'anio'          => $request->anio,
            'mes'           => $request->mes,
            'descripcion'   => $request->descripcion ??
                               "{$meses[$request->mes]} {$request->anio}",
            'fecha_apertura'=> now()->toDateString(),
            'estado'        => 'abierto',
            'created_at'    => now(),
        ]);

        return back()->with('success',
            "Período {$meses[$request->mes]} {$request->anio} abierto correctamente.");
    }

    public function cerrar(Request $request, EjercicioContable $ejercicio): RedirectResponse
    {
        // El route-model binding no filtraba por empresa: con Matriz activa se
        // podía cerrar un período de Altamira Import pasando el ID en la URL.
        if ((int) $ejercicio->empresa_id !== (int) session('empresa_activa_id')) {
            abort(404);
        }

        $request->validate([
            'motivo'       => 'required|string|min:10|max:300',
            'fecha_cierre' => 'required|date|before_or_equal:today',
        ], [
            'fecha_cierre.required'        => 'La fecha de cierre es obligatoria.',
            'fecha_cierre.before_or_equal' => 'La fecha no puede ser futura.',
        ]);

        if ($ejercicio->estaCerrado()) {
            return back()->with('error', 'Este período ya está cerrado.');
        }

        $sinCuadrar = $ejercicio->asientos()
            ->whereRaw('ABS(total_debe - total_haber) > 0.0001')
            ->where('estado', 1)
            ->count();

        if ($sinCuadrar > 0) {
            return back()->with('error',
                "No se puede cerrar: hay {$sinCuadrar} asiento(s) sin cuadrar en este período.");
        }

        $ejercicio->update([
            'estado'       => 'cerrado',
            'fecha_cierre' => $request->fecha_cierre,
            'cerrado_por'  => Auth::id(),
        ]);

        // CORRECCIÓN 1: columnas reales de log_cambios_criticos
        DB::table('log_cambios_criticos')->insert([
            'usuario_id'     => Auth::id(),
            'empresa_id'     => $ejercicio->empresa_id,
            'tabla'          => 'ejercicios_contables',
            'registro_id'    => $ejercicio->id,
            'campo'          => 'estado',
            'valor_anterior' => 'abierto',
            'valor_nuevo'    => "cerrado — {$request->motivo}",
            'ip_address'     => $request->ip(),
        ]);

        return back()->with('success',
            "Período {$ejercicio->periodo_label} cerrado. " .
            "No se pueden crear ni modificar asientos en este período.");
    }

    /**
     * ¿El ejercicio fiscal de ese año ya se cerró? Es el único candado
     * permanente: una vez ejecutado el Cierre Fiscal Anual, los resultados ya
     * se enceraron y se arrastraron al patrimonio, así que tocar ese año
     * invalidaría los estados financieros ya emitidos.
     */
    private function anioCerradoFiscalmente(int $empresaId, int $anio): bool
    {
        return AsientoContable::where('empresa_id', $empresaId)
            ->where('documento_tipo', 'CIERRE_ANUAL')
            ->where('documento_ref', (string) $anio)
            ->where('estado', 1)
            ->exists();
    }

    /**
     * Reabre un período mensual cerrado.
     *
     * Antes este método SIEMPRE devolvía error ("restricción contable
     * permanente"), lo cual dejaba el sistema en un callejón sin salida: como
     * solo se permitía un período abierto, cerrar un mes para poder trabajar
     * en el siguiente hacía imposible registrar para siempre cualquier ajuste
     * o documento atrasado de ese mes. Ningún ERP contable funciona así;
     * lo correcto es permitir la reapertura mientras el ejercicio FISCAL no
     * esté cerrado, restringida y auditada.
     */
    public function reabrir(Request $request, EjercicioContable $ejercicio): RedirectResponse
    {
        if ((int) $ejercicio->empresa_id !== (int) session('empresa_activa_id')) {
            abort(404);
        }

        $perfil = Auth::user()->perfil->nombre ?? '';
        if (!in_array($perfil, ['super_admin', 'contador'], true)) {
            return back()->with('error',
                'Solo el Super Administrador o el Contador pueden reabrir un período contable.');
        }

        $request->validate([
            'motivo' => 'required|string|min:10|max:300',
        ], [
            'motivo.required' => 'Indique el motivo de la reapertura.',
            'motivo.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        if (!$ejercicio->estaCerrado()) {
            return back()->with('error', 'El período ya está abierto.');
        }

        if ($this->anioCerradoFiscalmente((int) $ejercicio->empresa_id, (int) $ejercicio->anio)) {
            return back()->with('error',
                "No se puede reabrir {$ejercicio->periodo_label}: el ejercicio fiscal {$ejercicio->anio} " .
                'ya fue cerrado. Registre los ajustes en el ejercicio vigente.');
        }

        $ejercicio->update([
            'estado'       => 'abierto',
            'fecha_cierre' => null,
            'cerrado_por'  => null,
        ]);

        DB::table('log_cambios_criticos')->insert([
            'usuario_id'     => Auth::id(),
            'empresa_id'     => $ejercicio->empresa_id,
            'tabla'          => 'ejercicios_contables',
            'registro_id'    => $ejercicio->id,
            'campo'          => 'estado',
            'valor_anterior' => 'cerrado',
            'valor_nuevo'    => "abierto — {$request->motivo}",
            'ip_address'     => $request->ip(),
        ]);

        return back()->with('success',
            "Período {$ejercicio->periodo_label} reabierto. Recuerde volver a cerrarlo al terminar los ajustes.");
    }

    public function cierreFiscalAnual(Request $request): RedirectResponse
    {
        $perfil = Auth::user()->perfil->nombre ?? '';
        if ($perfil !== 'super_admin') {
            return back()->with('error',
                'Solo el Super Administrador puede ejecutar el Cierre Fiscal Anual.');
        }

        $request->validate([
            'anio'   => 'required|integer|min:2000|max:2100',
            'motivo' => 'required|string|min:10|max:300',
        ]);

        $empresaId = (int) session('empresa_activa_id');
        $anio      = (int) $request->input('anio');
        $motivo    = $request->input('motivo');

        $mesesExistentes = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $anio)->count();

        if ($mesesExistentes === 0) {
            return back()->with('error', "No existen períodos mensuales para el año {$anio}.");
        }

        $mesesCerrados = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $anio)->where('estado', 'cerrado')->count();

        if ($mesesCerrados < $mesesExistentes) {
            return back()->with('error',
                "No se puede cerrar: hay " . ($mesesExistentes - $mesesCerrados) .
                " período(s) sin cerrar en {$anio}.");
        }

        // Idempotencia: antes se comprobaba con un LIKE sobre el texto libre de
        // log_cambios_criticos.valor_nuevo. Si alguien limpiaba esa tabla de
        // auditoría, el cierre se podía ejecutar dos veces y duplicaba el
        // enceramiento y el arrastre al patrimonio. Ahora se pregunta por el
        // hecho contable en sí: ¿existe ya el asiento de cierre de ese año?
        if ($this->anioCerradoFiscalmente($empresaId, $anio)) {
            return back()->with('error', "El ejercicio fiscal {$anio} ya fue cerrado anteriormente.");
        }

        try {
            DB::transaction(function () use ($empresaId, $anio, $motivo) {

                // Ejercicio de diciembre (para vincular los asientos de cierre)
                $ejercicioDic = EjercicioContable::where('empresa_id', $empresaId)
                    ->where('anio', $anio)
                    ->orderByDesc('mes')
                    ->first();

                // IDs de asientos activos del año.
                //
                // Se seleccionan por la FECHA del asiento, no por el año de su
                // ejercicio: es la fecha la que define a qué ejercicio fiscal
                // pertenece el hecho económico, y así el cierre sigue siendo
                // correcto aunque existan asientos antiguos mal clasificados
                // (el bug de ejercicio_id que se corrigió en AsientoService).
                //
                // Se excluyen los propios asientos de cierre para que un
                // reproceso no encere sobre lo ya encerado.
                $asientoIds = AsientoContable::where('empresa_id', $empresaId)
                    ->where('estado', 1)
                    ->whereYear('fecha', $anio)
                    ->where(fn($q) => $q->whereNull('documento_tipo')
                                        ->orWhere('documento_tipo', '!=', 'CIERRE_ANUAL'))
                    ->pluck('id');

                // ── PASO 1: calcular saldo neto por cuenta de ingreso y gasto ──
                $totalIngresos  = 0.0;
                $totalGastos    = 0.0;
                $detallesCierre = [];

                $cuentasIngreso = PlanCuenta::where('tipo', 'ingreso')
                    ->where('permite_asientos', true)
                    ->where('estado', true)
                    ->get();

                foreach ($cuentasIngreso as $cuenta) {
                    $row = AsientoDetalle::whereIn('asiento_id', $asientoIds)
                        ->where('cuenta_id', $cuenta->id)
                        ->selectRaw('COALESCE(SUM(debe),0) as d, COALESCE(SUM(haber),0) as h')
                        ->first();

                    $neto = round((float)$row->h - (float)$row->d, 4);
                    if (abs($neto) < 0.0001) continue;

                    $totalIngresos += $neto;
                    // encerar ingreso (naturaleza acreedora): DEBE para reducir su saldo
                    $detallesCierre[] = [
                        'cuenta_id'   => $cuenta->id,
                        'descripcion' => "Cierre {$cuenta->codigo} — {$cuenta->nombre}",
                        'debe'        => $neto > 0 ? $neto : 0,
                        'haber'       => $neto < 0 ? abs($neto) : 0,
                    ];
                }

                $cuentasGasto = PlanCuenta::where('tipo', 'gasto')
                    ->where('permite_asientos', true)
                    ->where('estado', true)
                    ->get();

                foreach ($cuentasGasto as $cuenta) {
                    $row = AsientoDetalle::whereIn('asiento_id', $asientoIds)
                        ->where('cuenta_id', $cuenta->id)
                        ->selectRaw('COALESCE(SUM(debe),0) as d, COALESCE(SUM(haber),0) as h')
                        ->first();

                    $neto = round((float)$row->d - (float)$row->h, 4);
                    if (abs($neto) < 0.0001) continue;

                    $totalGastos += $neto;
                    // encerar gasto (naturaleza deudora): HABER para reducir su saldo
                    $detallesCierre[] = [
                        'cuenta_id'   => $cuenta->id,
                        'descripcion' => "Cierre {$cuenta->codigo} — {$cuenta->nombre}",
                        'debe'        => $neto < 0 ? abs($neto) : 0,
                        'haber'       => $neto > 0 ? $neto : 0,
                    ];
                }

                $utilidad = round($totalIngresos - $totalGastos, 4);

                // ── PASO 2: cuenta de resultado del ejercicio ──
                //
                // Se resuelve con AsientoService::cuentaId(), que respeta lo
                // configurado en Parámetros Contables y cae al plan real si no
                // está configurado. Antes se leía el parámetro a mano y su
                // fallback era '3.1.4.01', que en el plan de cuentas REAL del
                // cliente es "Ganancias Acumuladas", no "Utilidad del Periodo"
                // (esa es 3.1.5.1): el cierre metía el resultado del ejercicio
                // directamente en acumuladas y después el PASO 4 volvía a
                // arrastrarlo, duplicándolo.
                $cuentaResultadoId = null;
                try {
                    $cuentaResultadoId = $this->asientos->cuentaId('cta_utilidad_periodo', $empresaId);
                } catch (\Throwable) {
                    $cuentaResultadoId = null;
                }

                if (!empty($detallesCierre) && !$cuentaResultadoId) {
                    throw new \Exception(
                        'Configure la cuenta "Utilidad del Periodo" en Parámetros Contables antes del cierre fiscal.'
                    );
                }

                // Agregar línea de resultado para cuadrar el asiento
                if (!empty($detallesCierre) && $cuentaResultadoId && abs($utilidad) > 0.0001) {
                    $detallesCierre[] = [
                        'cuenta_id'   => $cuentaResultadoId,
                        'descripcion' => $utilidad >= 0
                            ? "Utilidad del ejercicio {$anio}"
                            : "Pérdida del ejercicio {$anio}",
                        'debe'        => $utilidad < 0 ? abs($utilidad) : 0,
                        'haber'       => $utilidad >= 0 ? $utilidad : 0,
                    ];
                }

                // ── PASO 3: asiento de cierre (enceramiento clases 4 y 5) ──
                if (count($detallesCierre) >= 2) {
                    $totalDebe  = collect($detallesCierre)->sum('debe');
                    $totalHaber = collect($detallesCierre)->sum('haber');

                    $asientoCierre = AsientoContable::create([
                        'empresa_id'     => $empresaId,
                        'ejercicio_id'   => $ejercicioDic?->id,
                        'numero'         => "CIERRE-{$anio}",
                        'fecha'          => "{$anio}-12-31",
                        'concepto'       => "Cierre Fiscal Anual {$anio} — Enceramiento clases Ingreso y Gasto",
                        'documento_tipo' => 'CIERRE_ANUAL',
                        'documento_ref'  => (string)$anio,
                        'total_debe'     => $totalDebe,
                        'total_haber'    => $totalHaber,
                        'es_automatico'  => true,
                        'estado'         => 1,
                        'creado_por'     => Auth::id(),
                        'created_at'     => now(),
                    ]);

                    $cuentaIdsCierre = [];
                    foreach ($detallesCierre as $det) {
                        AsientoDetalle::create([
                            'asiento_id'  => $asientoCierre->id,
                            'cuenta_id'   => $det['cuenta_id'],
                            'descripcion' => $det['descripcion'],
                            'debe'        => $det['debe'],
                            'haber'       => $det['haber'],
                        ]);
                        $cuentaIdsCierre[] = $det['cuenta_id'];
                    }
                    PlanCuenta::whereIn('id', array_unique($cuentaIdsCierre))
                        ->increment('total_asientos');

                    // ── PASO 4: asiento de arrastre 3.1.5.01 → 3.1.4.01 ──
                    if ($cuentaResultadoId && abs($utilidad) > 0.0001) {
                        // La utilidad va a Ganancias Acumuladas y la pérdida a
                        // Pérdidas Acumuladas. El fallback anterior era
                        // '3.1.3.01' que en el plan real es "Superavit por
                        // Revaluacion PPE": el resultado del ejercicio se
                        // arrastraba al superávit por revaluación.
                        $codigoAcumulada = $utilidad >= 0
                            ? 'cta_ganancias_acumuladas'
                            : 'cta_perdidas_acumuladas';

                        $cuentaAcumuladaId = null;
                        try {
                            $cuentaAcumuladaId = $this->asientos->cuentaId($codigoAcumulada, $empresaId);
                        } catch (\Throwable) {
                            $cuentaAcumuladaId = null;
                        }

                        if ($cuentaAcumuladaId && $cuentaAcumuladaId !== $cuentaResultadoId) {
                            $detallesArrastre = $utilidad >= 0
                                ? [
                                    ['cuenta_id' => $cuentaResultadoId, 'descripcion' => "Arrastre utilidad {$anio}", 'debe' => $utilidad, 'haber' => 0],
                                    ['cuenta_id' => $cuentaAcumuladaId, 'descripcion' => "Ganancias acumuladas {$anio}", 'debe' => 0, 'haber' => $utilidad],
                                ]
                                : [
                                    ['cuenta_id' => $cuentaAcumuladaId, 'descripcion' => "Pérdidas acumuladas {$anio}", 'debe' => abs($utilidad), 'haber' => 0],
                                    ['cuenta_id' => $cuentaResultadoId, 'descripcion' => "Arrastre pérdida {$anio}", 'debe' => 0, 'haber' => abs($utilidad)],
                                ];

                            $asientoArrastre = AsientoContable::create([
                                'empresa_id'     => $empresaId,
                                'ejercicio_id'   => $ejercicioDic?->id,
                                'numero'         => "CIERRE-ARRASTRE-{$anio}",
                                'fecha'          => "{$anio}-12-31",
                                'concepto'       => "Arrastre resultado {$anio} a ganancias/pérdidas acumuladas",
                                'documento_tipo' => 'CIERRE_ANUAL',
                                'documento_ref'  => (string)$anio,
                                'total_debe'     => abs($utilidad),
                                'total_haber'    => abs($utilidad),
                                'es_automatico'  => true,
                                'estado'         => 1,
                                'creado_por'     => Auth::id(),
                                'created_at'     => now(),
                            ]);

                            foreach ($detallesArrastre as $det) {
                                AsientoDetalle::create([
                                    'asiento_id'  => $asientoArrastre->id,
                                    'cuenta_id'   => $det['cuenta_id'],
                                    'descripcion' => $det['descripcion'],
                                    'debe'        => $det['debe'],
                                    'haber'       => $det['haber'],
                                ]);
                            }
                            PlanCuenta::whereIn('id', [$cuentaResultadoId, $cuentaAcumuladaId])
                                ->increment('total_asientos');
                        }
                    }
                }

                // ── PASO 5: registrar en auditoría ──
                DB::table('log_cambios_criticos')->insert([
                    'usuario_id'     => Auth::id(),
                    'empresa_id'     => $empresaId,
                    'tabla'          => 'ejercicios_contables',
                    'registro_id'    => 0,
                    'campo'          => 'cierre_fiscal_anual',
                    'valor_anterior' => 'ejercicio_abierto',
                    'valor_nuevo'    => "anio:{$anio} — {$motivo} — Ingresos:{$totalIngresos} Gastos:{$totalGastos} Resultado:{$utilidad}",
                    'ip_address'     => request()->ip(),
                ]);
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success',
            "Cierre Fiscal Anual {$anio} ejecutado correctamente. " .
            "Asientos de enceramiento y arrastre de resultado registrados.");
    }
}
