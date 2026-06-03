<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\InventarioMovimiento;
use App\Models\InventarioSaldo;
use App\Models\Producto;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KardexController extends Controller
{
    public function __construct(
        private InventarioServiceInterface $inventario,
        private AuditoriaService $auditoria
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $productoId = $request->integer('producto_id') ?: null;
        $producto   = null;
        $saldosPorBodega = [];
        $movimientos = null;

        if ($productoId) {
            $producto = Producto::where('id', $productoId)
                ->where('empresa_id', $empresaId)
                ->first();

            if ($producto) {
                $saldosPorBodega = InventarioSaldo::with('bodega')
                    ->where('producto_id', $productoId)
                    ->get();

                $movimientos = InventarioMovimiento::with(['bodega', 'usuario'])
                    ->where('producto_id', $productoId)
                    ->when($request->bodega_id, fn($q) => $q->where('bodega_id', $request->bodega_id))
                    ->when($request->fecha_desde, fn($q) => $q->where('created_at', '>=', $request->fecha_desde . ' 00:00:00'))
                    ->when($request->fecha_hasta, fn($q) => $q->where('created_at', '<=', $request->fecha_hasta . ' 23:59:59'))
                    ->when($request->tipo, fn($q) => $q->where('tipo', 'like', "%{$request->tipo}%"))
                    ->orderByDesc('created_at')
                    ->paginate(25)
                    ->withQueryString();
            }
        }

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Kardex/Index', [
            'producto'        => $producto,
            'movimientos'     => $movimientos,
            'saldosPorBodega' => $saldosPorBodega,
            'bodegas'         => $bodegas,
            'filters'         => $request->only(['producto_id', 'bodega_id', 'fecha_desde', 'fecha_hasta', 'tipo']),
        ]);
    }

    public function saldos(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = InventarioSaldo::with(['producto', 'bodega'])
            ->whereHas('producto', fn($q) => $q->where('empresa_id', $empresaId))
            ->when($request->bodega_id, fn($q) => $q->where('bodega_id', $request->bodega_id))
            ->when($request->search, fn($q) => $q->whereHas('producto', fn($p) => $p->where(function ($p) use ($request) {
                $p->where('codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('nombre', 'ilike', "%{$request->search}%");
            })))
            ->when($request->boolean('solo_criticos'), fn($q) => $q->whereHas('producto', fn($p) =>
                $p->whereColumn('inventario_saldos.stock_actual', '<=', 'productos.stock_minimo')
            ))
            ->orderBy(function ($q) {
                // No se puede ordenar directamente con closure, usar join
            });

        // Rehacer con join para poder ordenar correctamente
        $saldos = InventarioSaldo::with(['producto', 'bodega'])
            ->join('productos', 'inventario_saldos.producto_id', '=', 'productos.id')
            ->join('bodegas', 'inventario_saldos.bodega_id', '=', 'bodegas.id')
            ->where('productos.empresa_id', $empresaId)
            ->when($request->bodega_id, fn($q) => $q->where('inventario_saldos.bodega_id', $request->bodega_id))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('productos.codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('productos.nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->boolean('solo_criticos'), fn($q) =>
                $q->whereColumn('inventario_saldos.stock_actual', '<=', 'productos.stock_minimo')
            )
            ->select([
                'inventario_saldos.*',
                'productos.codigo as producto_codigo',
                'productos.nombre as producto_nombre',
                'productos.stock_minimo as producto_stock_minimo',
            ])
            ->orderBy('productos.nombre')
            ->paginate(25)
            ->withQueryString();

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Kardex/Saldos', [
            'saldos'  => $saldos,
            'bodegas' => $bodegas,
            'filters' => $request->only(['search', 'bodega_id', 'solo_criticos']),
        ]);
    }

    public function ajuste(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Kardex/Ajuste', [
            'productos'    => Producto::where('empresa_id', $empresaId)
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'bodegas'      => Bodega::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'productoId'   => $request->integer('producto_id') ?: null,
            'bodegaId'     => $request->integer('bodega_id') ?: null,
        ]);
    }

    public function storeAjuste(Request $request): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'producto_id'    => ['required', 'integer', 'exists:productos,id'],
            'bodega_id'      => ['required', 'integer', 'exists:bodegas,id'],
            'tipo_ajuste'    => ['required', 'in:positivo,negativo'],
            'cantidad'       => ['required', 'integer', 'min:1'],
            'costo_unitario' => ['required_if:tipo_ajuste,positivo', 'nullable', 'numeric', 'min:0'],
            'motivo'         => ['required', 'string', 'max:255'],
        ]);

        // Verificar que el producto pertenece a la empresa activa
        $producto = Producto::where('id', $data['producto_id'])
            ->where('empresa_id', $empresaId)
            ->firstOrFail();

        try {
            if ($data['tipo_ajuste'] === 'positivo') {
                $this->inventario->ingresarStock(
                    (int) $data['producto_id'],
                    (int) $data['bodega_id'],
                    (float) $data['cantidad'],
                    (float) ($data['costo_unitario'] ?? 0),
                    'ajuste',
                    0,
                    $data['motivo']
                );
            } else {
                $this->inventario->egresarStock(
                    (int) $data['producto_id'],
                    (int) $data['bodega_id'],
                    (float) $data['cantidad'],
                    'ajuste',
                    0,
                    $data['motivo']
                );
            }
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $tipoTexto = $data['tipo_ajuste'] === 'positivo' ? 'positivo' : 'negativo';
        $this->auditoria->documento(
            'ajuste',
            'inventario',
            'inventario_movimientos',
            0,
            "Ajuste {$tipoTexto} de {$data['cantidad']} unidades — {$producto->codigo}: {$data['motivo']}"
        );

        return redirect()->route('inventario.kardex.saldos')
            ->with('success', 'Ajuste de inventario registrado correctamente.');
    }

    public function getSaldo(Request $request): JsonResponse
    {
        $productoId = $request->integer('producto_id');
        $bodegaId   = $request->integer('bodega_id');
        $disponible = $this->inventario->getSaldoDisponible($productoId, $bodegaId);

        return response()->json(['disponible' => $disponible]);
    }
}
