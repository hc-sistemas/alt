<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\EjercicioContable;
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
        $perfil = Auth::user()->perfil->nombre ?? '';
        if ($perfil !== 'super_admin') {
            return back()->with('error',
                'Solo el Super Administrador puede reabrir períodos cerrados.');
        }

        if ($ejercicio->estaAbierto()) {
            return back()->with('error', 'Este período ya está abierto.');
        }

        $ejercicio->update([
            'estado'       => 'abierto',
            'fecha_cierre' => null,
            'cerrado_por'  => null,
        ]);

        // CORRECCIÓN 1: columnas reales de log_cambios_criticos
        DB::table('log_cambios_criticos')->insert([
            'usuario_id'     => Auth::id(),
            'empresa_id'     => $ejercicio->empresa_id,
            'tabla'          => 'ejercicios_contables',
            'registro_id'    => $ejercicio->id,
            'campo'          => 'estado',
            'valor_anterior' => 'cerrado',
            'valor_nuevo'    => 'abierto — Reapertura manual Super Admin',
            'ip_address'     => $request->ip(),
        ]);

        return back()->with('warning',
            "Período {$ejercicio->periodo_label} reabierto. " .
            "Recuerda cerrarlo cuando termines.");
    }
}
