<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\RecepcionBodega;
use App\Models\RecepcionDetalle;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecepcionController extends Controller
{
    public function __construct(
        private InventarioServiceInterface $inventario,
        private AuditoriaService $auditoria
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = RecepcionBodega::with(['compra.proveedor', 'bodega'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('created_at', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('created_at', '<=', $request->fecha_hasta . ' 23:59:59');
        }

        $recepciones = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Recepciones/Index', [
            'recepciones' => $recepciones,
            'filtros'     => $request->only(['estado', 'fecha_desde', 'fecha_hasta']),
            'bodegas'     => $bodegas,
        ]);
    }

    public function show(RecepcionBodega $recepcion): Response
    {
        $empresaId = session('empresa_activa_id');

        if ($recepcion->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $recepcion->load([
            'compra.proveedor',
            'bodega',
            'detalles.producto',
            'detalles.compraDetalle',
            'recibidoPor',
        ]);

        return Inertia::render('Inventario/Recepciones/Show', [
            'recepcion' => $recepcion,
        ]);
    }

    public function buscarCompra(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $q = $request->input('q', '');

        $compras = Compra::with([
            'proveedor:id,razon_social',
            'detalles' => fn($qb) => $qb->whereNotNull('producto_id')->with('producto:id,codigo,nombre'),
        ])
        ->where('empresa_id', $empresaId)
        ->where('estado', 'activa')
        ->where('num_documento', 'ilike', "%{$q}%")
        ->whereHas('detalles', fn($qb) => $qb->whereNotNull('producto_id'))
        ->orderByDesc('fecha_emision')
        ->limit(10)
        ->get(['id', 'num_documento', 'fecha_emision', 'proveedor_id']);

        return response()->json($compras);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'compra_id'                    => 'required|integer|exists:compras,id',
            'bodega_id'                    => 'required|integer|exists:bodegas,id',
            'detalles'                     => 'required|array|min:1',
            'detalles.*.compra_detalle_id' => 'required|integer|exists:compra_detalles,id',
            'detalles.*.producto_id'       => 'required|integer|exists:productos,id',
            'detalles.*.cantidad_esperada' => 'required|numeric|min:0.0001',
        ]);

        $compra = Compra::where('empresa_id', $empresaId)
            ->where('id', $request->compra_id)
            ->firstOrFail();

        $yaExiste = RecepcionBodega::where('compra_id', $compra->id)
            ->where('bodega_id', $request->bodega_id)
            ->exists();

        if ($yaExiste) {
            return back()->with('error', 'Ya existe una recepción para esta compra en esa bodega.');
        }

        DB::transaction(function () use ($request, $compra, $empresaId) {
            $recepcion = RecepcionBodega::create([
                'empresa_id' => $empresaId,
                'compra_id'  => $compra->id,
                'bodega_id'  => $request->bodega_id,
                'estado'     => 'pendiente',
            ]);

            foreach ($request->detalles as $d) {
                RecepcionDetalle::create([
                    'recepcion_id'      => $recepcion->id,
                    'compra_detalle_id' => $d['compra_detalle_id'],
                    'producto_id'       => $d['producto_id'],
                    'cantidad_esperada' => $d['cantidad_esperada'],
                    'cantidad_recibida' => 0,
                    'estado'            => 'pendiente',
                ]);
            }

            $this->auditoria->documento(
                'crear',
                'inventario',
                'recepciones_bodega',
                $recepcion->id,
                "Recepción manual creada desde compra #{$compra->num_documento}"
            );
        });

        return redirect()->route('inventario.recepciones.index')
            ->with('success', 'Recepción creada correctamente.');
    }

    public function buscarProducto(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $codigo = $request->input('codigo');

        if (!$codigo) {
            return response()->json(['encontrado' => false, 'producto' => null]);
        }

        $producto = Producto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->where(fn($q) => $q->where('codigo', $codigo)->orWhere('codigo_externo', $codigo))
            ->first(['id', 'codigo', 'nombre', 'unidad']);

        return response()->json([
            'encontrado' => $producto !== null,
            'producto'   => $producto,
        ]);
    }

    public function confirmar(Request $request, RecepcionBodega $recepcion): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($recepcion->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        if (!$recepcion->isPendiente()) {
            return response()->json(['message' => 'Esta recepción ya fue procesada.'], 422);
        }

        $request->validate([
            'detalles'                    => 'required|array|min:1',
            'detalles.*.id'               => 'required|integer|exists:recepcion_detalles,id',
            'detalles.*.cantidad_recibida' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $recepcion) {
            $todosCompletados = true;
            $algunoParcial    = false;

            foreach ($request->detalles as $d) {
                $detalle = RecepcionDetalle::with('compraDetalle')
                    ->where('id', $d['id'])
                    ->where('recepcion_id', $recepcion->id)
                    ->firstOrFail();

                $cantRecibida = (float) $d['cantidad_recibida'];
                $cantEsperada = (float) $detalle->cantidad_esperada;

                if ($cantRecibida >= $cantEsperada) {
                    $estadoDetalle = 'completado';
                } elseif ($cantRecibida > 0) {
                    $estadoDetalle = 'parcial';
                    $todosCompletados = false;
                    $algunoParcial = true;
                } else {
                    $estadoDetalle = 'pendiente';
                    $todosCompletados = false;
                }

                $detalle->update([
                    'cantidad_recibida' => $cantRecibida,
                    'estado'            => $estadoDetalle,
                ]);

                if ($cantRecibida > 0) {
                    $costo = (float) ($detalle->compraDetalle->precio_unitario ?? 0);
                    $this->inventario->ingresarStock(
                        $detalle->producto_id,
                        $recepcion->bodega_id,
                        $cantRecibida,
                        $costo,
                        'compra',
                        $recepcion->compra_id
                    );
                }
            }

            $estadoRecepcion = $todosCompletados ? 'completada' : ($algunoParcial ? 'parcial' : 'pendiente');

            $recepcion->update([
                'estado'          => $estadoRecepcion,
                'recibido_por'    => Auth::id(),
                'fecha_recepcion' => now()->toDateString(),
            ]);

            if ($estadoRecepcion === 'completada') {
                $recepcion->compra->update(['estado' => 'activa']);
            }

            $this->auditoria->documento(
                'confirmar',
                'inventario',
                'recepciones_bodega',
                $recepcion->id,
                "Recepción #{$recepcion->id} confirmada — estado: {$estadoRecepcion}"
            );
        });

        return response()->json(['ok' => true, 'message' => 'Recepción confirmada correctamente.']);
    }
}
