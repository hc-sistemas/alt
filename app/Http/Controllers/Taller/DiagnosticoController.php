<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerDiagnostico;
use App\Models\TallerOrdenTrabajo;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DiagnosticoController extends Controller
{
    /** Estados de OT en los que todavía tiene sentido registrar o resolver un diagnóstico. */
    private const ESTADOS_ABIERTOS = ['pendiente', 'en_proceso'];

    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function create(TallerOrdenTrabajo $orden): Response|RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        if (!in_array($orden->estado, self::ESTADOS_ABIERTOS, true)) {
            return redirect()->route('taller.ordenes.show', $orden->id)
                ->with('flash', ['tipo' => 'error', 'mensaje' => 'Solo se puede diagnosticar una orden pendiente o en proceso.']);
        }

        $orden->load(['ingreso.cliente', 'ingreso.equipo']);

        return Inertia::render('Taller/Diagnosticos/Form', [
            'orden' => $orden,
        ]);
    }

    public function store(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        if (!in_array($orden->estado, self::ESTADOS_ABIERTOS, true)) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'Solo se puede diagnosticar una orden pendiente o en proceso.']);
        }

        $data = $request->validate([
            'diagnostico'     => 'required|string',
            'tiempo_estimado' => 'nullable|integer|min:0',
            'tipo_tiempo'     => 'nullable|string|in:horas,dias',
        ]);

        $diagnostico = DB::transaction(function () use ($orden, $data) {
            $diagnostico = TallerDiagnostico::create([
                'orden_id'        => $orden->id,
                'tecnico_id'      => Auth::id(),
                'fecha'           => now()->toDateString(),
                'hora'            => now()->toTimeString(),
                'diagnostico'     => $data['diagnostico'],
                'tiempo_estimado' => $data['tiempo_estimado'] ?? null,
                'tipo_tiempo'     => $data['tipo_tiempo'] ?? null,
                'estado'          => 'pendiente',
            ]);

            if ($orden->estado === 'pendiente') {
                $orden->estado = 'en_proceso';
                $orden->save();
            }

            // "En diagnóstico" solo si el ingreso aún no avanzó a proceso/listo.
            $orden->ingreso()->where('estado', '<', 1)->update(['estado' => 1]);

            return $diagnostico;
        });

        $this->auditoria->documento('crear', 'taller', 'diagnosticos', $diagnostico->id,
            "Diagnóstico registrado para la orden {$orden->numero}");

        return redirect()->route('taller.ordenes.show', $orden->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => 'Diagnóstico registrado correctamente.']);
    }

    public function aprobar(Request $request, TallerDiagnostico $diagnostico): RedirectResponse
    {
        $orden = $diagnostico->orden;
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        if ($diagnostico->estado !== 'pendiente') {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'Este diagnóstico ya fue resuelto.']);
        }
        if (!in_array($orden->estado, self::ESTADOS_ABIERTOS, true)) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'La orden ya no admite cambios de diagnóstico.']);
        }

        $data = $request->validate([
            'aprueba'     => 'required|boolean',
            'observacion' => 'nullable|string',
        ]);

        DB::transaction(function () use ($diagnostico, $orden, $data) {
            $diagnostico->cliente_aprueba = $data['aprueba'];
            $diagnostico->fecha_aprobacion = now();
            $diagnostico->observacion_aprobacion = $data['observacion'] ?? null;
            $diagnostico->usuario_aprobacion_id = Auth::id();
            $diagnostico->estado = $data['aprueba'] ? 'aprobado' : 'rechazado';
            $diagnostico->save();

            if ($data['aprueba']) {
                $orden->estado = 'en_proceso';
                $orden->save();
                $orden->ingreso()->update(['estado' => 2]);
            }
        });

        $this->auditoria->documento('editar', 'taller', 'diagnosticos', $diagnostico->id,
            $data['aprueba'] ? 'Diagnóstico aprobado por el cliente' : 'Diagnóstico rechazado por el cliente');

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => 'Diagnóstico actualizado correctamente.']);
    }
}
