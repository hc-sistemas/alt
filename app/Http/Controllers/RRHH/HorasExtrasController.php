<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\HorasExtrasAprobacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class HorasExtrasController extends Controller
{
    // Límites legales Ecuador
    private const MAX_HORAS_DIA    = 4;
    private const MAX_HORAS_SEMANA = 12;

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = HorasExtrasAprobacion::with(['colaborador', 'aprobadoPor'])
            ->whereHas('colaborador', fn($q) => $q->where('empresa_id', $empresaId));

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('colaborador_id')) {
            $query->where('colaborador_id', $request->colaborador_id);
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $extras = $query->orderByDesc('fecha')->paginate(25)->withQueryString();

        $colaboradores = Colaborador::where('empresa_id', $empresaId)
            ->activos()->orderBy('apellidos')->orderBy('nombres')
            ->get(['id', 'cedula_ruc', 'apellidos', 'nombres', 'sueldo_base']);

        return Inertia::render('RRHH/HorasExtras/Index', [
            'extras'        => $extras,
            'colaboradores' => $colaboradores,
            'filtros'       => $request->only(['estado', 'colaborador_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function aprobar(Request $request, HorasExtrasAprobacion $horaExtra): RedirectResponse
    {
        if ($horaExtra->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $data = $request->validate([
            'horas_aprobadas' => 'required|numeric|min:0|max:' . self::MAX_HORAS_DIA,
            'observacion'     => 'nullable|string|max:300',
        ]);

        // Verificar límite semanal
        $inicioSemana = now()->parse($horaExtra->fecha)->startOfWeek();
        $finSemana    = now()->parse($horaExtra->fecha)->endOfWeek();

        $horasEnSemana = HorasExtrasAprobacion::where('colaborador_id', $horaExtra->colaborador_id)
            ->where('estado', 'aprobado')
            ->whereBetween('fecha', [$inicioSemana, $finSemana])
            ->sum('horas_aprobadas');

        if (($horasEnSemana + $data['horas_aprobadas']) > self::MAX_HORAS_SEMANA) {
            $disponibles = max(0, self::MAX_HORAS_SEMANA - $horasEnSemana);
            return back()->with('error',
                "Límite semanal (12h) excedido. Máximo aprobable esta semana: {$disponibles}h.");
        }

        // Recalcular valor con horas aprobadas reales
        $colaborador  = $horaExtra->colaborador;
        $valorHora    = $colaborador->sueldo_base / 240;
        $factor       = $horaExtra->tipo === 'extraordinaria' ? 2.0 : 1.5;
        $valorReal    = round($data['horas_aprobadas'] * $valorHora * $factor, 2);

        $horaExtra->update([
            'horas_aprobadas'  => $data['horas_aprobadas'],
            'valor_calculado'  => $valorReal,
            'estado'           => 'aprobado',
            'aprobado_por'     => Auth::id(),
            'fecha_aprobacion' => now(),
            'observacion'      => $data['observacion'],
        ]);

        return back()->with('success', "Horas extras aprobadas: {$data['horas_aprobadas']}h — \${$valorReal}.");
    }

    public function rechazar(Request $request, HorasExtrasAprobacion $horaExtra): RedirectResponse
    {
        if ($horaExtra->estado !== 'pendiente') {
            return back()->with('error', 'Esta solicitud ya fue procesada.');
        }

        $data = $request->validate([
            'observacion' => 'required|string|min:5|max:300',
        ]);

        $horaExtra->update([
            'estado'           => 'rechazado',
            'aprobado_por'     => Auth::id(),
            'fecha_aprobacion' => now(),
            'observacion'      => $data['observacion'],
        ]);

        return back()->with('success', 'Solicitud rechazada.');
    }
}
