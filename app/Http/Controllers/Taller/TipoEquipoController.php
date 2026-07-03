<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerTipoEquipo;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoEquipoController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $query = TallerTipoEquipo::query()
            ->when($request->search, fn($q) => $q->where('descripcion', 'ilike', "%{$request->search}%"))
            ->orderBy('descripcion');

        return Inertia::render('Taller/TiposEquipo/Index', [
            'tiposEquipo' => $query->paginate(15)->withQueryString(),
            'filters'     => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:100'],
            'estado'      => ['boolean'],
        ]);

        $tipoEquipo = TallerTipoEquipo::create($data);

        $this->auditoria->documento('crear', 'taller', 'tipos_equipo', $tipoEquipo->id,
            "Tipo de equipo {$tipoEquipo->descripcion} creado");

        return back()->with('success', 'Tipo de equipo creado correctamente.');
    }

    public function update(Request $request, TallerTipoEquipo $tipoEquipo): RedirectResponse
    {
        $data = $request->validate([
            'descripcion' => ['required', 'string', 'max:100'],
            'estado'      => ['boolean'],
        ]);

        $tipoEquipo->update($data);

        $this->auditoria->documento('editar', 'taller', 'tipos_equipo', $tipoEquipo->id,
            "Tipo de equipo {$tipoEquipo->descripcion} actualizado");

        return back()->with('success', 'Tipo de equipo actualizado correctamente.');
    }

    public function destroy(TallerTipoEquipo $tipoEquipo): RedirectResponse|JsonResponse
    {
        $total = $tipoEquipo->equipos()->count();
        if ($total > 0) {
            return response()->json([
                'message' => "No se puede eliminar: tiene {$total} equipo(s) asociado(s)",
            ], 422);
        }

        $descripcion = $tipoEquipo->descripcion;
        $id          = $tipoEquipo->id;
        $tipoEquipo->delete();

        $this->auditoria->documento('eliminar', 'taller', 'tipos_equipo', $id,
            "Tipo de equipo {$descripcion} eliminado");

        return back()->with('success', 'Tipo de equipo eliminado correctamente.');
    }
}
