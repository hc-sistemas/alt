<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Inventario/Recepciones/Index', [
            'recepciones' => $recepciones,
            'filtros'     => $request->only(['estado', 'fecha_desde', 'fecha_hasta']),
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

    public function buscarProducto(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $codigo = $request->input('codigo');

        if (!$codigo) {
            return response()->json(['encontrado' => false, 'producto' => null]);
        }

        $producto = Producto::where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->where('estado', true)
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
                $detalle = RecepcionDetalle::where('id', $d['id'])
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
