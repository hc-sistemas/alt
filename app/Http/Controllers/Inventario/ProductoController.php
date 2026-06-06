<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
use App\Models\InventarioSaldo;
use App\Models\Marca;
use App\Models\PlanCuenta;
use App\Models\Producto;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = Producto::with(['marca', 'categoria'])
            ->where('empresa_id', $empresaId)
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->marca_id, fn($q) => $q->where('marca_id', $request->marca_id))
            ->when($request->categoria_id, fn($q) => $q->where('categoria_id', $request->categoria_id))
            ->when($request->tipo, fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->estado !== null && $request->estado !== '', fn($q) =>
                $q->where('estado', $request->estado === 'activo')
            )
            ->orderBy('nombre');

        return Inertia::render('Inventario/Productos/Index', [
            'productos'  => $query->paginate(20)->withQueryString(),
            'filters'    => $request->only(['search', 'marca_id', 'categoria_id', 'tipo', 'estado']),
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id']),
        ]);
    }

    public function create(): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Productos/Form', [
            'producto'   => null,
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id']),
            'bodegas'    => Bodega::where('empresa_id', $empresaId)->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'cuentas'    => PlanCuenta::where('empresa_id', $empresaId)
                                ->where('permite_asientos', true)
                                ->where('estado', true)
                                ->orderBy('codigo')
                                ->get(['id', 'codigo', 'descripcion']),
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $query     = $request->string('q')->trim();

        if ($query->isEmpty()) {
            return response()->json(['resultados' => []]);
        }

        $productos = Producto::with('marca')
            ->where('empresa_id', $empresaId)
            ->where('estado', true)
            ->where(function ($q) use ($query) {
                $q->where('codigo', 'ilike', "%{$query}%")
                  ->orWhere('nombre', 'ilike', "%{$query}%")
                  ->orWhereHas('marca', fn($m) =>
                      $m->where('nombre', 'ilike', "%{$query}%")
                  );
            })
            ->limit(15)
            ->get(['id', 'codigo', 'nombre', 'marca_id', 'requiere_serie'])
            ->map(fn($p) => [
                'id'             => $p->id,
                'codigo'         => $p->codigo,
                'nombre'         => $p->nombre,
                'marca'          => $p->marca?->nombre,
                'requiere_serie' => $p->requiere_serie,
            ]);

        return response()->json(['resultados' => $productos]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'codigo'             => ['required', 'string', 'max:50', Rule::unique('productos')->where('empresa_id', $empresaId)],
            'codigo_externo'     => ['nullable', 'string', 'max:100'],
            'nombre'             => ['required', 'string', 'max:255'],
            'descripcion'        => ['nullable', 'string'],
            'tipo'               => ['required', 'in:producto,servicio,repuesto,insumo'],
            'unidad'             => ['required', 'string', 'max:20'],
            'marca_id'           => ['nullable', 'integer', 'exists:marcas,id'],
            'categoria_id'       => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'requiere_serie'     => ['boolean'],
            'pvp'                => ['numeric', 'min:0'],
            'pvd'                => ['numeric', 'min:0'],
            'costo'              => ['numeric', 'min:0'],
            'descuento_maximo'   => ['numeric', 'min:0', 'max:100'],
            'porcentaje_iva'     => ['numeric', 'min:0'],
            'tiene_ice'          => ['boolean'],
            'porcentaje_ice'     => ['numeric', 'min:0'],
            'stock_minimo'       => ['integer', 'min:0'],
            'stock_maximo'       => ['nullable', 'integer', 'min:0'],
            'cuenta_inventario'  => ['nullable', 'string', 'max:20'],
            'cuenta_costo_ventas' => ['nullable', 'string', 'max:20'],
            'cuenta_ventas'      => ['nullable', 'string', 'max:20'],
            'ref_importacion'    => ['nullable', 'string'],
            'estado'             => ['boolean'],
        ]);

        $producto = Producto::create([...$data, 'empresa_id' => $empresaId]);

        $this->auditoria->documento('crear', 'inventario', 'productos', $producto->id,
            "Producto {$producto->codigo} — {$producto->nombre} creado");

        return redirect()->route('inventario.productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function edit(Producto $producto): Response
    {
        $empresaId = session('empresa_activa_id');

        if ($producto->empresa_id !== $empresaId) {
            abort(403);
        }

        return Inertia::render('Inventario/Productos/Form', [
            'producto'   => $producto,
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id']),
            'bodegas'    => Bodega::where('empresa_id', $empresaId)->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'cuentas'    => PlanCuenta::where('empresa_id', $empresaId)
                                ->where('permite_asientos', true)
                                ->where('estado', true)
                                ->orderBy('codigo')
                                ->get(['id', 'codigo', 'descripcion']),
        ]);
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($producto->empresa_id !== $empresaId) {
            abort(403);
        }

        $data = $request->validate([
            'codigo'             => ['required', 'string', 'max:50', Rule::unique('productos')->where('empresa_id', $empresaId)->ignore($producto->id)],
            'codigo_externo'     => ['nullable', 'string', 'max:100'],
            'nombre'             => ['required', 'string', 'max:255'],
            'descripcion'        => ['nullable', 'string'],
            'tipo'               => ['required', 'in:producto,servicio,repuesto,insumo'],
            'unidad'             => ['required', 'string', 'max:20'],
            'marca_id'           => ['nullable', 'integer', 'exists:marcas,id'],
            'categoria_id'       => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'requiere_serie'     => ['boolean'],
            'pvp'                => ['numeric', 'min:0'],
            'pvd'                => ['numeric', 'min:0'],
            'costo'              => ['numeric', 'min:0'],
            'descuento_maximo'   => ['numeric', 'min:0', 'max:100'],
            'porcentaje_iva'     => ['numeric', 'min:0'],
            'tiene_ice'          => ['boolean'],
            'porcentaje_ice'     => ['numeric', 'min:0'],
            'stock_minimo'       => ['integer', 'min:0'],
            'stock_maximo'       => ['nullable', 'integer', 'min:0'],
            'cuenta_inventario'  => ['nullable', 'string', 'max:20'],
            'cuenta_costo_ventas' => ['nullable', 'string', 'max:20'],
            'cuenta_ventas'      => ['nullable', 'string', 'max:20'],
            'ref_importacion'    => ['nullable', 'string'],
            'estado'             => ['boolean'],
        ]);

        $producto->update($data);

        $this->auditoria->documento('editar', 'inventario', 'productos', $producto->id,
            "Producto {$producto->codigo} — {$producto->nombre} actualizado");

        return redirect()->route('inventario.productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($producto->empresa_id !== $empresaId) {
            abort(403);
        }

        $saldo = InventarioSaldo::where('producto_id', $producto->id)
            ->where('cantidad', '>', 0)
            ->exists();

        if ($saldo) {
            return response()->json([
                'message' => 'No se puede eliminar: el producto tiene stock registrado',
            ], 422);
        }

        if ($producto->series()->where('estado', '!=', 'vendido')->exists()) {
            $count = $producto->series()->where('estado', '!=', 'vendido')->count();
            return response()->json([
                'message' => "No se puede eliminar: tiene {$count} número(s) de serie activo(s)",
            ], 422);
        }

        $codigo = $producto->codigo;
        $id     = $producto->id;
        $producto->delete();

        $this->auditoria->documento('eliminar', 'inventario', 'productos', $id,
            "Producto {$codigo} eliminado");

        return back()->with('success', 'Producto eliminado correctamente.');
    }
}
