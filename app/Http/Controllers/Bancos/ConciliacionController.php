<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\ConciliacionBancaria;
use App\Models\MovimientoBancario;
use App\Models\PartidaTransito;
use App\Models\ParametroContable;
use App\Models\PlanCuenta;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConciliacionController extends Controller
{
    public function __construct(private readonly AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $conciliaciones = null;

        if ($request->boolean('buscado')) {
            $query = ConciliacionBancaria::where('empresa_id', $empresaId)
                ->with('bancoCaja')
                ->orderByDesc('fecha_corte');

            if ($request->filled('banco_caja_id')) {
                $query->where('banco_caja_id', $request->banco_caja_id);
            }
            if ($request->filled('fecha_desde')) {
                $query->where('fecha_corte', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->where('fecha_corte', '<=', $request->fecha_hasta);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('buscar')) {
                $q = $request->buscar;
                $query->where(fn($qb) =>
                    $qb->where('descripcion', 'ilike', "%{$q}%")
                       ->orWhereHas('bancoCaja', fn($b) => $b->where('nombre', 'ilike', "%{$q}%"))
                );
            }

            $conciliaciones = $query->get()->map(function ($c) {
                // Una conciliación "pendiente" todavía no está congelada: si el saldo
                // del banco se movió por movimientos ajenos a esta conciliación desde
                // que se creó (o desde la última acción que la sincronizó), el valor
                // guardado en saldo_sistema/diferencia queda desactualizado. Para
                // pendientes se recalcula al vuelo contra el saldo vivo del banco —
                // solo al mostrarlo, sin persistirlo (eso lo siguen haciendo las
                // acciones explícitas: conciliarPartida/generarAsientoAjuste/
                // generarAsientoPartida/cerrar). Una vez 'conciliada' el valor
                // guardado SÍ es el histórico correcto y no se toca.
                if ($c->estado === 'pendiente' && $c->bancoCaja) {
                    $saldoSistema = (float) $c->bancoCaja->saldo_actual;
                    $diferencia   = (float) $c->saldo_banco - $saldoSistema;
                } else {
                    $saldoSistema = (float) $c->saldo_sistema;
                    $diferencia   = (float) $c->diferencia;
                }

                return [
                    'id'            => $c->id,
                    'banco_caja_id' => $c->banco_caja_id,
                    'banco'         => $c->bancoCaja?->nombre,
                    'fecha_corte'   => $c->fecha_corte?->format('d/m/Y'),
                    'saldo_banco'   => $c->saldo_banco,
                    'saldo_sistema' => $saldoSistema,
                    'diferencia'    => $diferencia,
                    'estado'        => $c->estado,
                    'tiene_dif'     => abs($diferencia) > 0.01,
                    'created_at'    => $c->created_at?->format('d/m/Y'),
                ];
            });
        }

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->bancos()->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'saldo_actual']);

        return Inertia::render('Bancos/Conciliaciones/Index', [
            'conciliaciones' => $conciliaciones,
            'bancos'         => $bancos,
            'filtros'        => $request->only(['banco_caja_id', 'fecha_desde', 'fecha_hasta', 'estado', 'buscar']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'banco_caja_id' => 'required|exists_empresa:bancos_cajas',
            'fecha_corte'   => 'required|date',
            'saldo_banco'   => 'required|numeric',
            'descripcion'   => 'nullable|string|max:300',
            'archivo'       => 'nullable|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $banco        = BancoCaja::where('empresa_id', $empresaId)->findOrFail($request->banco_caja_id);
        // Saldo según libro A LA FECHA DE CORTE (no el saldo de hoy).
        $saldoSistema = $this->saldoLibroAFecha($banco, $request->fecha_corte);
        $diferencia   = $request->saldo_banco - $saldoSistema;
        $archivoPath  = null;

        if ($request->hasFile('archivo')) {
            $archivoPath = $request->file('archivo')->store('conciliaciones', 'local');
        }

        $conciliacion = ConciliacionBancaria::create([
            'empresa_id'    => $empresaId,
            'banco_caja_id' => $request->banco_caja_id,
            'fecha_corte'   => $request->fecha_corte,
            'saldo_banco'   => $request->saldo_banco,
            'saldo_sistema' => $saldoSistema,
            'diferencia'    => $diferencia,
            'descripcion'   => $request->descripcion,
            'archivo_csv'   => $archivoPath,
            'estado'        => 'pendiente',
            'created_by'    => Auth::id(),
            'created_at'    => now(),
        ]);

        $movimientosNoConciliados = MovimientoBancario::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $request->banco_caja_id)
            ->where('fecha', '<=', $request->fecha_corte)
            ->where('conciliado', false)
            ->where('anulado', false)
            ->get();

        foreach ($movimientosNoConciliados as $mov) {
            PartidaTransito::create([
                'conciliacion_id' => $conciliacion->id,
                'tipo'            => 'sistema',
                'fecha'           => $mov->fecha,
                'descripcion'     => $mov->descripcion,
                'monto'           => $mov->monto,
                'sentido'         => $mov->tipo,
                'movimiento_id'   => $mov->id,
                'conciliada'      => false,
            ]);
        }

        $this->refrescar($conciliacion);
        $diferencia = (float) $conciliacion->fresh()->diferencia;

        $msg = "Conciliación creada. Saldo banco: \${$request->saldo_banco} · Saldo según libro al corte: \${$saldoSistema}";
        if (abs($diferencia) > 0.01) {
            $msg .= ' · Diferencia: $' . number_format(abs($diferencia), 2);
        }

        return redirect()->route('bancos.conciliaciones.show', $conciliacion)
            ->with(abs($diferencia) > 0.01 ? 'warning' : 'success', $msg);
    }

    public function show(ConciliacionBancaria $conciliacion): Response
    {
        $conciliacion->load(['bancoCaja', 'partidas.movimiento']);

        $mapPartida = fn($p) => [
            'id'          => $p->id,
            'tipo'        => $p->tipo,
            'fecha'       => $p->fecha?->format('Y-m-d'),
            'descripcion' => $p->descripcion,
            'monto'       => $p->monto,
            'conciliada'  => $p->conciliada,
            'movimiento'  => $p->movimiento ? [
                'id'          => $p->movimiento->id,
                'descripcion' => $p->movimiento->descripcion,
                'monto'       => $p->movimiento->monto,
                'tipo'        => $p->movimiento->tipo,
                'sub_tipo'    => $p->movimiento->sub_tipo,
            ] : null,
        ];

        $partidas          = $conciliacion->partidas;
        $partidasSistema   = $partidas->where('tipo', 'sistema')->values()->map($mapPartida);
        $partidasBanco     = $partidas->where('tipo', 'banco')->values()->map($mapPartida);
        $partidasConciliadas = $partidas->where('conciliada', true)->count();
        $partidasPendientes  = $partidas->where('conciliada', false)->count();

        $cuentaComision = ParametroContable::getCuenta('cta_comisiones_bancarias', (int) $conciliacion->empresa_id);
        $cuentaInteres  = PlanCuenta::where('codigo', '4.03.01')->first(); // PC-BAN-13 Intereses Ganados

        return Inertia::render('Bancos/Conciliaciones/Show', [
            'conciliacion'        => [
                'id'            => $conciliacion->id,
                'estado'        => $conciliacion->estado,
                'fecha_corte'   => $conciliacion->fecha_corte?->format('Y-m-d'),
                'saldo_banco'   => $conciliacion->saldo_banco,
                'saldo_sistema' => $conciliacion->saldo_sistema,
                'diferencia'    => $conciliacion->diferencia,
                'descripcion'   => $conciliacion->descripcion,
                'observacion'   => $conciliacion->observacion,
                'banco_caja'    => [
                    'nombre'      => $conciliacion->bancoCaja?->nombre,
                    'saldo_actual' => $conciliacion->bancoCaja?->saldo_actual,
                ],
            ],
            'partidas_sistema'    => $partidasSistema,
            'partidas_banco'      => $partidasBanco,
            'resumen'             => [
                'total_sistema'   => $partidasSistema->count(),
                'total_banco'     => $partidasBanco->count(),
                'conciliadas'     => $partidasConciliadas,
                'pendientes'      => $partidasPendientes,
            ],
            'cuentas' => PlanCuenta::where('permite_asientos', true)->where('estado', true)
                ->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'cuenta_comision_sugerida_id' => $cuentaComision?->id,
            'cuenta_interes_sugerida_id'  => $cuentaInteres?->id,
            'calculo'                     => $this->calculo($conciliacion),
        ]);
    }

    // ── Upload CSV / estado de cuenta bancario ─────────────────────────────────
    public function uploadEstadoCuenta(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }

        $ext = strtolower($request->file('archivo')->getClientOriginalExtension());

        // Procesar XLSX/XLS con maatwebsite
        if (in_array($ext, ['xlsx', 'xls'])) {
            return $this->uploadEstadoCuentaExcel($request, $conciliacion);
        }

        $contenido = file_get_contents($request->file('archivo')->getRealPath());
        // Quitar BOM UTF-8
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $lineas    = array_filter(explode("\n", str_replace("\r\n", "\n", $contenido)));
        $lineas    = array_values($lineas);

        if (count($lineas) < 2) {
            return back()->with('error', 'El archivo CSV está vacío o no tiene datos.');
        }

        // Detectar delimitador (coma o punto y coma)
        $cabecera   = $lineas[0];
        $delimitador = str_contains($cabecera, ';') ? ';' : ',';
        $columnas    = array_map('trim', str_getcsv($cabecera, $delimitador));

        // Mapear columnas por palabras clave
        $idxFecha  = $this->encontrarColumna($columnas, ['fecha', 'date', 'dia']);
        $idxDesc   = $this->encontrarColumna($columnas, ['descripcion', 'concepto', 'detalle', 'description']);
        [$idxMonto, $idxDebito, $idxCredito] = $this->columnasMonto($columnas);

        if ($idxFecha === null || ($idxMonto === null && $idxDebito === null && $idxCredito === null)) {
            return back()->with('error', 'No se pudo detectar las columnas de fecha y monto (o débito/crédito) en el CSV.');
        }

        $importadas = 0;
        $errores    = 0;

        DB::transaction(function () use ($lineas, $delimitador, $idxFecha, $idxDesc, $idxMonto, $idxDebito, $idxCredito, $conciliacion, &$importadas, &$errores) {
            foreach (array_slice($lineas, 1) as $linea) {
                if (empty(trim($linea))) continue;
                $cols = array_map('trim', str_getcsv($linea, $delimitador));

                $fechaRaw = $cols[$idxFecha] ?? null;
                [$monto, $sentido] = $this->montoConSentido($cols, $idxMonto, $idxDebito, $idxCredito);
                $desc     = $idxDesc !== null ? ($cols[$idxDesc] ?? 'Sin descripción') : 'Sin descripción';

                if (!$fechaRaw || !$monto) { $errores++; continue; }

                $fecha = null;
                foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $fmt) {
                    $dt = \DateTime::createFromFormat($fmt, $fechaRaw);
                    if ($dt) { $fecha = $dt->format('Y-m-d'); break; }
                }
                if (!$fecha) { $errores++; continue; }

                PartidaTransito::create([
                    'conciliacion_id' => $conciliacion->id,
                    'tipo'            => 'banco',
                    'fecha'           => $fecha,
                    'descripcion'     => substr($desc, 0, 300),
                    'monto'           => abs($monto),
                    'sentido'         => $sentido,
                    'conciliada'      => false,
                ]);
                $importadas++;
            }
        });

        $autoMatch = $this->autoMatchPartidas($conciliacion);
        $this->refrescar($conciliacion);

        $msg = "CSV importado: {$importadas} movimientos del banco cargados.";
        if ($autoMatch > 0) $msg .= " Se cruzaron automáticamente {$autoMatch} partida(s) (±2 días, ±\$0.01).";
        if ($errores > 0) $msg .= " ({$errores} filas con errores omitidas)";

        return back()->with('success', $msg);
    }

    // ── Cruce manual de una partida sistema con una partida banco ─────────────
    public function conciliarPartida(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate([
            'partida_sistema_id' => 'required|exists:partidas_transito,id',
            'partida_banco_id'   => 'required|exists:partidas_transito,id',
            'generar_ajuste'     => 'sometimes|boolean',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }

        $mensaje = 'Partidas cruzadas correctamente.';

        DB::transaction(function () use ($request, $conciliacion, &$mensaje) {
            $pSistema = PartidaTransito::findOrFail($request->partida_sistema_id);
            $pBanco   = PartidaTransito::findOrFail($request->partida_banco_id);

            // Verificar que ambas pertenecen a esta conciliación
            if ($pSistema->conciliacion_id !== $conciliacion->id || $pBanco->conciliacion_id !== $conciliacion->id) {
                abort(422, 'Las partidas no pertenecen a esta conciliación.');
            }
            if ($pSistema->tipo !== 'sistema' || $pBanco->tipo !== 'banco') {
                abort(422, 'Las partidas deben ser una de sistema y otra de banco.');
            }

            // Diferencia real entre lo que dice el banco y lo que dice el sistema para
            // este cruce puntual. Positiva → el banco registra más (falta un ingreso);
            // negativa → el banco registra menos (falta un egreso).
            $diferenciaMonto = round((float) $pBanco->monto - (float) $pSistema->monto, 2);
            $hayDiferencia   = abs($diferenciaMonto) > 0.01;

            $pSistema->update(['conciliada' => true]);
            $pBanco->update(['conciliada' => true]);

            if (!$hayDiferencia) {
                if ($pSistema->movimiento_id) {
                    MovimientoBancario::where('id', $pSistema->movimiento_id)->update(['conciliado' => true, 'fecha_conciliacion' => now()->toDateString()]);
                    \App\Models\Cheque::sincronizarConciliacion([$pSistema->movimiento_id], true);
                }
            } elseif ($request->boolean('generar_ajuste')) {
                // El contador decidió justificar contablemente la diferencia del cruce:
                // se genera un movimiento + asiento de ajuste (misma cuenta/mecanismo que
                // el ajuste global de diferencia), y el movimiento original queda conciliado.
                $empresaId   = $conciliacion->empresa_id;
                $banco       = $conciliacion->bancoCaja;
                $montoAjuste = abs($diferenciaMonto);
                $tipoAjuste  = $diferenciaMonto > 0 ? 'ingreso' : 'egreso';
                $desc        = "Ajuste cruce manual — {$pSistema->descripcion} (sistema \${$pSistema->monto}) vs {$pBanco->descripcion} (banco \${$pBanco->monto})";

                $movimiento = MovimientoBancario::create([
                    'empresa_id'     => $empresaId,
                    'banco_caja_id'  => $banco->id,
                    'tipo'           => $tipoAjuste,
                    'sub_tipo'       => 'transferencia',
                    'fecha'          => $pBanco->fecha,
                    'monto'          => $montoAjuste,
                    'descripcion'    => $desc,
                    'documento_tipo' => 'AJUSTE_CRUCE',
                    'documento_id'   => $conciliacion->id,
                    'conciliado'     => true,
                    'fecha_conciliacion' => now()->toDateString(),
                    'anulado'        => false,
                    'created_by'     => Auth::id(),
                ]);

                $banco->actualizarSaldo($montoAjuste, $tipoAjuste);

                // La fecha contable del ajuste es la del corte de la
                // conciliacion, no la de hoy: si no, conciliar en octubre el
                // corte de septiembre metia el ajuste en el mes equivocado.
                $asiento = $this->asientoService->ajusteConciliacion(
                    $empresaId,
                    $conciliacion->id,
                    $diferenciaMonto,
                    $desc,
                    $conciliacion->fecha_corte?->toDateString(),
                    $conciliacion->bancoCaja->cuenta_id,
                );
                $movimiento->update(['asiento_id' => $asiento->id]);

                if ($pSistema->movimiento_id) {
                    MovimientoBancario::where('id', $pSistema->movimiento_id)->update(['conciliado' => true, 'fecha_conciliacion' => now()->toDateString()]);
                    \App\Models\Cheque::sincronizarConciliacion([$pSistema->movimiento_id], true);
                }

                $mensaje = 'Partidas cruzadas. Se generó un asiento de ajuste de $' . number_format($montoAjuste, 2) . ' por la diferencia.';
            } else {
                // Cruce confirmado sin ajuste: el movimiento original queda SIN marcar
                // conciliado (hay una diferencia real sin justificar) y esa diferencia
                // se refleja abajo en saldo_sistema/diferencia de la conciliación.
                $mensaje = 'Partidas cruzadas SIN ajuste — quedó una diferencia de $' . number_format(abs($diferenciaMonto), 2) . ' reflejada en el saldo de la conciliación.';
            }

            // Recalcular saldo_sistema/diferencia SIEMPRE tras esta acción (mismo mecanismo
            // que generarAsientoAjuste()/generarAsientoPartida()/cerrar()), para que
            // "Diferencia" nunca quede desactualizada, haya o no ajuste de por medio.
            $this->refrescar($conciliacion);
        });

        return back()->with('success', $mensaje);
    }

    // ── Generar asiento de ajuste para la diferencia ──────────────────────────
    public function generarAsientoAjuste(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate([
            'descripcion' => 'nullable|string|max:300',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }
        if (!$conciliacion->tieneDiferencia()) {
            return back()->with('error', 'No hay diferencia que ajustar (cuentas cuadradas).');
        }

        $empresaId = session('empresa_activa_id');
        $desc      = $request->descripcion ?: "Ajuste conciliación bancaria #{$conciliacion->id}";

        DB::transaction(function () use ($conciliacion, $empresaId, $desc) {
            $diferencia = (float) $conciliacion->diferencia;

            $asiento = $this->asientoService->ajusteConciliacion(
                $empresaId,
                $conciliacion->id,
                $diferencia,
                $desc,
                $conciliacion->fecha_corte?->toDateString(),
                $conciliacion->bancoCaja->cuenta_id,
            );

            // Crear partida banco para el ajuste
            PartidaTransito::create([
                'conciliacion_id'    => $conciliacion->id,
                'tipo'               => 'banco',
                'fecha'              => now()->format('Y-m-d'),
                'descripcion'        => $desc,
                'monto'              => abs($diferencia),
                'sentido'            => $diferencia > 0 ? 'ingreso' : 'egreso',
                'conciliada'         => true,
                'asiento_generado_id' => $asiento->id,
            ]);

            // Sincronizar el saldo cacheado del banco con el ajuste contable recién
            // registrado (ajusteConciliacion solo afecta el mayor contable) y reflejar
            // en la conciliación que la diferencia quedó resuelta. Sin esto, "Diferencia"
            // mostraría el valor original para siempre, incluso ya cerrada la conciliación.
            $conciliacion->bancoCaja->actualizarSaldo(abs($diferencia), $diferencia > 0 ? 'ingreso' : 'egreso');
            $this->refrescar($conciliacion);
        });

        return back()->with('success', 'Asiento de ajuste generado correctamente.');
    }

    // ── Generar movimiento + asiento para UNA partida puntual sin contraparte ──
    // (ej. una comisión bancaria que aparece en el extracto pero nunca se registró
    // en el sistema). A diferencia de generarAsientoAjuste() (que resuelve la
    // diferencia GLOBAL declarada), esto resuelve la partida específica.
    public function generarAsientoPartida(Request $request, ConciliacionBancaria $conciliacion, PartidaTransito $partida): RedirectResponse
    {
        $request->validate([
            'tipo'                    => 'required|in:ingreso,egreso',
            'cuenta_contrapartida_id' => 'required|exists:plan_cuentas,id',
            'descripcion'             => 'nullable|string|max:300',
        ]);

        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }
        if ($partida->conciliacion_id !== $conciliacion->id) {
            abort(422, 'La partida no pertenece a esta conciliación.');
        }
        if ($partida->tipo !== 'banco') {
            return back()->with('error', 'Solo se puede generar un movimiento para partidas del extracto bancario.');
        }
        if ($partida->conciliada) {
            return back()->with('error', 'Esta partida ya está conciliada.');
        }

        $banco = $conciliacion->bancoCaja;
        if (!$banco->cuenta_id) {
            return back()->with('error', "El banco {$banco->nombre} no tiene una cuenta contable vinculada en el plan de cuentas.");
        }

        $empresaId = session('empresa_activa_id');
        $desc      = $request->descripcion ?: $partida->descripcion;
        $monto     = (float) $partida->monto;

        DB::transaction(function () use ($conciliacion, $partida, $request, $empresaId, $desc, $monto, $banco) {
            $movimiento = MovimientoBancario::create([
                'empresa_id'              => $empresaId,
                'banco_caja_id'           => $conciliacion->banco_caja_id,
                'tipo'                    => $request->tipo,
                'sub_tipo'                => 'transferencia',
                'fecha'                   => $partida->fecha,
                'monto'                   => $monto,
                'descripcion'             => $desc,
                'cuenta_contrapartida_id' => $request->cuenta_contrapartida_id,
                'documento_tipo'          => 'CONCILIACION',
                'documento_id'            => $conciliacion->id,
                'conciliado'              => true,
                'fecha_conciliacion'      => now()->toDateString(),
                'anulado'                 => false,
                'created_by'              => Auth::id(),
            ]);

            $banco->actualizarSaldo($monto, $request->tipo);

            $partidasAsiento = $request->tipo === 'ingreso'
                ? [
                    ['cuenta_id' => $banco->cuenta_id,             'debe' => $monto, 'haber' => 0,     'descripcion' => $desc],
                    ['cuenta_id' => $request->cuenta_contrapartida_id, 'debe' => 0,     'haber' => $monto, 'descripcion' => $desc],
                  ]
                : [
                    ['cuenta_id' => $request->cuenta_contrapartida_id, 'debe' => $monto, 'haber' => 0,     'descripcion' => $desc],
                    ['cuenta_id' => $banco->cuenta_id,             'debe' => 0,     'haber' => $monto, 'descripcion' => $desc],
                  ];

            $asiento = $this->asientoService->crear(
                empresaId:     $empresaId,
                concepto:      $desc,
                partidas:      $partidasAsiento,
                documentoTipo: 'BANCO',
                documentoId:   $movimiento->id,
                esAutomatico:  true,
                fecha:         $partida->fecha->format('Y-m-d'),
            );
            $movimiento->update(['asiento_id' => $asiento->id]);

            $partida->update([
                'movimiento_id'       => $movimiento->id,
                'asiento_generado_id' => $asiento->id,
                'sentido'             => $request->tipo,
                'conciliada'          => true,
            ]);

            // Igual que en generarAsientoAjuste(): sincronizar el saldo del banco y
            // reflejar el efecto en la conciliación, para que "Diferencia" no quede
            // desactualizada tras esta acción.
            $banco->refresh();
            $this->refrescar($conciliacion);
        });

        return back()->with('success', 'Movimiento y asiento generados. Partida conciliada.');
    }

    // ── Cerrar conciliación ───────────────────────────────────────────────────
    public function cerrar(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'La conciliación ya está cerrada.');
        }

        $this->refrescar($conciliacion);
        $calculo = $this->calculo($conciliacion->fresh());
        $dif     = (float) $calculo['diferencia'];

        // Con diferencia: se exige observación y la diferencia se registra como
        // ajuste (PC-BAN-15 sobrante / PC-BAN-16 faltante).
        if (abs($dif) > 0.01) {
            $request->validate(
                ['observacion' => 'required|string|min:5|max:500'],
                ['observacion.required' => 'Hay una diferencia de $' . number_format(abs($dif), 2) . ': escriba la observación para poder cerrar.']
            );
        }

        try {
            DB::transaction(function () use ($request, $conciliacion, $dif) {
                if (abs($dif) > 0.01) {
                    $this->registrarAjusteDiferencia($conciliacion, $dif, (string) $request->observacion);
                }

                // Solo quedan conciliados los movimientos que se emparejaron con el
                // banco; los cheques no cobrados y depósitos en tránsito siguen
                // pendientes para el próximo período.
                $movIds = $conciliacion->partidas()
                    ->where('tipo', 'sistema')->where('conciliada', true)
                    ->whereNotNull('movimiento_id')->pluck('movimiento_id');
                MovimientoBancario::whereIn('id', $movIds)->update(['conciliado' => true, 'fecha_conciliacion' => now()->toDateString()]);
                \App\Models\Cheque::sincronizarConciliacion($movIds, true);

                $this->refrescar($conciliacion);
                $conciliacion->update([
                    'estado'      => 'conciliada',
                    'observacion' => $request->filled('observacion') ? $request->observacion : $conciliacion->observacion,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo cerrar la conciliación: ' . $e->getMessage());
        }

        return back()->with('success', 'Conciliación cerrada correctamente.');
    }

    /**
     * Anula una conciliación cerrada (02-06 §2.4: «no se modifica sin anular la conciliación»). Queda abierta para corregirla:
     * libera los movimientos que había conciliado y reversa el asiento y el saldo del ajuste por diferencia. Las notas del
     * banco (comisiones, intereses) son movimientos reales: se conservan, solo vuelven a quedar pendientes de conciliar.
     */
    public function anular(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        $request->validate(
            ['motivo' => 'required|string|min:5|max:300'],
            ['motivo.required' => 'Escriba el motivo de la anulación.']
        );

        if (!$conciliacion->estaConciliada()) {
            return back()->with('error', 'Solo se puede anular una conciliación cerrada.');
        }

        $posterior = ConciliacionBancaria::where('banco_caja_id', $conciliacion->banco_caja_id)
            ->where('estado', 'conciliada')
            ->where(fn ($q) => $q->where('fecha_corte', '>', $conciliacion->fecha_corte)
                ->orWhere(fn ($q2) => $q2->where('fecha_corte', $conciliacion->fecha_corte)->where('id', '>', $conciliacion->id)))
            ->exists();
        if ($posterior) {
            return back()->with('error', 'Hay una conciliación posterior cerrada para este banco: anule primero la más reciente.');
        }

        try {
            DB::transaction(function () use ($request, $conciliacion) {
                $banco = $conciliacion->bancoCaja;
                $partidas = $conciliacion->partidas()->where('conciliada', true)->get();

                MovimientoBancario::whereIn('id', $partidas->whereNotNull('movimiento_id')->pluck('movimiento_id'))
                    ->update(['conciliado' => false, 'fecha_conciliacion' => null]);
                \App\Models\Cheque::sincronizarConciliacion($partidas->whereNotNull('movimiento_id')->pluck('movimiento_id'), false);

                // Ajuste por diferencia (sin movimiento): se reversa el asiento y el saldo del banco
                foreach ($partidas->whereNull('movimiento_id')->whereNotNull('asiento_generado_id') as $ajuste) {
                    $asiento = \App\Models\AsientoContable::find($ajuste->asiento_generado_id);
                    if ($asiento && !$asiento->estaAnulado()) {
                        $this->asientoService->anular($asiento, "Anulación de conciliación #{$conciliacion->id}");
                    }
                    $banco->actualizarSaldo((float) $ajuste->monto, $ajuste->sentido === 'ingreso' ? 'egreso' : 'ingreso');
                    $ajuste->delete();
                }

                $this->refrescar($conciliacion);
                $conciliacion->update([
                    'estado'      => 'pendiente',
                    'observacion' => trim(($conciliacion->observacion ? $conciliacion->observacion . "\n" : '')
                        . 'Anulada el ' . now()->format('d/m/Y H:i') . ' por ' . (Auth::user()->nombre ?? Auth::id()) . ': ' . $request->motivo),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo anular la conciliación: ' . $e->getMessage());
        }

        return back()->with('success', 'Conciliación anulada. Quedó abierta para corregirla.');
    }

    /** Plantilla CSV del estado de cuenta (fecha, descripción, débito, crédito) para importar en la conciliación. */
    public function plantilla(Request $request)
    {
        $nombre = 'estado_cuenta';
        if ($request->filled('banco_caja_id')) {
            $banco = BancoCaja::where('empresa_id', session('empresa_activa_id'))->find($request->banco_caja_id);
            $nombre = 'plantilla_' . \Illuminate\Support\Str::slug($banco?->nombre ?? 'banco', '_');
        }

        $csv = "\xEF\xBB\xBF" . "Fecha;Descripcion;Debito;Credito;Referencia\r\n"
            . now()->format('d/m/Y') . ";Ejemplo: transferencia recibida;;100.00;TRF-001\r\n"
            . now()->format('d/m/Y') . ";Ejemplo: comision bancaria;2.50;;\r\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombre}.csv\"",
        ]);
    }

    // ── Cálculo de la conciliación (02-06 §2.4) ───────────────────────────────

    /** Saldo según libro a una fecha: el saldo actual menos lo registrado después de esa fecha. */
    private function saldoLibroAFecha(BancoCaja $banco, string $fecha): float
    {
        $despues = MovimientoBancario::where('banco_caja_id', $banco->id)
            ->where('anulado', false)->where('fecha', '>', $fecha)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) as neto")
            ->value('neto');

        return round((float) $banco->saldo_actual - (float) $despues, 2);
    }

    /**
     * saldo según banco = libro − depósitos en tránsito + cheques/egresos no cobrados
     *                     + notas de crédito del banco − notas de débito del banco
     * (cada partida pendiente se resta o suma según su sentido). La diferencia es lo que
     * declara el estado de cuenta menos ese saldo esperado.
     */
    private function calculo(ConciliacionBancaria $conciliacion): array
    {
        $pend = fn (string $tipo, string $sentido) => round((float) PartidaTransito::where('conciliacion_id', $conciliacion->id)
            ->where('tipo', $tipo)->where('conciliada', false)->where('sentido', $sentido)->sum('monto'), 2);

        $libro            = $this->saldoLibroAFecha($conciliacion->bancoCaja, $conciliacion->fecha_corte->toDateString());
        $depositosTransito = $pend('sistema', 'ingreso');
        $chequesNoCobrados = $pend('sistema', 'egreso');
        $notasCredito      = $pend('banco', 'ingreso');
        $notasDebito       = $pend('banco', 'egreso');

        $esperado  = round($libro - $depositosTransito + $chequesNoCobrados + $notasCredito - $notasDebito, 2);
        $diferencia = round((float) $conciliacion->saldo_banco - $esperado, 2);

        return [
            'saldo_libro'        => $libro,
            'depositos_transito' => $depositosTransito,
            'cheques_no_cobrados' => $chequesNoCobrados,
            'notas_credito'      => $notasCredito,
            'notas_debito'       => $notasDebito,
            'saldo_esperado'     => $esperado,
            'saldo_banco'        => (float) $conciliacion->saldo_banco,
            'diferencia'         => $diferencia,
        ];
    }

    /** Guarda en la conciliación el saldo del libro al corte y la diferencia calculada. */
    private function refrescar(ConciliacionBancaria $conciliacion): void
    {
        $c = $this->calculo($conciliacion->fresh(['bancoCaja']));
        $conciliacion->update(['saldo_sistema' => $c['saldo_libro'], 'diferencia' => $c['diferencia']]);
    }

    /** Asiento y partida del ajuste por la diferencia no explicada (PC-BAN-15 / PC-BAN-16). */
    private function registrarAjusteDiferencia(ConciliacionBancaria $conciliacion, float $dif, string $observacion): void
    {
        $desc = "Ajuste conciliación #{$conciliacion->id}: {$observacion}";

        $asiento = $this->asientoService->ajusteConciliacion(
            (int) $conciliacion->empresa_id,
            $conciliacion->id,
            $dif,
            $desc,
            $conciliacion->fecha_corte?->toDateString(),
            $conciliacion->bancoCaja->cuenta_id,
        );

        PartidaTransito::create([
            'conciliacion_id'     => $conciliacion->id,
            'tipo'                => 'banco',
            'fecha'               => $conciliacion->fecha_corte?->toDateString(),
            'descripcion'         => $desc,
            'monto'               => abs($dif),
            'sentido'             => $dif > 0 ? 'ingreso' : 'egreso',
            'conciliada'          => true,
            'asiento_generado_id' => $asiento->id,
        ]);

        $conciliacion->bancoCaja->actualizarSaldo(abs($dif), $dif > 0 ? 'ingreso' : 'egreso');
    }

    /** Detecta las columnas de monto: una sola con signo, o débito y crédito por separado. */
    private function columnasMonto(array $columnas): array
    {
        $cols = array_map(fn ($c) => strtolower(trim((string) $c)), $columnas);

        return [
            $this->encontrarColumna($cols, ['monto', 'valor', 'importe', 'amount']),
            $this->encontrarColumna($cols, ['debito', 'débito', 'retiro', 'egreso', 'cargo']),
            $this->encontrarColumna($cols, ['credito', 'crédito', 'deposito', 'depósito', 'abono', 'ingreso']),
        ];
    }

    /**
     * Monto absoluto y sentido de una línea del estado de cuenta.
     * Débito = sale dinero del banco (egreso); crédito = entra (ingreso).
     * Con una sola columna, el signo negativo (o paréntesis) es egreso.
     */
    private function montoConSentido(array $fila, ?int $idxMonto, ?int $idxDebito, ?int $idxCredito): array
    {
        $num = function ($raw): float {
            $t = trim((string) $raw);
            if ($t === '') return 0.0;
            $neg = str_contains($t, '-') || (str_starts_with($t, '(') && str_ends_with($t, ')'));
            $t = preg_replace('/[^0-9.,]/', '', $t);
            // 1.234,56 (coma decimal) o 1,234.56 (punto decimal)
            if (str_contains($t, ',') && str_contains($t, '.')) {
                $t = strrpos($t, ',') > strrpos($t, '.') ? str_replace(['.', ','], ['', '.'], $t) : str_replace(',', '', $t);
            } else {
                $t = str_replace(',', '.', $t);
            }
            return $neg ? -(float) $t : (float) $t;
        };

        $deb = $idxDebito  !== null ? abs($num($fila[$idxDebito]  ?? '')) : 0.0;
        $cre = $idxCredito !== null ? abs($num($fila[$idxCredito] ?? '')) : 0.0;
        if ($deb > 0) return [$deb, 'egreso'];
        if ($cre > 0) return [$cre, 'ingreso'];

        if ($idxMonto !== null) {
            $m = $num($fila[$idxMonto] ?? '');
            if ($m != 0.0) return [abs($m), $m < 0 ? 'egreso' : 'ingreso'];
        }

        return [null, null];
    }

    // ── Eliminar conciliación (solo si está pendiente) ─────────────────────────
    public function destroy(ConciliacionBancaria $conciliacion): RedirectResponse
    {
        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'No se puede eliminar una conciliación ya cerrada.');
        }

        DB::transaction(function () use ($conciliacion) {
            $conciliacion->partidas()->delete();
            $conciliacion->delete();
        });

        return redirect()->route('bancos.conciliaciones.index')
            ->with('success', 'Conciliación eliminada.');
    }

    // Candado: antes marcaba todo como conciliado sin validar partidas pendientes ni la diferencia
    // de saldos. Ahora exige exactamente las mismas condiciones que cerrar().
    public function marcarConciliada(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        return $this->cerrar($request, $conciliacion);
    }

    // ── Helper: procesar XLSX/XLS ─────────────────────────────────────────────
    private function uploadEstadoCuentaExcel(Request $request, ConciliacionBancaria $conciliacion): RedirectResponse
    {
        try {
            $rows = \Maatwebsite\Excel\Facades\Excel::toArray(new class {}, $request->file('archivo'))[0] ?? [];
        } catch (\Exception $e) {
            return back()->with('error', 'Error leyendo el archivo Excel: ' . $e->getMessage());
        }

        if (count($rows) < 2) {
            return back()->with('error', 'El archivo Excel está vacío o no tiene datos.');
        }

        $cabecera    = array_map(fn($c) => strtolower(trim((string)$c)), $rows[0]);
        $idxFecha    = $this->encontrarColumna($cabecera, ['fecha', 'date', 'dia']);
        $idxDesc     = $this->encontrarColumna($cabecera, ['descripcion', 'concepto', 'detalle', 'description']);
        [$idxMonto, $idxDebito, $idxCredito] = $this->columnasMonto($cabecera);

        if ($idxFecha === null || ($idxMonto === null && $idxDebito === null && $idxCredito === null)) {
            return back()->with('error', 'No se pudo detectar las columnas de fecha y monto (o débito/crédito) en el Excel.');
        }

        $importadas = 0;
        $errores    = 0;

        DB::transaction(function () use ($rows, $idxFecha, $idxDesc, $idxMonto, $idxDebito, $idxCredito, $conciliacion, &$importadas, &$errores) {
            foreach (array_slice($rows, 1) as $row) {
                $fechaRaw = isset($row[$idxFecha]) ? trim((string)$row[$idxFecha]) : null;
                $desc     = $idxDesc !== null ? trim((string)($row[$idxDesc] ?? 'Sin descripción')) : 'Sin descripción';

                [$monto, $sentido] = $this->montoConSentido($row, $idxMonto, $idxDebito, $idxCredito);
                if (!$fechaRaw || !$monto) { $errores++; continue; }

                // Excel puede dar fecha como número serial
                $fecha = null;
                if (is_numeric($fechaRaw)) {
                    try {
                        $fecha = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$fechaRaw)
                            ->format('Y-m-d');
                    } catch (\Exception) {}
                }
                if (!$fecha) {
                    foreach (['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y'] as $fmt) {
                        $dt = \DateTime::createFromFormat($fmt, $fechaRaw);
                        if ($dt) { $fecha = $dt->format('Y-m-d'); break; }
                    }
                }
                if (!$fecha) { $errores++; continue; }

                PartidaTransito::create([
                    'conciliacion_id' => $conciliacion->id,
                    'tipo'            => 'banco',
                    'fecha'           => $fecha,
                    'descripcion'     => substr($desc, 0, 300),
                    'monto'           => abs($monto),
                    'sentido'         => $sentido,
                    'conciliada'      => false,
                ]);
                $importadas++;
            }
        });

        $autoMatch = $this->autoMatchPartidas($conciliacion);
        $this->refrescar($conciliacion);

        $msg = "Excel importado: {$importadas} movimientos del banco cargados.";
        if ($autoMatch > 0) $msg .= " Se cruzaron automáticamente {$autoMatch} partida(s) (±2 días, ±\$0.01).";
        if ($errores > 0) $msg .= " ({$errores} filas omitidas)";

        return back()->with('success', $msg);
    }

    // ── Auto-match: cruza partidas banco vs sistema con tolerancia ±2d ±$0.01 ──
    private function autoMatchPartidas(ConciliacionBancaria $conciliacion): int
    {
        $matched = 0;

        $partidasBanco = PartidaTransito::where('conciliacion_id', $conciliacion->id)
            ->where('tipo', 'banco')->where('conciliada', false)->get();

        $partidasSistema = PartidaTransito::where('conciliacion_id', $conciliacion->id)
            ->where('tipo', 'sistema')->where('conciliada', false)->get();

        $usadosIds = [];

        foreach ($partidasBanco as $pBanco) {
            $montoB = (float) $pBanco->monto;
            $fechaB = \Carbon\Carbon::parse($pBanco->fecha);

            $candidatos = $partidasSistema->filter(function ($pS) use ($pBanco, $montoB, $fechaB, $usadosIds) {
                if (in_array($pS->id, $usadosIds)) return false;
                // Un ingreso del libro no se empareja con un egreso del banco.
                if ($pBanco->sentido && $pS->sentido && $pBanco->sentido !== $pS->sentido) return false;
                if (abs($montoB - (float) $pS->monto) > 0.01) return false;
                // abs(): diffInDays() en Carbon 3 es firmado (negativo cuando la fecha de
                // sistema es posterior a $fechaB); sin abs() la tolerancia de ±2 días dejaba
                // de ser simétrica y aceptaba cualquier partida de sistema posterior sin límite.
                return abs(\Carbon\Carbon::parse($pS->fecha)->diffInDays($fechaB)) <= 2;
            });

            if ($candidatos->count() === 1) {
                $pSistema = $candidatos->first();
                $usadosIds[] = $pSistema->id;

                DB::transaction(function () use ($pBanco, $pSistema) {
                    $pBanco->update(['conciliada' => true]);
                    $pSistema->update(['conciliada' => true]);
                    if ($pSistema->movimiento_id) {
                        MovimientoBancario::where('id', $pSistema->movimiento_id)
                            ->update(['conciliado' => true, 'fecha_conciliacion' => now()->toDateString()]);
                        \App\Models\Cheque::sincronizarConciliacion([$pSistema->movimiento_id], true);
                    }
                });

                $matched++;
            }
        }

        return $matched;
    }

    // ── Helper: detectar columna por palabras clave ───────────────────────────
    private function encontrarColumna(array $columnas, array $palabras): ?int
    {
        foreach ($columnas as $i => $col) {
            $colNorm = strtolower(trim($col));
            foreach ($palabras as $palabra) {
                if (str_contains($colNorm, $palabra)) return $i;
            }
        }
        return null;
    }
}
