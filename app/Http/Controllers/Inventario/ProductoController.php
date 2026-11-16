<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
use App\Models\Empresa;
use App\Models\InventarioSaldo;
use App\Models\Marca;
use App\Models\PlanCuenta;
use App\Models\Producto;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    /**
     * El catálogo de productos es compartido entre empresas (el inventario/stock
     * sí es independiente por empresa, vía bodegas). La columna empresa_id de
     * productos no admite null por ser una tabla legacy, así que todo producto
     * nuevo se ancla a esta empresa como dueña nominal del catálogo.
     */
    private const CATALOGO_EMPRESA_ID = 1;

    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $busquedaRealizada = $request->boolean('buscado');

        $productos = null;

        // Categorías (o subcategorías) que ya tienen cuentas contables: un producto en otra categoría necesita subcategoría
        $conCuentas = DB::table('subcategoria_cuentas')->where('estado', true)->pluck('categoria_producto_id')->map(fn ($i) => (int) $i)->all();

        if ($busquedaRealizada) {
            $productos = $this->consultaFiltrada($request, $conCuentas)
                ->paginate(20)
                ->withQueryString()
                ->through(fn (Producto $p) => $this->conClasificacion($p, $conCuentas));
        }

        return Inertia::render('Inventario/Productos/Index', [
            'pendientes_total' => Producto::where('tipo', '!=', 'servicio')->whereNotIn('categoria_id', $conCuentas ?: [0])->count(),
            'productos'  => $productos,
            'filters'    => $request->only(['search', 'marca_id', 'categoria_id', 'tipo', 'estado']),
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id', 'requiere_serie']),
        ]);
    }

    /**
     * Productos según los filtros de la pantalla (texto, categoría, tipo, estado). La usan el listado (paginado) y la exportación
     * a Excel (todo lo filtrado), para que ambos muestren exactamente lo mismo.
     *
     * @param  array<int,int> $conCuentas categorías que ya tienen cuentas contables
     */
    private function consultaFiltrada(Request $request, array $conCuentas)
    {
        return Producto::with(['marca', 'categoria.padre'])
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->marca_id, fn($q) => $q->where('marca_id', $request->marca_id))
            // «pendientes»: productos sin subcategoría con cuentas (no se pueden facturar ni comprar). Una categoría con
            // subcategorías incluye también a sus subcategorías.
            ->when($request->categoria_id === 'pendientes', fn($q) => $q->where('tipo', '!=', 'servicio')->whereNotIn('categoria_id', $conCuentas ?: [0]))
            ->when($request->categoria_id && $request->categoria_id !== 'pendientes', function ($q) use ($request) {
                $ids = CategoriaProducto::where('id', $request->categoria_id)->orWhere('categoria_padre_id', $request->categoria_id)->pluck('id');
                $q->whereIn('categoria_id', $ids);
            })
            ->when($request->tipo, fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->estado !== null && $request->estado !== '', fn($q) =>
                $q->where('estado', $request->estado === 'activo')
            )
            ->orderBy('nombre');
    }

    /** Agrega categoría y subcategoría separadas, y si el producto tiene cuentas contables (PC-CAT). */
    private function conClasificacion(Producto $p, array $conCuentas): Producto
    {
        $cat = $p->categoria;
        // Con subcategoría: la categoría es el padre y la subcategoría la propia categoría del producto
        $p->setAttribute('categoria_nombre', $cat?->padre?->nombre ?? $cat?->nombre);
        $p->setAttribute('subcategoria_nombre', $cat?->padre ? $cat->nombre : null);
        $p->setAttribute('clasificado', $p->tipo === 'servicio' || in_array((int) $p->categoria_id, $conCuentas, true));

        return $p;
    }

    /** Excel con TODO lo que coincide con los filtros (no solo la página que se ve). */
    public function excel(Request $request)
    {
        $conCuentas = DB::table('subcategoria_cuentas')->where('estado', true)->pluck('categoria_producto_id')->map(fn ($i) => (int) $i)->all();
        $filas = $this->consultaFiltrada($request, $conCuentas)->get()
            ->map(fn (Producto $p) => $this->conClasificacion($p, $conCuentas))
            ->map(fn (Producto $p) => [
                $p->codigo, $p->nombre, $p->marca?->nombre, $p->marca_fabricante, $p->getAttribute('categoria_nombre'),
                $p->getAttribute('subcategoria_nombre') ?? ($p->getAttribute('clasificado') ? '' : 'FALTA'),
                $p->tipo, (float) $p->pvp, (float) $p->pvd, (float) $p->costo, $p->estado ? 'Activo' : 'Inactivo',
            ]);

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ProductosExport($filas),
            'productos_' . now()->format('Ymd_His') . '.xlsx'
        );
    }

    public function create(): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Productos/Form', [
            'producto'   => null,
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id', 'requiere_serie']),
            'bodegas'    => Bodega::where('empresa_id', $empresaId)->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'cuentas'    => PlanCuenta::whereNull('empresa_id')
                ->where('permite_asientos', true)
                ->where('estado', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentasCategoria' => $this->cuentasPorCategoria(),
        ]);
    }

    public function buscar(Request $request): JsonResponse
    {
        $query = $request->string('q')->trim();

        if ($query->isEmpty()) {
            return response()->json(['resultados' => []]);
        }

        $productos = Producto::with('marca')
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

    /** Ficha completa del producto (modal de solo lectura en Inventario General). */
    public function ficha(Producto $producto): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $producto->load(['marca:id,nombre', 'categoria:id,nombre']);

        $stock = InventarioSaldo::join('bodegas', 'inventario_saldos.bodega_id', '=', 'bodegas.id')
            ->where('inventario_saldos.producto_id', $producto->id)
            ->where('bodegas.empresa_id', $empresaId)
            ->orderBy('bodegas.nombre')
            ->get(['bodegas.nombre as bodega', 'inventario_saldos.stock_actual', 'inventario_saldos.costo_promedio'])
            ->map(fn($s) => [
                'bodega'         => $s->bodega,
                'stock_actual'   => (float) $s->stock_actual,
                'costo_promedio' => (float) $s->costo_promedio,
            ]);

        $codigos = array_filter([$producto->cuenta_inventario, $producto->cuenta_costo_ventas, $producto->cuenta_ventas]);
        $nombres = PlanCuenta::whereIn('codigo', $codigos)->pluck('nombre', 'codigo');
        $cuenta  = fn($c) => $c ? trim($c . ' — ' . ($nombres[$c] ?? '')) : null;

        return response()->json([
            'id'               => $producto->id,
            'codigo'           => $producto->codigo,
            'codigo_externo'   => $producto->codigo_externo,
            'nombre'           => $producto->nombre,
            'descripcion'      => $producto->descripcion,
            'tipo'             => $producto->tipo,
            'unidad'           => $producto->unidad,
            'marca'            => $producto->marca?->nombre,
            'marca_fabricante' => $producto->marca_fabricante,
            'categoria'        => $producto->categoria?->nombre,
            'requiere_serie'   => (bool) $producto->requiere_serie,
            'peso'             => $producto->peso,
            'costo'            => $producto->costo,
            'pvp'              => $producto->pvp,
            'pvd'              => $producto->pvd,
            'descuento_maximo' => $producto->descuento_maximo,
            'porcentaje_iva'   => $producto->porcentaje_iva,
            'tiene_ice'        => (bool) $producto->tiene_ice,
            'porcentaje_ice'   => $producto->porcentaje_ice,
            'stock_minimo'     => $producto->stock_minimo,
            'stock_maximo'     => $producto->stock_maximo,
            'ref_importacion'  => $producto->ref_importacion,
            'estado'           => (bool) $producto->estado,
            'cuenta_inventario'   => $cuenta($producto->cuenta_inventario),
            'cuenta_costo_ventas' => $cuenta($producto->cuenta_costo_ventas),
            'cuenta_ventas'       => $cuenta($producto->cuenta_ventas),
            'stock'            => $stock,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'codigo'             => ['required', 'string', 'max:50', Rule::unique('productos', 'codigo')],
            'codigo_externo'     => ['nullable', 'string', 'max:100'],
            'nombre'             => ['required', 'string', 'max:255'],
            'descripcion'        => ['nullable', 'string'],
            'tipo'               => ['required', 'in:producto,servicio,repuesto,insumo'],
            'unidad'             => ['required', 'string', 'max:20'],
            'marca_id'           => ['nullable', 'integer', 'exists:marcas,id'],
            'marca_fabricante'   => ['nullable', 'string', 'max:150'],
            'categoria_id'       => ['required', 'integer', 'exists:categorias_producto,id'],
            'requiere_serie'     => ['boolean'],
            'pvp'                => ['numeric', 'min:0'],
            'pvd'                => ['numeric', 'min:0'],
            'costo'              => ['numeric', 'min:0'],
            'porcentaje_iva'     => ['numeric', 'min:0'],
            'tiene_ice'          => ['boolean'],
            'porcentaje_ice'     => ['numeric', 'min:0'],
            'stock_minimo'       => ['nullable', 'integer', 'min:0'],
            'stock_maximo'       => ['nullable', 'integer', 'min:0'],
            'peso'               => ['nullable', 'numeric', 'min:0'],
            'cuenta_inventario'  => ['nullable', 'string', 'max:20'],
            'cuenta_costo_ventas' => ['nullable', 'string', 'max:20'],
            'cuenta_ventas'      => ['nullable', 'string', 'max:20'],
            'ref_importacion'    => ['nullable', 'string'],
            'estado'             => ['boolean'],
        ]);

        $this->exigirSubcategoria((int) $data['categoria_id']);
        $data = $this->aplicarSerieDeCategoria($data);

        $data['stock_minimo'] = $data['stock_minimo'] ?? 0;
        $data['stock_maximo'] = $data['stock_maximo'] ?? 0;

        $producto = Producto::create([...$data, 'empresa_id' => self::CATALOGO_EMPRESA_ID]);

        $this->auditoria->documento('crear', 'inventario', 'productos', $producto->id,
            "Producto {$producto->codigo} — {$producto->nombre} creado");

        return redirect()->route('inventario.productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function edit(Producto $producto): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Productos/Form', [
            'producto'   => $producto,
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'categoria_padre_id', 'requiere_serie']),
            'bodegas'    => Bodega::where('empresa_id', $empresaId)->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'cuentas' => PlanCuenta::whereNull('empresa_id')
                ->where('permite_asientos', true)
                ->where('estado', true)
                ->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre']),
            'cuentasCategoria' => $this->cuentasPorCategoria(),
        ]);
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $data = $request->validate([
            'codigo'             => ['required', 'string', 'max:50', Rule::unique('productos', 'codigo')->ignore($producto->id)],
            'codigo_externo'     => ['nullable', 'string', 'max:100'],
            'nombre'             => ['required', 'string', 'max:255'],
            'descripcion'        => ['nullable', 'string'],
            'tipo'               => ['required', 'in:producto,servicio,repuesto,insumo'],
            'unidad'             => ['required', 'string', 'max:20'],
            'marca_id'           => ['nullable', 'integer', 'exists:marcas,id'],
            'marca_fabricante'   => ['nullable', 'string', 'max:150'],
            'categoria_id'       => ['nullable', 'integer', 'exists:categorias_producto,id'],
            'requiere_serie'     => ['boolean'],
            'pvp'                => ['numeric', 'min:0'],
            'pvd'                => ['numeric', 'min:0'],
            'costo'              => ['numeric', 'min:0'],
            'porcentaje_iva'     => ['numeric', 'min:0'],
            'tiene_ice'          => ['boolean'],
            'porcentaje_ice'     => ['numeric', 'min:0'],
            'stock_minimo'       => ['nullable', 'integer', 'min:0'],
            'stock_maximo'       => ['nullable', 'integer', 'min:0'],
            'peso'               => ['nullable', 'numeric', 'min:0'],
            'cuenta_inventario'  => ['nullable', 'string', 'max:20'],
            'cuenta_costo_ventas' => ['nullable', 'string', 'max:20'],
            'cuenta_ventas'      => ['nullable', 'string', 'max:20'],
            'ref_importacion'    => ['nullable', 'string'],
            'estado'             => ['boolean'],
        ]);

        if (empty($data['categoria_id'])) {
            throw ValidationException::withMessages(['categoria_id' => 'Todo producto debe tener categoría.']);
        }
        if ((int) $data['categoria_id'] !== (int) $producto->categoria_id) {
            $this->exigirSubcategoria((int) $data['categoria_id']);
        }
        $data = $this->aplicarSerieDeCategoria($data);

        // Un servicio no mueve inventario: si se convierte con stock, esas unidades quedarían fuera de toda contabilización.
        if ($data['tipo'] === 'servicio' && $producto->tipo !== 'servicio'
            && InventarioSaldo::where('producto_id', $producto->id)->where('stock_actual', '>', 0)->exists()) {
            throw ValidationException::withMessages(['tipo' => 'El producto tiene stock en bodega: no puede convertirse en servicio. Dé de baja el stock primero.']);
        }

        $data['stock_minimo'] = $data['stock_minimo'] ?? 0;
        $data['stock_maximo'] = $data['stock_maximo'] ?? 0;

        $producto->update($data);

        $this->auditoria->documento('editar', 'inventario', 'productos', $producto->id,
            "Producto {$producto->codigo} — {$producto->nombre} actualizado");

        return redirect()->route('inventario.productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function reporteLista(Request $request): HttpResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = Empresa::findOrFail($empresaId);

        $productos = Producto::with(['marca', 'categoria'])
            ->orderBy('nombre')
            ->get();

        $pdf = Pdf::loadView('reportes.inventario.productos', [
            'productos' => $productos,
            'empresa'   => $empresa,
            'usuario'   => auth()->user(),
        ])->setPaper('a3', 'landscape');

        return $request->boolean('download')
            ? $pdf->download('productos.pdf')
            : $pdf->stream('productos.pdf');
    }

    public function destroy(Producto $producto): RedirectResponse|JsonResponse
    {
        $saldo = InventarioSaldo::where('producto_id', $producto->id)
            ->where('stock_actual', '>', 0)
            ->exists();

        if ($saldo) {
            return response()->json([
                'message' => 'No se puede eliminar: el producto tiene stock registrado',
            ], 422);
        }

        // Con movimientos de kárdex el producto no se borra (rompería el historial, los documentos y los reportes):
        // se desactiva. Antes el borrado fallaba con un error 500 por las llaves foráneas o dejaba el kárdex huérfano.
        if (DB::table('inventario_movimientos')->where('producto_id', $producto->id)->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar: el producto tiene movimientos de inventario. Desactívelo (estado inactivo) en su lugar.',
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
        try {
            $producto->delete();
        } catch (\Illuminate\Database\QueryException) {
            return response()->json([
                'message' => 'No se puede eliminar: el producto está usado en documentos (facturas, compras, proformas…). Desactívelo en su lugar.',
            ], 422);
        }

        $this->auditoria->documento('eliminar', 'inventario', 'productos', $id,
            "Producto {$codigo} eliminado");

        return back()->with('success', 'Producto eliminado correctamente.');
    }

    /** Si la categoría tiene subcategorías, el producto debe elegir una (ella define las cuentas, 01 §6). */
    private function exigirSubcategoria(int $categoriaId): void
    {
        if (app(\App\Services\CuentasProductoService::class)->tieneSubcategorias($categoriaId)) {
            throw ValidationException::withMessages([
                'categoria_id' => 'Esta categoría tiene subcategorías: elija la subcategoría del producto, porque ella define sus cuentas contables.',
            ]);
        }
    }

    /** Los productos de categorías que exigen número de serie (DJ, computadoras, pantallas LED) lo llevan siempre. */
    private function aplicarSerieDeCategoria(array $data): array
    {
        if (!empty($data['categoria_id']) && CategoriaProducto::where('id', $data['categoria_id'])->where('requiere_serie', true)->exists()) {
            $data['requiere_serie'] = true;
        }

        return $data;
    }

    /**
     * Cuentas contables que el sistema usa para cada categoría o subcategoría (PC-CAT-01 a 20). Solo lectura: se muestran en la
     * pestaña Contabilidad del producto porque son las que de verdad se usan al facturar, comprar y ajustar.
     *
     * @return array<int,array{codigo:string,inventario:string,venta:?string,costo:string}>
     */
    private function cuentasPorCategoria(): array
    {
        $cuentas = PlanCuenta::whereNull('empresa_id')->get(['id', 'codigo', 'nombre'])->keyBy('id');
        $texto = fn (?int $id) => $id && isset($cuentas[$id]) ? $cuentas[$id]->codigo . ' — ' . $cuentas[$id]->nombre : null;

        return DB::table('subcategoria_cuentas')->where('estado', true)->get()
            ->mapWithKeys(fn ($f) => [(int) $f->categoria_producto_id => [
                'codigo'     => $f->codigo,
                'inventario' => $texto((int) $f->cuenta_inventario_id),
                'venta'      => $texto($f->cuenta_venta_id ? (int) $f->cuenta_venta_id : null),
                'costo'      => $texto((int) $f->cuenta_costo_id),
            ]])->all();
    }
}
