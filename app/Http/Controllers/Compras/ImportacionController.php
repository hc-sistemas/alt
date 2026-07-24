<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AnticipoProveedor;
use App\Models\AsientoContable;
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

        $estadoAnterior = $importacion->estado;

        $request->validate([
            'metodo_prorrateo'  => 'required|in:cantidad,precio,peso,factor_importacion',
            'fecha_liquidacion' => 'required|date',
            'comision_pct'      => 'nullable|numeric|min:0',
            'margen_pvd_pct'    => 'nullable|numeric|min:0|max:99.99',
            'margen_pvp_pct'    => 'nullable|numeric|min:0|max:99.99',
        ]);

        // Compras de productos (base del prorrateo) — con producto para los métodos "peso" y "factor_importacion"
        $comprasProducto = Compra::where('importacion_id', $importacion->id)
            ->where('gasto_no_deducible', false)
            ->with('detalles.producto')->get();

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
        $costoFob         = (float) $importacion->costo_fob;

        $metodo = $request->input('metodo_prorrateo');

        // ── Método "Factor de Importación" — réplica exacta de la hoja de cálculo real
        // del cliente (ver AUDITORIA_IMPORTACIONES.md): Factor = (Mercadería + TODOS los
        // costos extra) / Mercadería FOB. Se aplica multiplicando directamente el precio
        // unitario original de cada línea — no es un prorrateo proporcional entre líneas
        // como cantidad/precio/peso, es un factor único aplicado uniformemente.
        $factorImportacion = null;
        $comisionPct       = null;
        $margenPvdPct      = null;
        $margenPvpPct      = null;
        $baseDetalle       = null;
        $bases             = collect();
        $baseTotal         = 0.0;

        if ($metodo === 'factor_importacion') {
            if ($costoFob <= 0) {
                return back()->with('error',
                    'El costo FOB (Mercadería) de la importación debe ser mayor a 0 para calcular el Factor de Importación.');
            }
            $comisionPct       = (float) $request->input('comision_pct', 3);
            $margenPvdPct      = (float) $request->input('margen_pvd_pct', 20);
            $margenPvpPct      = (float) $request->input('margen_pvp_pct', 35);
            $factorImportacion = ($costoFob + $totalCostosExtra) / $costoFob;
        } else {
            // Base de prorrateo por línea de detalle, según el método elegido — se usa
            // tanto para repartir el costo extra ENTRE facturas como DENTRO de cada factura.
            $baseDetalle = fn($d) => match ($metodo) {
                'peso'   => (float) $d->cantidad * (float) ($d->producto?->peso ?? 0),
                'precio' => (float) $d->cantidad * (float) $d->precio_unitario,
                default  => (float) $d->cantidad,
            };

            $bases = $comprasProducto->map(function ($compra) use ($baseDetalle) {
                $validos = $compra->detalles->filter(
                    fn($d) => $d->producto_id !== null
                        && (float) $d->cantidad > 0
                        && (float) $d->precio_unitario > 0.01
                );
                return (float) $validos->sum($baseDetalle);
            });

            $baseTotal = (float) $bases->sum();

            if ($baseTotal <= 0) {
                $mensaje = $metodo === 'peso'
                    ? 'No se puede prorratear por peso: ningún producto de esta importación tiene peso configurado (ver ficha de producto).'
                    : 'No se puede prorratear: las facturas de productos no tienen cantidad ni valor.';
                return back()->with('error', $mensaje);
            }
        }

        // ── Snapshot para poder revertir esta liquidación más adelante ──
        $snapshotProductos = []; // producto_id => [costo_anterior, saldo_id, costo_promedio_anterior, delta, stock_actual_al_momento]
        $snapshotCruces    = [];

        DB::transaction(function () use (
            $comprasProducto, $bases, $baseTotal, $totalCostosExtra, $baseDetalle,
            $metodo, $factorImportacion, &$snapshotProductos
        ) {
            $comprasProducto->each(function ($compra, $idx) use (
                $bases, $baseTotal, $totalCostosExtra, $baseDetalle, $metodo, $factorImportacion, &$snapshotProductos
            ) {
                $detallesValidos = $compra->detalles->filter(
                    fn($d) => $d->producto_id !== null
                        && (float) $d->cantidad > 0
                        && (float) $d->precio_unitario > 0.01
                );

                if ($metodo === 'factor_importacion') {
                    foreach ($detallesValidos as $detalle) {
                        $precioOriginal = (float) $detalle->precio_unitario;
                        $costoNuevo     = round($precioOriginal * $factorImportacion, 4);
                        $deltaCosto     = round($costoNuevo - $precioOriginal, 4);
                        $this->aplicarCostoLinea($detalle->producto_id, $costoNuevo, $deltaCosto, $snapshotProductos);
                    }
                    return;
                }

                $proporcion    = $bases[$idx] / $baseTotal;
                $costoAsignado = $totalCostosExtra * $proporcion;
                $baseValidaFactura = (float) $detallesValidos->sum($baseDetalle);

                if ($baseValidaFactura <= 0) return;

                foreach ($detallesValidos as $detalle) {
                    $baseLinea = $baseDetalle($detalle);
                    if ($baseLinea <= 0) continue; // ej: método "peso" y este producto no tiene peso configurado

                    $costoLineaTotal  = $costoAsignado * ($baseLinea / $baseValidaFactura);
                    $costoPorUnitario = $costoLineaTotal / $detalle->cantidad;
                    $costoPorUnitarioRedondeado = round($costoPorUnitario, 4);
                    $nuevoCosto = round((float) $detalle->precio_unitario + $costoPorUnitario, 4);

                    $this->aplicarCostoLinea($detalle->producto_id, $nuevoCosto, $costoPorUnitarioRedondeado, $snapshotProductos);
                }
            });
        });

        // Auto-cruce de anticipos pendientes contra CxP de esta importación (C-08)
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
                    DB::transaction(function () use ($anticiposPendientes, $cxpPendientes, $importacion, $request, &$snapshotCruces) {
                        foreach ($anticiposPendientes as $anticipo) {
                            foreach ($cxpPendientes as $cxp) {
                                if ((float) $anticipo->saldo <= 0.001 || (float) $cxp->saldo <= 0.001) {
                                    continue;
                                }

                                $montoCruce = min((float) $anticipo->saldo, (float) $cxp->saldo);

                                $saldoAnticipoAnterior  = (float) $anticipo->saldo;
                                $estadoAnticipoAnterior = $anticipo->estado;
                                $saldoCxpAnterior       = (float) $cxp->saldo;
                                $estadoCxpAnterior      = $cxp->estado;

                                $nuevoSaldoAnticipo = max(0, $saldoAnticipoAnterior - $montoCruce);
                                $anticipo->update([
                                    'saldo'  => $nuevoSaldoAnticipo,
                                    'estado' => $nuevoSaldoAnticipo <= 0.001 ? 'cruzado' : 'pendiente',
                                ]);
                                $anticipo->refresh();

                                $nuevoSaldoCxP = max(0, $saldoCxpAnterior - $montoCruce);
                                $cxp->update([
                                    'saldo'  => $nuevoSaldoCxP,
                                    'estado' => $nuevoSaldoCxP <= 0.001 ? 'pagada' : 'parcial',
                                ]);
                                $cxp->refresh();

                                try {
                                    $referencia = 'CRZ-ANT-' . str_pad($anticipo->id, 4, '0', STR_PAD_LEFT);
                                    $asientoCruce = $this->asientoService->cruciarAnticipo(
                                        empresaId:  $importacion->empresa_id,
                                        anticipoId: $anticipo->id,
                                        referencia: $referencia,
                                        monto:      $montoCruce,
                                        fecha:      $request->input('fecha_liquidacion'),
                                    );

                                    $snapshotCruces[] = [
                                        'anticipo_id'              => $anticipo->id,
                                        'cxp_id'                   => $cxp->id,
                                        'monto_cruzado'            => $montoCruce,
                                        'saldo_anticipo_anterior'  => $saldoAnticipoAnterior,
                                        'estado_anticipo_anterior'=> $estadoAnticipoAnterior,
                                        'saldo_cxp_anterior'       => $saldoCxpAnterior,
                                        'estado_cxp_anterior'      => $estadoCxpAnterior,
                                        'asiento_id'               => $asientoCruce->id,
                                    ];
                                } catch (\Exception) {
                                    // No bloquear si período contable cerrado — pero entonces este
                                    // cruce no queda en el snapshot (no hay asiento que revertir).
                                }
                            }
                        }
                    });
                }
            } catch (\Exception) {
                // Auto-cruce no crítico — la liquidación ya fue procesada
            }
        }

        $costoTotal = $costoFob + $totalCostosExtra;

        $importacion->update([
            'metodo_prorrateo'     => $metodo,
            'total_costos_extra'   => $totalCostosExtra,
            'costo_total'          => $costoTotal,
            'fecha_liquidacion'    => $request->input('fecha_liquidacion'),
            'estado'               => 'liquidada',
            'snapshot_liquidacion' => [
                'estado_anterior'    => $estadoAnterior,
                'productos'          => array_values($snapshotProductos),
                'cruces_anticipo'    => $snapshotCruces,
                'factor_importacion' => $factorImportacion,
                'comision_pct'       => $comisionPct,
                'margen_pvd_pct'     => $margenPvdPct,
                'margen_pvp_pct'     => $margenPvpPct,
            ],
        ]);

        return back()->with('success',
            "Importación {$importacion->nombre} liquidada. " .
            'Costo total: $' . number_format($costoTotal, 2));
    }

    private function aplicarCostoLinea(int $productoId, float $costoNuevo, float $deltaCosto, array &$snapshotProductos): void
    {
        if (!isset($snapshotProductos[$productoId])) {
            $costoActual = Producto::where('id', $productoId)->value('costo');
            $snapshotProductos[$productoId] = [
                'producto_id'             => $productoId,
                'costo_anterior'          => (float) $costoActual,
                'saldo_id'                => null,
                'delta_costo_promedio'    => 0.0,
                'stock_actual_al_momento' => 0.0,
            ];
        }

        $saldo = InventarioSaldo::where('producto_id', $productoId)->first();
        if ($saldo && $saldo->stock_actual > 0) {
            if ($snapshotProductos[$productoId]['saldo_id'] === null) {
                $snapshotProductos[$productoId]['saldo_id'] = $saldo->id;
                $snapshotProductos[$productoId]['stock_actual_al_momento'] = (float) $saldo->stock_actual;
            }
            $snapshotProductos[$productoId]['delta_costo_promedio'] += $deltaCosto;
            $saldo->increment('costo_promedio', $deltaCosto);
        }

        Producto::where('id', $productoId)->update(['costo' => $costoNuevo]);
    }

    public function revertir(Importacion $importacion): RedirectResponse
    {
        if ($importacion->estado !== 'liquidada') {
            return back()->with('error', 'Solo se puede revertir una importación que esté liquidada.');
        }

        $snapshot = $importacion->snapshot_liquidacion;
        if (!$snapshot) {
            return back()->with('error',
                'Esta importación no tiene información de reversión disponible ' .
                '(fue liquidada antes de existir esta función). Contacta al administrador.');
        }

        // Candado: si ya se vendió/movió stock con el costo liquidado, revertir
        // dejaría el costo de ventas ya registrado inconsistente con el costo anterior.
        $productosVendidos = [];
        foreach ($snapshot['productos'] ?? [] as $p) {
            if (!$p['saldo_id']) continue;
            $saldoActual = InventarioSaldo::find($p['saldo_id']);
            if ($saldoActual && (float) $saldoActual->stock_actual < (float) $p['stock_actual_al_momento']) {
                $productosVendidos[] = $p['producto_id'];
            }
        }

        if (!empty($productosVendidos)) {
            $codigos = Producto::whereIn('id', $productosVendidos)->pluck('codigo')->implode(', ');
            return back()->with('error',
                "No se puede revertir: ya se vendieron o movieron unidades con el costo liquidado " .
                "de los productos [{$codigos}]. Revertir ahora dejaría el costo de ventas ya " .
                "registrado inconsistente con el costo anterior.");
        }

        try {
            DB::transaction(function () use ($importacion, $snapshot) {
                foreach ($snapshot['productos'] ?? [] as $p) {
                    Producto::where('id', $p['producto_id'])->update(['costo' => $p['costo_anterior']]);

                    if ($p['saldo_id'] && (float) $p['delta_costo_promedio'] != 0.0) {
                        InventarioSaldo::where('id', $p['saldo_id'])
                            ->decrement('costo_promedio', $p['delta_costo_promedio']);
                    }
                }

                foreach ($snapshot['cruces_anticipo'] ?? [] as $c) {
                    AnticipoProveedor::where('id', $c['anticipo_id'])->update([
                        'saldo'  => $c['saldo_anticipo_anterior'],
                        'estado' => $c['estado_anticipo_anterior'],
                    ]);

                    CuentaPagar::where('id', $c['cxp_id'])->update([
                        'saldo'  => $c['saldo_cxp_anterior'],
                        'estado' => $c['estado_cxp_anterior'],
                    ]);

                    $asiento = AsientoContable::find($c['asiento_id']);
                    if ($asiento && !$asiento->estaAnulado()) {
                        $this->asientoService->anular(
                            $asiento,
                            "Reversión de liquidación de importación \"{$importacion->nombre}\""
                        );
                    }
                }

                $importacion->update([
                    'estado'               => $snapshot['estado_anterior'] ?? 'en_aduana',
                    // metodo_prorrateo es NOT NULL en BD (default 'cantidad') — se resetea
                    // al valor por defecto de una importación aún no liquidada.
                    'metodo_prorrateo'     => 'cantidad',
                    'fecha_liquidacion'    => null,
                    'total_costos_extra'   => 0,
                    'costo_total'          => 0,
                    'snapshot_liquidacion' => null,
                ]);
            });
        } catch (\Exception $e) {
            return back()->with('error',
                'No se pudo revertir la liquidación: ' . $e->getMessage());
        }

        return back()->with('success',
            "Liquidación de \"{$importacion->nombre}\" revertida. " .
            'Puedes volver a liquidarla con otro método.');
    }

    public function copiar(Importacion $importacion): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        if ($importacion->empresa_id !== $empresaId) abort(403);

        $nueva = Importacion::create([
            'empresa_id'      => $empresaId,
            'proveedor_id'    => $importacion->proveedor_id,
            'nombre'          => $importacion->nombre . ' (Copia)',
            'agente_aduanero' => $importacion->agente_aduanero,
            'pais_embarque'   => $importacion->pais_embarque,
            'divisa'          => $importacion->divisa,
            'costo_fob'       => 0,
            'estado'          => 'en_transito',
            'created_by'      => Auth::id(),
        ]);

        return back()->with('success',
            "Importación \"{$nueva->nombre}\" creada a partir de \"{$importacion->nombre}\". " .
            'Completa fechas y monto FOB.');
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

    public function resultadoLiquidacion(Importacion $importacion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        if ($importacion->empresa_id !== $empresaId) abort(403);

        if ($importacion->estado !== 'liquidada') {
            return response()->json(['message' => 'Esta importación todavía no está liquidada.'], 422);
        }

        // costo_anterior viene del snapshot capturado justo antes de liquidar (fuente
        // exacta, no una estimación). Si la importación se liquidó antes de que este
        // snapshot existiera, queda en null — no se inventa un valor.
        $snapshot = $importacion->snapshot_liquidacion ?? [];
        $costoAnteriorPorProducto = collect($snapshot['productos'] ?? [])
            ->keyBy('producto_id')
            ->map(fn($p) => (float) $p['costo_anterior']);

        // Precios sugeridos (PVD/PVP) solo aplican al método "Factor de Importación" —
        // se recalculan aquí a partir del costo YA liquidado (que ya trae el factor
        // incorporado) más comisión/margen/IVA, sin necesidad de guardar un precio
        // sugerido por producto en el snapshot.
        $esFactorImportacion = $importacion->metodo_prorrateo === 'factor_importacion'
            && isset($snapshot['factor_importacion']);
        $comisionPct  = $esFactorImportacion ? (float) $snapshot['comision_pct']  : null;
        $margenPvdPct = $esFactorImportacion ? (float) $snapshot['margen_pvd_pct'] : null;
        $margenPvpPct = $esFactorImportacion ? (float) $snapshot['margen_pvp_pct'] : null;

        $cantidadPorProducto = CompraDetalle::whereHas('compra', function ($q) use ($importacion) {
                $q->where('importacion_id', $importacion->id)->where('gasto_no_deducible', false);
            })
            ->whereNotNull('producto_id')
            ->selectRaw('producto_id, SUM(cantidad) as cantidad_total')
            ->groupBy('producto_id')
            ->pluck('cantidad_total', 'producto_id');

        $productos = Producto::whereIn('id', $cantidadPorProducto->keys())
            ->orderBy('codigo')
            ->get()
            ->map(function ($p) use ($cantidadPorProducto, $costoAnteriorPorProducto, $esFactorImportacion, $comisionPct, $margenPvdPct, $margenPvpPct) {
                $fila = [
                    'producto_id'    => $p->id,
                    'codigo'         => $p->codigo,
                    'nombre'         => $p->nombre,
                    'cantidad'       => (float) $cantidadPorProducto->get($p->id, 0),
                    'costo_anterior' => $costoAnteriorPorProducto->get($p->id),
                    'costo_nuevo'    => (float) $p->costo,
                    'pvp'            => (float) $p->pvp,
                    'pvd'            => (float) $p->pvd,
                    'pvp_sugerido'   => null,
                    'pvd_sugerido'   => null,
                ];

                if ($esFactorImportacion && $p->costo > 0) {
                    $ivaPct           = (float) ($p->porcentaje_iva ?: 15);
                    $costoConComision = $p->costo * (1 + $comisionPct / 100);
                    $pvdSinIva        = $costoConComision / (1 - $margenPvdPct / 100);
                    $pvpSinIva        = $costoConComision / (1 - $margenPvpPct / 100);
                    $fila['pvd_sugerido'] = round($pvdSinIva * (1 + $ivaPct / 100), 4);
                    $fila['pvp_sugerido'] = round($pvpSinIva * (1 + $ivaPct / 100), 4);
                }

                return $fila;
            })
            ->values();

        return response()->json([
            'metodo_prorrateo'   => $importacion->metodo_prorrateo,
            'costo_total'        => (float) $importacion->costo_total,
            'cantidad_productos' => $productos->count(),
            'factor_importacion' => $esFactorImportacion ? (float) $snapshot['factor_importacion'] : null,
            'productos'          => $productos,
        ]);
    }

    public function actualizarPreciosLote(Request $request, Importacion $importacion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        if ($importacion->empresa_id !== $empresaId) abort(403);

        $request->validate([
            'precios'              => 'required|array|min:1',
            'precios.*.producto_id'=> 'required|integer',
            'precios.*.pvp'        => 'required|numeric|min:0',
            'precios.*.pvd'        => 'required|numeric|min:0',
        ]);

        // Solo se permite tocar productos que realmente pertenecen a esta importación —
        // evita que el endpoint se use para editar precios de cualquier producto.
        $productosValidos = CompraDetalle::whereHas('compra', function ($q) use ($importacion) {
                $q->where('importacion_id', $importacion->id)->where('gasto_no_deducible', false);
            })
            ->whereNotNull('producto_id')
            ->distinct()
            ->pluck('producto_id');

        $actualizados = 0;

        DB::transaction(function () use ($request, $productosValidos, &$actualizados) {
            foreach ($request->input('precios') as $fila) {
                if (!$productosValidos->contains((int) $fila['producto_id'])) {
                    continue;
                }

                Producto::where('id', $fila['producto_id'])->update([
                    'pvp' => $fila['pvp'],
                    'pvd' => $fila['pvd'],
                ]);
                $actualizados++;
            }
        });

        return response()->json([
            'success'      => true,
            'actualizados' => $actualizados,
            'message'      => "Precios actualizados en {$actualizados} producto(s).",
        ]);
    }
}
