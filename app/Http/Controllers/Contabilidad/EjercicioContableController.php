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

        // CORRECCIÓN 4: solo 1 período abierto a la vez
        $periodosAbiertos = EjercicioContable::where('empresa_id', $empresaId)
            ->where('estado', 'abierto')->count();

        if ($periodosAbiertos >= 1) {
            return back()->with('error',
                'Ya existe un período abierto. Ciérralo antes de abrir uno nuevo.');
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

    public function reabrir(Request $request, EjercicioContable $ejercicio): RedirectResponse
    {
        return back()->with('error',
            'Los períodos contables cerrados no pueden reabrirse. ' .
            'Esta es una restricción contable permanente para garantizar la integridad del libro mayor. ' .
            'Si necesitas registrar ajustes, abre el período mensual siguiente.');
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

        $yaCerrado = DB::table('log_cambios_criticos')
            ->where('empresa_id', $empresaId)
            ->where('tabla', 'ejercicios_contables')
            ->where('campo', 'cierre_fiscal_anual')
            ->where('valor_nuevo', 'like', "%anio:{$anio}%")
            ->exists();

        if ($yaCerrado) {
            return back()->with('error', "El ejercicio fiscal {$anio} ya fue cerrado anteriormente.");
        }

        try {
            DB::transaction(function () use ($empresaId, $anio, $motivo) {

                // Ejercicio de diciembre (para vincular los asientos de cierre)
                $ejercicioDic = EjercicioContable::where('empresa_id', $empresaId)
                    ->where('anio', $anio)
                    ->orderByDesc('mes')
                    ->first();

                // IDs de asientos activos del año
                $asientoIds = AsientoContable::where('empresa_id', $empresaId)
                    ->where('estado', 1)
                    ->whereHas('ejercicio', fn($q) => $q->where('anio', $anio))
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
                $cuentaResultadoId = DB::table('parametros_contables')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', 'cta_resultados_ejercicio')
                    ->value('cuenta_id');

                if (!$cuentaResultadoId) {
                    $cr = PlanCuenta::where(fn($q) => $q->where('codigo', 'like', '3.1.5%')
                            ->orWhere('descripcion', 'ilike', '%utilidad%periodo%')
                            ->orWhere('descripcion', 'ilike', '%resultado%ejercicio%'))
                        ->first();
                    $cuentaResultadoId = $cr?->id;
                }

                if (!empty($detallesCierre) && !$cuentaResultadoId) {
                    throw new \Exception(
                        'Configure la cuenta "cta_resultados_ejercicio" en Parámetros Contables antes del cierre fiscal.'
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
                        $cuentaAcumuladaId = DB::table('parametros_contables')
                            ->where('empresa_id', $empresaId)
                            ->where('codigo', 'cta_ganancias_acumuladas')
                            ->value('cuenta_id');

                        if (!$cuentaAcumuladaId) {
                            $ca = PlanCuenta::where(fn($q) => $q->where('codigo', 'like', '3.1.4%')
                                    ->orWhere('descripcion', 'ilike', '%ganancias%acumuladas%')
                                    ->orWhere('descripcion', 'ilike', '%utilidades%acumuladas%'))
                                ->first();
                            $cuentaAcumuladaId = $ca?->id;
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
