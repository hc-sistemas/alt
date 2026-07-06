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
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function create(TallerOrdenTrabajo $orden): Response
    {
        $orden->load(['ingreso.cliente', 'ingreso.equipo']);

        return Inertia::render('Taller/Diagnosticos/Form', [
            'orden' => $orden,
        ]);
    }

    public function store(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        $data = $request->validate([
            'diagnostico'     => 'required|string',
            'tiempo_estimado' => 'nullable|integer',
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

            $orden->ingreso()->update(['estado' => 1]);

            return $diagnostico;
        });

        $this->auditoria->documento('crear', 'taller', 'diagnosticos', $diagnostico->id,
            "Diagnóstico registrado para la orden {$orden->numero}");

        return redirect()->route('taller.ordenes.show', $orden->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => 'Diagnóstico registrado correctamente.']);
    }

    public function aprobar(Request $request, TallerDiagnostico $diagnostico): RedirectResponse
    {
        $data = $request->validate([
            'aprueba'     => 'required|boolean',
            'observacion' => 'nullable|string',
        ]);

        DB::transaction(function () use ($diagnostico, $data) {
            $diagnostico->cliente_aprueba = $data['aprueba'];
            $diagnostico->fecha_aprobacion = now();
            $diagnostico->observacion_aprobacion = $data['observacion'] ?? null;
            $diagnostico->usuario_aprobacion_id = Auth::id();
            $diagnostico->estado = $data['aprueba'] ? 'aprobado' : 'rechazado';
            $diagnostico->save();

            if ($data['aprueba']) {
                $orden = $diagnostico->orden;
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
