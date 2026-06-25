<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AnticipoProveedor;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CuentaPagar;
use App\Models\Importacion;
use App\Models\InventarioSaldo;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\AsientoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ImportacionController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(): Response
    {
        $empresaId    = session('empresa_activa_id');
        $importaciones = Importacion::with('proveedor')
            ->where('empresa_id', $empresaId)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($i) => [
                'id'                => $i->id,
                'nombre'            => $i->nombre,
                'num_invoice'       => $i->num_invoice,
                'agente_aduanero'   => $i->agente_aduanero,
                'proveedor'         => $i->proveedor?->razon_social,
                'pais_embarque'     => $i->pais_embarque,
                'costo_fob'         => $i->costo_fob,
                'divisa'            => $i->divisa,
                'total_costos_extra'=> $i->total_costos_extra,
                'costo_total'       => $i->costo_total,
                'metodo_prorrateo'  => $i->metodo_prorrateo,
                'proveedor_id'      => $i->proveedor_id,
                'fecha_partida'     => $i->fecha_partida?->format('Y-m-d'),
                'fecha_llegada'     => $i->fecha_llegada?->format('Y-m-d'),
                'fecha_liquidacion' => $i->fecha_liquidacion?->format('d/m/Y'),
                'estado'            => $i->estado,
                'estado_label'      => $i->estado_label,
                'estado_color'      => $i->estado_color,
                'observaciones'     => $i->observaciones,
            ]);

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social', 'pais', 'divisa', 'tipo']);

        return Inertia::render('Compras/Importaciones/Index', [
            'importaciones' => $importaciones,
            'proveedores'   => $proveedores,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'nombre'        => 'required|string|max:200',
            'proveedor_id'  => 'nullable|exists:proveedores,id',
            'num_invoice'   => 'nullable|string|max:100',
            'costo_fob'     => 'required|numeric|min:0',
            'pais_embarque' => 'nullable|string|max:100',
            'fecha_partida' => 'nullable|date',
            'fecha_llegada' => 'nullable|date|after_or_equal:fecha_partida',
        ]);

        Importacion::create([
            ...$request->only([
                'nombre', 'proveedor_id', 'num_invoice', 'agente_aduanero',
                'pais_embarque', 'costo_fob', 'divisa', 'fecha_partida',
                'fecha_llegada', 'observaciones',
            ]),
            'empresa_id' => $empresaId,
            'estado'     => 'en_transito',
            'created_by' => Auth::id(),
        ]);

        return back()->with('success',
            "Importación {$request->nombre} creada correctamente.");
    }

    public function update(Request $request, Importacion $importacion): RedirectResponse
    {
        if ($importacion->estaLiquidada()) {
            return back()->with('error',
                'No se puede editar una importación ya liquidada.');
        }

        $request->validate([
            'estado'        => 'nullable|in:en_transito,en_aduana,liquidada',
            'fecha_llegada' => 'nullable|date',
        ]);

        $importacion->update($request->only([
            'nombre', 'agente_aduanero', 'pais_embarque', 'costo_fob',
            'divisa', 'fecha_partida', 'fecha_llegada', 'estado', 'observaciones',
        ]));

        return back()->with('success', 'Importación actualizada correctamente.');
    }

    public function liquidar(Request $request, Importacion $importacion): RedirectResponse
    {
        if ($importacion->estaLiquidada()) {
            return back()->with('error', 'Esta importación ya está liquidada.');
        }

        $request->validate([
            'metodo_prorrateo'  => 'required|in:cantidad,precio',
            'fecha_liquidacion' => 'required|date',
        ]);

        // Compras de productos (base del prorrateo)
        $comprasProducto = Compra::where('importacion_id', $importacion->id)
            ->where('gasto_no_deducible', false)
            ->with('detalles')->get();

        // Costos extra ya registrados como compras
        $comprasGasto = Compra::where('importacion_id', $importacion->id)
            ->where('gasto_no_deducible', true)
            ->get();

        if ($comprasProducto->isEmpty()) {
            return back()->with('error',
                'No hay facturas de productos vinculadas. Crea una desde el Tab "Productos".');
        }

        if ($comprasGasto->isEmpty()) {
            return back()->with('error',
                'No hay costos extra registrados. Agrégalos desde el Tab "Costos Extra".');
        }

        $totalCostosExtra = (float) $comprasGasto->sum('total');

        $metodo = $request->input('metodo_prorrateo');

        $bases = $comprasProducto->map(fn($compra) => match ($metodo) {
            'cantidad' => (float) $compra->detalles->sum('cantidad'),
            default    => (float) $compra->total,
        });

        $baseTotal = (float) $bases->sum();

        if ($baseTotal <= 0) {
            return back()->with('error',
                'No se puede prorratear: las facturas de productos no tienen cantidad ni valor.');
        }

        DB::transaction(function () use ($comprasProducto, $bases, $baseTotal, $totalCostosExtra) {
            $comprasProducto->each(function ($compra, $idx) use ($bases, $baseTotal, $totalCostosExtra) {
                $proporcion    = $bases[$idx] / $baseTotal;
                $costoAsignado = $totalCostosExtra * $proporcion;

                $detallesValidos = $compra->detalles->filter(
                    fn($d) => $d->producto_id !== null
                        && (float) $d->cantidad > 0
                        && (float) $d->precio_unitario > 0.01
                );
                $cantidadValida = (float) $detallesValidos->sum('cantidad');

                if ($cantidadValida <= 0) return;

                foreach ($detallesValidos as $detalle) {
                    $costoPorUnitario = ($costoAsignado * ($detalle->cantidad / $cantidadValida))
                        / $detalle->cantidad;
                    $costoPorUnitarioRedondeado = round($costoPorUnitario, 4);

                    $saldo = InventarioSaldo::where('producto_id', $detalle->producto_id)->first();
                    if ($saldo && $saldo->stock_actual > 0) {
                        $saldo->increment('costo_promedio', $costoPorUnitarioRedondeado);
                    }

                    $nuevoCosto = round((float) $detalle->precio_unitario + $costoPorUnitario, 4);
                    Producto::where('id', $detalle->producto_id)
                        ->update(['costo' => $nuevoCosto]);
                }
            });
        });

        // Auto-cruce de anticipos pendientes contra CxP de esta importación
        if ($importacion->proveedor_id) {
            try {
                $anticiposPendientes = AnticipoProveedor::where('empresa_id', $importacion->empresa_id)
                    ->where('proveedor_id', $importacion->proveedor_id)
                    ->where('saldo', '>', 0)
                    ->where('estado', 'pendiente')
                    ->orderBy('fecha')
                    ->orderBy('id')
                    ->get();

                $cxpPendientes = CuentaPagar::whereHas('compra', function ($q) use ($importacion) {
                    $q->where('importacion_id', $importacion->id)
                      ->where('gasto_no_deducible', false);
                })
                ->where('saldo', '>', 0)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->get();

                if ($anticiposPendientes->isNotEmpty() && $cxpPendientes->isNotEmpty()) {
                    DB::transaction(function () use ($anticiposPendientes, $cxpPendientes, $importacion, $request) {
                        foreach ($anticiposPendientes as $anticipo) {
                            foreach ($cxpPendientes as $cxp) {
                                if ((float) $anticipo->saldo <= 0.001 || (float) $cxp->saldo <= 0.001) {
                                    continue;
                                }

                                $montoCruce = min((float) $anticipo->saldo, (float) $cxp->saldo);

                                $nuevoSaldoAnticipo = max(0, (float) $anticipo->saldo - $montoCruce);
                                $anticipo->update([
                                    'saldo'  => $nuevoSaldoAnticipo,
                                    'estado' => $nuevoSaldoAnticipo <= 0.001 ? 'cruzado' : 'pendiente',
                                ]);
                                $anticipo->refresh();

                                $nuevoSaldoCxP = max(0, (float) $cxp->saldo - $montoCruce);
                                $cxp->update([
                                    'saldo'  => $nuevoSaldoCxP,
                                    'estado' => $nuevoSaldoCxP <= 0.001 ? 'pagada' : 'parcial',
                                ]);
                                $cxp->refresh();

                                try {
                                    $referencia = 'CRZ-ANT-' . str_pad($anticipo->id, 4, '0', STR_PAD_LEFT);
                                    $this->asientoService->cruciarAnticipo(
                                        empresaId:  $importacion->empresa_id,
                                        anticipoId: $anticipo->id,
                                        referencia: $referencia,
                                        monto:      $montoCruce,
                                        fecha:      $request->input('fecha_liquidacion'),
                                    );
                                } catch (\Exception) {
                                    // No bloquear si período contable cerrado
                                }
                            }
                        }
                    });
                }
            } catch (\Exception) {
                // Auto-cruce no crítico — la liquidación ya fue procesada
            }
        }

        $costoFob   = (float) $importacion->costo_fob;
        $costoTotal = $costoFob + $totalCostosExtra;

        $importacion->update([
            'metodo_prorrateo'   => $metodo,
            'total_costos_extra' => $totalCostosExtra,
            'costo_total'        => $costoTotal,
            'fecha_liquidacion'  => $request->input('fecha_liquidacion'),
            'estado'             => 'liquidada',
        ]);

        return back()->with('success',
            "Importación {$importacion->nombre} liquidada. " .
            'Costo total: $' . number_format($costoTotal, 2));
    }

    public function crearFactura(Importacion $importacion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        if ($importacion->empresa_id !== $empresaId) {
            return response()->json(['message' => 'Sin autorización.'], 403);
        }

        $yaExiste = Compra::where('importacion_id', $importacion->id)
            ->where('gasto_no_deducible', false)
            ->whereHas('detalles', fn($q) => $q->whereNotNull('producto_id'))
            ->exists();

        return response()->json([
            'ya_existe'           => $yaExiste,
            'tipo_documento'      => 'EXT',
            'proveedor_id'        => $importacion->proveedor_id,
            'proveedor_nombre'    => optional($importacion->proveedor)->razon_social,
            'num_documento'       => $importacion->num_invoice ?? '',
            'fecha_emision'       => $importacion->fecha_llegada
                                        ? $importacion->fecha_llegada->format('Y-m-d')
                                        : now()->format('Y-m-d'),
            'importacion_id'      => $importacion->id,
            'importacion_nombre'  => $importacion->nombre,
            'sustento_tributario' => '06',
            'dias_credito'        => 0,
            'iva_forzado'         => 0,
            'metodo_envio'        => 'FOB',
            'divisa'              => $importacion->divisa ?? 'USD',
        ]);
    }

    public function agregarCosto(Request $request, Importacion $importacion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        if ($importacion->empresa_id !== $empresaId) {
            return response()->json(['message' => 'Sin autorización.'], 403);
        }
        if ($importacion->estaLiquidada()) {
            return response()->json(['message' => 'La importación ya está liquidada.'], 422);
        }

        $request->validate([
            'concepto'     => 'required|string|max:200',
            'proveedor_id' => 'nullable|exists:proveedores,id',
            'monto'        => 'required|numeric|min:0.01',
            'num_factura'  => 'nullable|string|max:30',
        ]);

        $monto       = (float) $request->monto;
        $proveedorId = $request->proveedor_id ?? $importacion->proveedor_id;

        if (!$proveedorId) {
            return response()->json(['message' => 'Selecciona el proveedor que cobró este gasto.'], 422);
        }

        DB::transaction(function () use ($request, $importacion, $empresaId, $monto, $proveedorId) {
            $compra = Compra::create([
                'empresa_id'          => $empresaId,
                'proveedor_id'        => $proveedorId,
                'importacion_id'      => $importacion->id,
                'tipo_documento'      => 'LIQ',
                'num_documento'       => $request->num_factura ?? ('GASTO-' . $importacion->id . '-' . now()->format('Hisu')),
                'fecha_emision'       => now()->toDateString(),
                'fecha_registro'      => now()->toDateString(),
                'fecha_vencimiento'   => now()->toDateString(),
                'dias_credito'        => 0,
                'subtotal_0'          => $monto,
                'subtotal_iva'        => 0,
                'total_iva'           => 0,
                'total_ice'           => 0,
                'total'               => $monto,
                'gasto_no_deducible'  => true,
                'sustento_tributario' => 2,
                'concepto'            => $request->concepto,
                'estado'              => 'activa',
                'created_by'          => Auth::id(),
            ]);

            CompraDetalle::create([
                'compra_id'       => $compra->id,
                'descripcion'     => $request->concepto,
                'cantidad'        => 1,
                'precio_unitario' => $monto,
                'descuento'       => 0,
                'subtotal'        => $monto,
                'porcentaje_iva'  => 0,
                'valor_iva'       => 0,
                'total'           => $monto,
                'es_activo_fijo'  => false,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Costo \"{$request->concepto}\" registrado: \${$monto}",
        ]);
    }

    public function detalle(Importacion $importacion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        if ($importacion->empresa_id !== $empresaId) abort(403);

        $compras = Compra::where('importacion_id', $importacion->id)
            ->with(['detalles.producto'])
            ->get();

        $productos = $compras
            ->where('gasto_no_deducible', false)
            ->flatMap(fn($c) => $c->detalles)
            ->filter(fn($d) => $d->producto_id !== null)
            ->map(fn($d) => [
                'codigo'          => $d->producto?->codigo ?? '—',
                'nombre'          => $d->descripcion,
                'cantidad'        => (float) $d->cantidad,
                'precio_unitario' => (float) $d->precio_unitario,
                'subtotal'        => (float) $d->subtotal,
                'costo_actual'    => $d->producto ? (float) $d->producto->costo : null,
            ])
            ->values();

        $gastos = $compras
            ->where('gasto_no_deducible', true)
            ->map(fn($c) => [
                'concepto'      => $c->concepto ?: $c->num_documento,
                'num_documento' => $c->num_documento,
                'monto'         => (float) $c->total,
            ])
            ->values();

        $totalGastosFact = $gastos->sum('monto');

        return response()->json([
            'productos' => $productos,
            'gastos'    => $gastos,
            'totales'   => [
                'fob'    => (float) $importacion->costo_fob,
                'gastos' => $totalGastosFact > 0
                    ? $totalGastosFact
                    : (float) $importacion->total_costos_extra,
                'total'  => (float) $importacion->costo_total,
            ],
        ]);
    }
}
