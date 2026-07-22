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
        $categorias = CategoriaProducto::with('hijos')
            ->raices()
            ->orderBy('nombre')
            ->get();

        $todasCategorias = CategoriaProducto::whereNull('categoria_padre_id')
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
            'nombre'             => ['required', 'string', 'max:150'],
            'categoria_padre_id' => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'estado'             => ['boolean'],
        ]);

        $categoria = CategoriaProducto::create($data);

        $this->auditoria->documento('crear', 'inventario', 'categorias_producto', $categoria->id,
            "Categoría {$categoria->nombre} creada");

        return back()->with('success', 'Categoría creada correctamente.');
    }

    public function update(Request $request, CategoriaProducto $categoria): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'nombre'             => ['required', 'string', 'max:150'],
            'categoria_padre_id' => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'estado'             => ['boolean'],
        ]);

        if (!empty($data['categoria_padre_id'])) {
            if ($data['categoria_padre_id'] === $categoria->id) {
                return response()->json(['message' => 'Una categoría no puede ser su propio padre.'], 422);
            }
            $esHijo = $categoria->hijos()->where('id', $data['categoria_padre_id'])->exists();
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
        if ($categoria->hijos()->exists()) {
            $count = $categoria->hijos()->count();
            return response()->json([
                'message' => "No se puede eliminar: tiene {$count} subcategoría(s) asociada(s)",
            ], 422);
        }

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
