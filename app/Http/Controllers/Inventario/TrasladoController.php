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

    public function productosEnBodega(Request $request): JsonResponse
    {
        $bodegaId  = $request->integer('bodega_id');
        $query     = $request->string('q')->trim();

        $resultados = InventarioSaldo::with('producto.marca')
            ->where('bodega_id', $bodegaId)
            ->where('stock_actual', '>', 0)
            ->whereHas('producto', function ($q) use ($query) {
                $q->where('estado', true)
                  ->when($query->isNotEmpty(), fn($q) =>
                      $q->where(function ($q) use ($query) {
                          $q->where('codigo', 'ilike', "%{$query}%")
                            ->orWhere('nombre', 'ilike', "%{$query}%");
                      })
                  );
            })
            ->limit(15)
            ->get()
            ->map(fn($s) => [
                'id'             => $s->producto->id,
                'codigo'         => $s->producto->codigo,
                'nombre'         => $s->producto->nombre,
                'marca'          => $s->producto->marca?->nombre,
                'requiere_serie' => $s->producto->requiere_serie,
                'disponible'     => (float) $s->stock_actual,
            ]);

        return response()->json(['resultados' => $resultados]);
    }

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $busquedaRealizada = $request->boolean('buscado');

        $traslados = null;

        if ($busquedaRealizada) {
            $traslados = TrasladoBodega::with(['bodegaOrigen', 'bodegaDestino', 'enviadoPor', 'detalles'])
                ->where('empresa_id', $empresaId)
                ->when($request->search, fn($q) => $q->where('numero', 'ilike', "%{$request->search}%"))
                ->when($request->estado, fn($q) => $q->where('estado', $request->estado))
                ->when($request->bodega_origen_id, fn($q) => $q->where('bodega_origen_id', $request->bodega_origen_id))
                ->when($request->bodega_destino_id, fn($q) => $q->where('bodega_destino_id', $request->bodega_destino_id))
                ->when($request->fecha_desde, fn($q) => $q->where('fecha', '>=', $request->fecha_desde))
                ->when($request->fecha_hasta, fn($q) => $q->where('fecha', '<=', $request->fecha_hasta))
                ->orderByDesc('created_at')
                ->paginate(20)
                ->withQueryString();
        }

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Traslados/Index', [
            'traslados' => $traslados,
            'bodegas'   => $bodegas,
            'filters'   => $request->only(['search', 'estado', 'bodega_origen_id', 'bodega_destino_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Traslados/Form', [
            'productos' => Producto::where('estado', true)
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
            'bodega_origen_id'             => ['required', 'integer', 'exists_empresa:bodegas', 'different:bodega_destino_id'],
            'bodega_destino_id'            => ['required', 'integer', 'exists_empresa:bodegas'],
            'detalles'                     => ['required', 'array', 'min:1'],
            'detalles.*.producto_id'       => ['required', 'integer', 'exists:productos,id'],
            'detalles.*.cantidad_enviada'  => ['required', 'integer', 'min:1'],
            'observacion'                  => ['nullable', 'string'],
        ], [
            'bodega_origen_id.different'      => 'La bodega origen y destino no pueden ser la misma.',
            'detalles.*.producto_id.required' => 'Hay filas sin producto: elija el producto o elimine la fila.',
            'detalles.*.cantidad_enviada.*'   => 'Cada producto debe tener una cantidad entera mayor a cero.',
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

                    $producto = Producto::find($detalleData['producto_id']);
                    if ($producto?->tipo === 'servicio') {
                        throw new \Exception("{$producto->codigo} es un servicio: no tiene stock que trasladar.");
                    }

                    $detalle = TrasladoDetalle::create([
                        'traslado_id'      => $traslado->id,
                        'producto_id'      => $detalleData['producto_id'],
                        'cantidad_enviada' => $detalleData['cantidad_enviada'],
                    ]);

                    $this->inventario->reservarStock(
                        (int) $detalleData['producto_id'],
                        (int) $data['bodega_origen_id'],
                        (float) $detalleData['cantidad_enviada'],
                        'traslado_detalle',
                        $detalle->id
                    );
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

    public function show(Request $request, TrasladoBodega $traslado): Response|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        $traslado->load(['bodegaOrigen', 'bodegaDestino', 'enviadoPor', 'recibidoPor', 'detalles.producto']);

        if ($request->wantsJson()) {
            return response()->json(['traslado' => $traslado]);
        }

        return Inertia::render('Inventario/Traslados/Show', [
            'traslado' => $traslado,
        ]);
    }

    public function confirmar(Request $request, TrasladoBodega $traslado): RedirectResponse|JsonResponse
    {
        $empresaId = (int) session('empresa_activa_id');

        if ((int) $traslado->empresa_id !== $empresaId) {
            abort(403);
        }

        if (!$traslado->isPendiente()) {
            return response()->json(['message' => 'El traslado ya no está pendiente.'], 422);
        }

        $request->validate([
            'detalles'                     => ['nullable', 'array'],
            'detalles.*.id'                => ['required', 'integer'],
            'detalles.*.cantidad_recibida' => ['required', 'integer', 'min:0'],
            'observacion'                  => ['nullable', 'string', 'max:1000'],
        ], [
            'detalles.*.cantidad_recibida.integer' => 'La cantidad recibida debe ser un número entero de unidades.',
        ]);

        // Se recorre SIEMPRE el detalle del propio traslado: antes se iteraba lo que mandaba el navegador, así que un
        // envío vacío aceptaba el traslado sin mover stock (la reserva quedaba colgada) y se podían enviar ids de
        // detalle de OTRO traslado.
        $traslado->load('detalles.producto');
        $recibidas = collect($request->input('detalles', []))->keyBy(fn ($d) => (int) $d['id']);

        foreach ($recibidas->keys() as $id) {
            if (!$traslado->detalles->contains('id', $id)) {
                return response()->json(['message' => 'El detalle enviado no pertenece a este traslado.'], 422);
            }
        }

        $mermas = [];
        foreach ($traslado->detalles as $detalle) {
            $recibida = (int) ($recibidas[$detalle->id]['cantidad_recibida'] ?? $detalle->cantidad_enviada);
            if ($recibida > (int) $detalle->cantidad_enviada) {
                return response()->json(['message' => "{$detalle->producto?->codigo}: no se puede recibir más de lo enviado (" . (int) $detalle->cantidad_enviada . ")."], 422);
            }
            if ($recibida < (int) $detalle->cantidad_enviada) {
                $mermas[] = (int) $detalle->producto_id;
            }
        }

        // La diferencia entre lo enviado y lo recibido es un faltante: va a 5.01.09 (PC-INV-01), igual que un ajuste
        // negativo. Antes desaparecía del kárdex sin asiento y el mayor de inventario quedaba descuadrado.
        $asientos = app(\App\Services\AsientoService::class);
        if ($mermas) {
            try {
                $asientos->validarConfiguracion($empresaId, ['cta_ajuste_inventario'], now()->toDateString());
                $asientos->cuentasProductos($mermas, $empresaId);
            } catch (\DomainException $e) {
                return response()->json(['message' => 'Hay unidades faltantes y no se pueden contabilizar: ' . $e->getMessage()], 422);
            }
        }

        try {
            DB::transaction(function () use ($request, $traslado, $recibidas, $empresaId, $asientos) {
                // Bloqueo del traslado: dos confirmaciones simultáneas movían el stock dos veces.
                $bloqueado = TrasladoBodega::whereKey($traslado->id)->lockForUpdate()->first();
                if (!$bloqueado->isPendiente()) {
                    throw new \RuntimeException('El traslado ya fue procesado por otro usuario.');
                }

                $faltantes = [];
                foreach ($traslado->detalles as $detalle) {
                    $enviada  = (float) $detalle->cantidad_enviada;
                    $recibida = (float) ($recibidas[$detalle->id]['cantidad_recibida'] ?? $enviada);

                    // Libera la reserva hecha en store() antes de egresar lo reservado. Usa cantidad_enviada (lo
                    // reservado), no cantidad_recibida (puede diferir por mermas).
                    $this->inventario->liberarReserva((int) $detalle->producto_id, (int) $traslado->bodega_origen_id, $enviada);

                    $costoPromedio = (float) InventarioSaldo::where('producto_id', $detalle->producto_id)
                        ->where('bodega_id', $traslado->bodega_origen_id)
                        ->value('costo_promedio');

                    $this->inventario->egresarStock(
                        (int) $detalle->producto_id,
                        (int) $traslado->bodega_origen_id,
                        $enviada,
                        'traslado',
                        $traslado->id,
                        $traslado->numero,
                        "Traslado {$traslado->numero} hacia bodega destino"
                    );

                    if ($recibida > 0) {
                        $this->inventario->ingresarStock(
                            (int) $detalle->producto_id,
                            (int) $traslado->bodega_destino_id,
                            $recibida,
                            $costoPromedio,
                            'traslado',
                            $traslado->id,
                            $traslado->numero,
                            "Traslado {$traslado->numero} desde bodega origen"
                        );
                    }

                    if ($recibida < $enviada) {
                        $faltantes[] = ['producto_id' => (int) $detalle->producto_id, 'valor' => round(($enviada - $recibida) * $costoPromedio, 2)];
                    }

                    $detalle->update(['cantidad_recibida' => $recibida]);
                }

                $asientos->ajusteInventario($empresaId, 'faltante', $faltantes,
                    "Faltante al recibir el traslado {$traslado->numero}", 'TRASLADO', $traslado->id, $traslado->numero);

                // La observación de recepción se agrega a la de envío (antes la reemplazaba y se perdía la original).
                $obsRecepcion = trim((string) $request->observacion);
                $traslado->update([
                    'estado'          => 'aceptado',
                    'recibido_por'    => Auth::id(),
                    'fecha_recepcion' => now()->toDateString(),
                    'observacion'     => $obsRecepcion === ''
                        ? $traslado->observacion
                        : trim(($traslado->observacion ? $traslado->observacion . "\n" : '') . 'Recepción: ' . $obsRecepcion),
                ]);

                $this->auditoria->documento('confirmar', 'inventario', 'traslados_bodega', $traslado->id,
                    "Traslado {$traslado->numero} aceptado por usuario " . Auth::id()
                    . ($faltantes ? ' con ' . count($faltantes) . ' producto(s) con faltante' : ''));
            });
        } catch (\Throwable $e) {
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
            return response()->json(['message' => 'El traslado ya no está pendiente.'], 422);
        }

        $request->validate([
            'motivo' => ['required', 'string', 'max:1000'],
        ]);

        try {
            DB::transaction(function () use ($request, $traslado) {
                $bloqueado = TrasladoBodega::whereKey($traslado->id)->lockForUpdate()->first();
                if (!$bloqueado->isPendiente()) {
                    throw new \RuntimeException('El traslado ya fue procesado por otro usuario.');
                }
                $traslado->load('detalles');

                foreach ($traslado->detalles as $detalle) {
                    $this->inventario->liberarReserva(
                        (int) $detalle->producto_id,
                        (int) $traslado->bodega_origen_id,
                        (float) $detalle->cantidad_enviada
                    );
                }

                $traslado->update([
                    'estado'      => 'rechazado',
                    'observacion' => trim(($traslado->observacion ? $traslado->observacion . "\n" : '') . 'Rechazo: ' . $request->motivo),
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
