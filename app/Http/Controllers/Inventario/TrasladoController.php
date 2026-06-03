<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\InventarioSaldo;
use App\Models\Producto;
use App\Models\Traslado;
use App\Models\TrasladoItem;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TrasladoController extends Controller
{
    public function __construct(
        private InventarioServiceInterface $inventario,
        private AuditoriaService $auditoria
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $traslados = Traslado::with(['bodegaOrigen', 'bodegaDestino', 'usuarioOrigen', 'items'])
            ->where('empresa_id', $empresaId)
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->when($request->bodega_origen_id, fn($q) => $q->where('bodega_origen_id', $request->bodega_origen_id))
            ->when($request->bodega_destino_id, fn($q) => $q->where('bodega_destino_id', $request->bodega_destino_id))
            ->when($request->fecha_desde, fn($q) => $q->where('fecha_traslado', '>=', $request->fecha_desde . ' 00:00:00'))
            ->when($request->fecha_hasta, fn($q) => $q->where('fecha_traslado', '<=', $request->fecha_hasta . ' 23:59:59'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Traslados/Index', [
            'traslados' => $traslados,
            'bodegas'   => $bodegas,
            'filters'   => $request->only(['estado', 'bodega_origen_id', 'bodega_destino_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Traslados/Form', [
            'productos' => Producto::where('empresa_id', $empresaId)
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'bodegas'   => Bodega::where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'bodega_origen_id'              => ['required', 'integer', 'exists:bodegas,id', 'different:bodega_destino_id'],
            'bodega_destino_id'             => ['required', 'integer', 'exists:bodegas,id'],
            'items'                         => ['required', 'array', 'min:1'],
            'items.*.producto_id'           => ['required', 'integer', 'exists:productos,id'],
            'items.*.cantidad_enviada'      => ['required', 'integer', 'min:1'],
            'notas_origen'                  => ['nullable', 'string'],
        ], [
            'bodega_origen_id.different' => 'La bodega origen y destino no pueden ser la misma.',
        ]);

        // Verificar que ambas bodegas pertenecen a la empresa activa
        $bodegaOrigen = Bodega::where('id', $data['bodega_origen_id'])->where('empresa_id', $empresaId)->firstOrFail();
        $bodegaDestino = Bodega::where('id', $data['bodega_destino_id'])->where('empresa_id', $empresaId)->firstOrFail();

        try {
            DB::transaction(function () use ($data, $empresaId) {
                $traslado = Traslado::create([
                    'empresa_id'        => $empresaId,
                    'bodega_origen_id'  => $data['bodega_origen_id'],
                    'bodega_destino_id' => $data['bodega_destino_id'],
                    'estado'            => 'pendiente',
                    'usuario_origen_id' => Auth::id(),
                    'notas_origen'      => $data['notas_origen'] ?? null,
                    'fecha_traslado'    => now(),
                ]);

                foreach ($data['items'] as $itemData) {
                    $disponible = $this->inventario->getSaldoDisponible(
                        (int) $itemData['producto_id'],
                        (int) $data['bodega_origen_id']
                    );

                    if ($disponible < (float) $itemData['cantidad_enviada']) {
                        $producto = Producto::find($itemData['producto_id']);
                        throw new \Exception(
                            "Stock insuficiente para {$producto->nombre}: disponible {$disponible}, solicitado {$itemData['cantidad_enviada']}"
                        );
                    }

                    $this->inventario->reservarStock(
                        (int) $itemData['producto_id'],
                        (int) $data['bodega_origen_id'],
                        (float) $itemData['cantidad_enviada']
                    );

                    TrasladoItem::create([
                        'traslado_id'      => $traslado->id,
                        'producto_id'      => $itemData['producto_id'],
                        'cantidad_enviada' => $itemData['cantidad_enviada'],
                    ]);
                }

                $this->auditoria->documento('crear', 'inventario', 'traslados', $traslado->id,
                    "Traslado #{$traslado->id} creado: bodega origen {$traslado->bodega_origen_id} → destino {$traslado->bodega_destino_id}");
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()->route('inventario.traslados.index')
            ->with('success', 'Traslado creado correctamente.');
    }

    public function show(Traslado $traslado): Response
    {
        $empresaId = session('empresa_activa_id');

        if ($traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        $traslado->load(['bodegaOrigen', 'bodegaDestino', 'usuarioOrigen', 'usuarioDestino', 'items.producto']);

        return Inertia::render('Inventario/Traslados/Show', [
            'traslado' => $traslado,
        ]);
    }

    public function confirmar(Request $request, Traslado $traslado): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        if (!$traslado->isPendiente()) {
            abort(422, 'Traslado no está pendiente.');
        }

        $request->validate([
            'items'                      => ['array'],
            'items.*.id'                 => ['exists:traslado_items,id'],
            'items.*.cantidad_recibida'  => ['numeric', 'min:0'],
            'notas_destino'              => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($request, $traslado) {
                foreach ($request->items ?? [] as $itemData) {
                    $item = TrasladoItem::findOrFail($itemData['id']);

                    // Obtener costo promedio de bodega origen antes de egresar
                    $saldo = InventarioSaldo::where('producto_id', $item->producto_id)
                        ->where('bodega_id', $traslado->bodega_origen_id)
                        ->first();
                    $costoPromedio = $saldo ? (float) $saldo->costo_promedio : 0.0;

                    $cantidadRecibida = (float) ($itemData['cantidad_recibida'] ?? $item->cantidad_enviada);

                    // Egresar de bodega origen (libera reserva + reduce stock)
                    $this->inventario->egresarStock(
                        (int) $item->producto_id,
                        (int) $traslado->bodega_origen_id,
                        (float) $item->cantidad_enviada,
                        'traslado',
                        $traslado->id
                    );

                    // Ingresar en bodega destino
                    if ($cantidadRecibida > 0) {
                        $this->inventario->ingresarStock(
                            (int) $item->producto_id,
                            (int) $traslado->bodega_destino_id,
                            $cantidadRecibida,
                            $costoPromedio,
                            'traslado',
                            $traslado->id
                        );
                    }

                    $item->update([
                        'cantidad_recibida' => $cantidadRecibida,
                        'notas'             => $itemData['notas'] ?? null,
                    ]);
                }

                $traslado->update([
                    'estado'              => 'confirmado',
                    'usuario_destino_id'  => Auth::id(),
                    'fecha_confirmacion'  => now(),
                    'notas_destino'       => $request->notas_destino,
                ]);

                $this->auditoria->documento('confirmar', 'inventario', 'traslados', $traslado->id,
                    "Traslado #{$traslado->id} confirmado por usuario " . Auth::id());
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()->route('inventario.traslados.show', $traslado)
            ->with('success', 'Traslado confirmado correctamente.');
    }

    public function anular(Request $request, Traslado $traslado): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        if (!$traslado->isPendiente()) {
            abort(422, 'Traslado no está pendiente.');
        }

        $request->validate([
            'motivo' => ['required', 'string'],
        ]);

        try {
            DB::transaction(function () use ($request, $traslado) {
                $traslado->load('items');

                foreach ($traslado->items as $item) {
                    $this->inventario->liberarReserva(
                        (int) $item->producto_id,
                        (int) $traslado->bodega_origen_id,
                        (float) $item->cantidad_enviada
                    );
                }

                $traslado->update([
                    'estado'       => 'anulado',
                    'notas_destino' => $request->motivo,
                ]);

                $this->auditoria->documento('anular', 'inventario', 'traslados', $traslado->id,
                    "Traslado #{$traslado->id} anulado: {$request->motivo}");
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()->route('inventario.traslados.index')
            ->with('success', 'Traslado anulado correctamente.');
    }
}
