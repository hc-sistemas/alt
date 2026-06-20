<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Marca;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class MarcaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $query = Marca::query()
            ->when($request->search, fn($q) => $q->where('nombre', 'ilike', "%{$request->search}%"))
            ->orderBy('nombre');

        return Inertia::render('Inventario/Configuracion/Marcas/Index', [
            'marcas'  => $query->paginate(15)->withQueryString(),
            'filters' => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'logo'   => ['nullable', 'string'],
            'icono'  => ['nullable', 'string'],
            'estado' => ['boolean'],
        ]);

        $marca = Marca::create($data);

        $this->auditoria->documento('crear', 'inventario', 'marcas', $marca->id,
            "Marca {$marca->nombre} creada");

        return back()->with('success', 'Marca creada correctamente.');
    }

    public function update(Request $request, Marca $marca): RedirectResponse
    {
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'logo'   => ['nullable', 'string'],
            'icono'  => ['nullable', 'string'],
            'estado' => ['boolean'],
        ]);

        $marca->update($data);

        $this->auditoria->documento('editar', 'inventario', 'marcas', $marca->id,
            "Marca {$marca->nombre} actualizada");

        return back()->with('success', 'Marca actualizada correctamente.');
    }

    public function destroy(Marca $marca): RedirectResponse|JsonResponse
    {
        if (Schema::hasTable('productos')) {
            $total = \DB::table('productos')->where('marca_id', $marca->id)->count();
            if ($total > 0) {
                return response()->json([
                    'message' => "No se puede eliminar: tiene {$total} producto(s) asociado(s)",
                ], 422);
            }
        }

        $nombre = $marca->nombre;
        $id     = $marca->id;
        $marca->delete();

        $this->auditoria->documento('eliminar', 'inventario', 'marcas', $id,
            "Marca {$nombre} eliminada");

        return back()->with('success', 'Marca eliminada correctamente.');
    }
}
