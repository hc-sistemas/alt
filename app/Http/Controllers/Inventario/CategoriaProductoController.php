<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class CategoriaProductoController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(): Response
    {
        // Árbol completo con hijos eager loaded
        $categorias = CategoriaProducto::with('hijos')
            ->raices()
            ->orderBy('nombre')
            ->get();

        // Lista plana para el selector de padre
        $todasCategorias = CategoriaProducto::whereNull('parent_id')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Configuracion/Categorias/Index', [
            'categorias'      => $categorias,
            'todasCategorias' => $todasCategorias,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'activo'      => ['boolean'],
        ]);

        $categoria = CategoriaProducto::create($data);

        $this->auditoria->documento('crear', 'inventario', 'categorias_producto', $categoria->id,
            "Categoría {$categoria->nombre} creada");

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, CategoriaProducto $categoria): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'nombre'      => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string'],
            'parent_id'   => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'activo'      => ['boolean'],
        ]);

        // Prevenir ciclos: parent_id no puede ser la misma categoría ni un hijo de ella
        if (!empty($data['parent_id'])) {
            if ($data['parent_id'] === $categoria->id) {
                return response()->json(['message' => 'Una categoría no puede ser su propio padre.'], 422);
            }
            $esHijo = $categoria->hijos()->where('id', $data['parent_id'])->exists();
            if ($esHijo) {
                return response()->json(['message' => 'No se puede asignar un hijo como padre (ciclo detectado).'], 422);
            }
        }

        $categoria->update($data);

        $this->auditoria->documento('editar', 'inventario', 'categorias_producto', $categoria->id,
            "Categoría {$categoria->nombre} actualizada");

        return back()->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(CategoriaProducto $categoria): RedirectResponse|JsonResponse
    {
        // Bloquear si tiene hijos
        if ($categoria->hijos()->exists()) {
            $count = $categoria->hijos()->count();
            return response()->json([
                'message' => "No se puede eliminar: tiene {$count} subcategoría(s) asociada(s)",
            ], 422);
        }

        // Bloquear si tiene productos asociados
        if (Schema::hasTable('productos')) {
            $total = \DB::table('productos')->where('categoria_id', $categoria->id)->count();
            if ($total > 0) {
                return response()->json([
                    'message' => "No se puede eliminar: tiene {$total} producto(s) asociado(s)",
                ], 422);
            }
        }

        $nombre = $categoria->nombre;
        $id     = $categoria->id;
        $categoria->delete();

        $this->auditoria->documento('eliminar', 'inventario', 'categorias_producto', $id,
            "Categoría {$nombre} eliminada");

        return back()->with('success', 'Categoría eliminada correctamente.');
    }
}
