<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\InventarioSaldo;
use App\Models\Producto;
use App\Models\TrasladoBodega;
use App\Models\TrasladoDetalle;
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

        $traslados = TrasladoBodega::with(['bodegaOrigen', 'bodegaDestino', 'enviadoPor', 'detalles'])
            ->where('empresa_id', $empresaId)
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
            ->when($request->bodega_origen_id, fn($q) => $q->where('bodega_origen_id', $request->bodega_origen_id))
            ->when($request->bodega_destino_id, fn($q) => $q->where('bodega_destino_id', $request->bodega_destino_id))
            ->when($request->fecha_desde, fn($q) => $q->where('fecha', '>=', $request->fecha_desde))
            ->when($request->fecha_hasta, fn($q) => $q->where('fecha', '<=', $request->fecha_hasta))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('estado', true)
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
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo']),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'bodega_origen_id'             => ['required', 'integer', 'exists:bodegas,id', 'different:bodega_destino_id'],
            'bodega_destino_id'            => ['required', 'integer', 'exists:bodegas,id'],
            'detalles'                     => ['required', 'array', 'min:1'],
            'detalles.*.producto_id'       => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad_enviada'  => ['required', 'integer', 'min:1'],
            'observacion'                  => ['nullable', 'string'],
        ], [
            'bodega_origen_id.different' => 'La bodega origen y destino no pueden ser la misma.',
        ]);

        Bodega::where('id', $data['bodega_origen_id'])->where('empresa_id', $empresaId)->firstOrFail();
        Bodega::where('id', $data['bodega_destino_id'])->where('empresa_id', $empresaId)->firstOrFail();

        try {
            DB::transaction(function () use ($data, $empresaId) {
                $traslado = TrasladoBodega::create([
                    'empresa_id'        => $empresaId,
                    'bodega_origen_id'  => $data['bodega_origen_id'],
                    'bodega_destino_id' => $data['bodega_destino_id'],
                    'estado'            => 'pendiente',
                    'enviado_por'       => Auth::id(),
                    'fecha'             => now()->toDateString(),
                    'observacion'       => $data['observacion'] ?? null,
                ]);

                $traslado->update(['numero' => 'TRA-' . str_pad($traslado->id, 6, '0', STR_PAD_LEFT)]);

                foreach ($data['detalles'] as $detalleData) {
                    $disponible = $this->inventario->getSaldoDisponible(
                        (int) $detalleData['producto_id'],
                        (int) $data['bodega_origen_id']
                    );

                    if ($disponible < (float) $detalleData['cantidad_enviada']) {
                        $producto = Producto::find($detalleData['producto_id']);
                        throw new \Exception(
                            "Stock insuficiente para {$producto->nombre}: disponible {$disponible}, solicitado {$detalleData['cantidad_enviada']}"
                        );
                    }

                    $this->inventario->reservarStock(
                        (int) $detalleData['producto_id'],
                        (int) $data['bodega_origen_id'],
                        (float) $detalleData['cantidad_enviada']
                    );

                    TrasladoDetalle::create([
                        'traslado_id'      => $traslado->id,
                        'producto_id'      => $detalleData['producto_id'],
                        'cantidad_enviada' => $detalleData['cantidad_enviada'],
                    ]);
                }

                $this->auditoria->documento('crear', 'inventario', 'traslados_bodega', $traslado->id,
                    "Traslado {$traslado->numero} creado: bodega {$traslado->bodega_origen_id} → {$traslado->bodega_destino_id}");
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()->route('inventario.traslados.index')
            ->with('success', 'Traslado creado correctamente.');
    }

    public function show(TrasladoBodega $traslado): Response
    {
        $empresaId = session('empresa_activa_id');

        if ($traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        $traslado->load(['bodegaOrigen', 'bodegaDestino', 'enviadoPor', 'recibidoPor', 'detalles.producto']);

        return Inertia::render('Inventario/Traslados/Show', [
            'traslado' => $traslado,
        ]);
    }

    public function confirmar(Request $request, TrasladoBodega $traslado): RedirectResponse|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        if (!$traslado->isPendiente()) {
            abort(422, 'Traslado no está pendiente.');
        }

        $request->validate([
            'detalles'                     => ['array'],
            'detalles.*.id'                => ['exists:traslado_detalles,id'],
            'detalles.*.cantidad_recibida' => ['numeric', 'min:0'],
            'observacion'                  => ['nullable', 'string'],
        ]);

        try {
            DB::transaction(function () use ($request, $traslado) {
                foreach ($request->detalles ?? [] as $detalleData) {
                    $detalle = TrasladoDetalle::findOrFail($detalleData['id']);

                    $saldo = InventarioSaldo::where('producto_id', $detalle->producto_id)
                        ->where('bodega_id', $traslado->bodega_origen_id)
                        ->first();
                    $costoPromedio = $saldo ? (float) $saldo->costo_promedio : 0.0;

                    $cantidadRecibida = (float) ($detalleData['cantidad_recibida'] ?? $detalle->cantidad_enviada);

                    $this->inventario->egresarStock(
                        (int) $detalle->producto_id,
                        (int) $traslado->bodega_origen_id,
                        (float) $detalle->cantidad_enviada,
                        'traslado',
                        $traslado->id
                    );

                    if ($cantidadRecibida > 0) {
                        $this->inventario->ingresarStock(
                            (int) $detalle->producto_id,
                            (int) $traslado->bodega_destino_id,
                            $cantidadRecibida,
                            $costoPromedio,
                            'traslado',
                            $traslado->id
                        );
                    }

                    $detalle->update(['cantidad_recibida' => $cantidadRecibida]);
                }

                $traslado->update([
                    'estado'          => 'aceptado',
                    'recibido_por'    => Auth::id(),
                    'fecha_recepcion' => now()->toDateString(),
                    'observacion'     => $request->observacion,
                ]);

                $this->auditoria->documento('confirmar', 'inventario', 'traslados_bodega', $traslado->id,
                    "Traslado {$traslado->numero} aceptado por usuario " . Auth::id());
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()->route('inventario.traslados.show', $traslado)
            ->with('success', 'Traslado aceptado correctamente.');
    }

    public function anular(Request $request, TrasladoBodega $traslado): RedirectResponse|JsonResponse
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
                $traslado->load('detalles');

                foreach ($traslado->detalles as $detalle) {
                    $this->inventario->liberarReserva(
                        (int) $detalle->producto_id,
                        (int) $traslado->bodega_origen_id,
                        (float) $detalle->cantidad_enviada
                    );
                }

                $traslado->update([
                    'estado'     => 'rechazado',
                    'observacion' => $request->motivo,
                ]);

                $this->auditoria->documento('anular', 'inventario', 'traslados_bodega', $traslado->id,
                    "Traslado {$traslado->numero} rechazado: {$request->motivo}");
            });
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return redirect()->route('inventario.traslados.index')
            ->with('success', 'Traslado rechazado correctamente.');
    }
}
