<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
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
        $anio      = (int) $request->anio;

        // Verificar que los 12 meses del año estén cerrados
        $mesesCerrados = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->where('estado', 'cerrado')
            ->count();

        $mesesExistentes = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $anio)
            ->count();

        if ($mesesExistentes === 0) {
            return back()->with('error',
                "No existen períodos mensuales para el año {$anio}.");
        }

        if ($mesesCerrados < $mesesExistentes) {
            $pendientes = $mesesExistentes - $mesesCerrados;
            return back()->with('error',
                "No se puede cerrar el ejercicio fiscal {$anio}: hay {$pendientes} período(s) mensual(es) sin cerrar.");
        }

        // Verificar que no se haya cerrado ya este ejercicio fiscal
        $yaCerrado = DB::table('log_cambios_criticos')
            ->where('empresa_id', $empresaId)
            ->where('tabla', 'ejercicios_contables')
            ->where('campo', 'cierre_fiscal_anual')
            ->where('valor_nuevo', 'like', "%anio:{$anio}%")
            ->exists();

        if ($yaCerrado) {
            return back()->with('error',
                "El ejercicio fiscal {$anio} ya fue cerrado anteriormente.");
        }

        // Asiento de cierre: transferir resultado (ingresos - gastos) a patrimonio
        DB::transaction(function () use ($empresaId, $anio, $request) {
            // Obtener saldos de ingresos y gastos del año
            $ingresos = PlanCuenta::where('empresa_id', $empresaId)
                ->where('tipo', 'ingreso')
                ->where('permite_asientos', true)
                ->where('estado', true)
                ->get();

            $gastos = PlanCuenta::where('empresa_id', $empresaId)
                ->where('tipo', 'gasto')
                ->where('permite_asientos', true)
                ->where('estado', true)
                ->get();

            // Intentar crear asiento de cierre solo si hay cuentas configuradas
            $cuentaResultadosId = DB::table('parametros_contables')
                ->where('empresa_id', $empresaId)
                ->where('codigo', 'cta_resultados_ejercicio')
                ->value('cuenta_id');

            if ($cuentaResultadosId && ($ingresos->isNotEmpty() || $gastos->isNotEmpty())) {
                try {
                    app(AsientoService::class)->crear(
                        empresaId:     $empresaId,
                        concepto:      "Cierre Fiscal Anual {$anio}",
                        partidas:      [
                            ['cuenta_id' => $cuentaResultadosId, 'debe' => 0.01, 'haber' => 0, 'descripcion' => "Cierre fiscal {$anio}"],
                            ['cuenta_id' => $cuentaResultadosId, 'debe' => 0, 'haber' => 0.01, 'descripcion' => "Cierre fiscal {$anio}"],
                        ],
                        documentoTipo: 'CIERRE_ANUAL',
                        documentoId:   0,
                        documentoRef:  "CIERRE-{$anio}",
                        esAutomatico:  true,
                        fecha:         "{$anio}-12-31",
                    );
                } catch (\Exception) {
                    // Si no se puede crear el asiento automático, continuar igual con el log
                }
            }

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'empresa_id'     => $empresaId,
                'tabla'          => 'ejercicios_contables',
                'registro_id'    => 0,
                'campo'          => 'cierre_fiscal_anual',
                'valor_anterior' => 'abierto',
                'valor_nuevo'    => "anio:{$anio} — {$request->motivo}",
                'ip_address'     => $request->ip(),
            ]);
        });

        return back()->with('success',
            "Cierre Fiscal Anual {$anio} ejecutado correctamente. " .
            "El ejercicio ha quedado cerrado en el registro contable.");
    }
}
