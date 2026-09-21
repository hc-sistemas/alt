<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerTipoEquipo;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /** Reglas comunes: descripción única sin distinguir mayúsculas (el legacy la guardaba en mayúsculas). */
    private function reglas(?int $ignorarId = null): array
    {
        return [
            'descripcion' => [
                'required', 'string', 'max:100',
                function ($attr, $value, $fail) use ($ignorarId) {
                    $existe = TallerTipoEquipo::whereRaw('upper(descripcion) = ?', [mb_strtoupper(trim($value))])
                        ->when($ignorarId, fn($q) => $q->where('id', '!=', $ignorarId))
                        ->exists();
                    if ($existe) {
                        $fail('Ya existe un tipo de equipo con esa descripción.');
                    }
                },
            ],
            'estado' => ['boolean'],
        ];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate($this->reglas());
        $data['descripcion'] = mb_strtoupper(trim($data['descripcion']));

        $tipoEquipo = TallerTipoEquipo::create($data + ['estado' => true]);

        $this->auditoria->documento('crear', 'taller', 'tipos_equipo', $tipoEquipo->id,
            "Tipo de equipo {$tipoEquipo->descripcion} creado");

        // Creación rápida desde el formulario de ingreso (fetch JSON).
        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['id' => $tipoEquipo->id, 'descripcion' => $tipoEquipo->descripcion]);
        }

        return back()->with('success', 'Tipo de equipo creado correctamente.');
    }

    public function update(Request $request, TallerTipoEquipo $tipoEquipo): RedirectResponse
    {
        $data = $request->validate($this->reglas($tipoEquipo->id));
        $data['descripcion'] = mb_strtoupper(trim($data['descripcion']));

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
