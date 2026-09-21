<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\CuentaCobrar;
use App\Models\Factura;
use App\Models\NotaCredito;
use App\Models\NotaCreditoDetalle;
use App\Services\AsientoService;
use App\Services\AuditoriaService;
use App\Services\InventarioService;
use App\Services\SecuencialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class NotaCreditoController extends Controller
{
    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
        private InventarioService $inventario,
        private AsientoService    $asiento,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        // Primera visita: fechas por defecto = hoy (si se borran, llegan vacías y no se fuerzan)
        $hoy = now()->toDateString();
        $request->merge([
            'fecha_desde' => $request->has('fecha_desde') ? $request->fecha_desde : $hoy,
            'fecha_hasta' => $request->has('fecha_hasta') ? $request->fecha_hasta : $hoy,
        ]);

        $query = NotaCredito::with(['factura', 'cliente'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', fn($q) => $q
                ->where('razon_social', 'ilike', "%{$request->cliente}%")
                ->orWhere('identificacion', 'ilike', "%{$request->cliente}%"));
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $notasCredito = $query->paginate(25)->withQueryString();

        $notasCredito->getCollection()->transform(function (NotaCredito $n) {
            return [
                'id'              => $n->id,
                'numero_completo' => $n->numero_completo ?? "NC-{$n->id}",
                'fecha'           => $n->fecha_emision?->toDateString(),
                'factura_numero'  => $n->factura?->numero_completo ?? '—',
                'cliente_razon'   => $n->cliente?->razon_social ?? '—',
                'total'           => (float) $n->total,
                'estado_sri'      => $n->estado_sri,
            ];
        });

        return Inertia::render('Ventas/NotasCredito/Index', [
            'notas'  => $notasCredito,
            'filtros' => $request->only(['estado', 'cliente', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:facturas,id',
        ]);

        $factura = Factura::with(['detalles.producto', 'cliente', 'empresa'])
            ->where('estado', 'activa')
            // DESHABILITADO TEMPORALMENTE para pruebas sin SRI — reactivar cuando el licen integre SRI.
            // ->where('estado_sri', 'autorizada')
            ->findOrFail($request->factura_id);

        return Inertia::render('Ventas/NotasCredito/Form', [
            'factura' => $factura,
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'factura_id'             => 'required|integer|exists:facturas,id',
            'motivo'                 => 'required|string|max:300',
            'detalles'               => 'required|array|min:1',
            'detalles.*.detalle_id'  => 'required|integer',
            'detalles.*.cantidad'    => 'required|numeric|min:0.01',
        ]);

        $factura = Factura::with('detalles')->findOrFail($request->factura_id);

        // Validar cantidades contra los originales
        foreach ($request->detalles as $det) {
            $original = $factura->detalles->firstWhere('id', $det['detalle_id']);
            if (!$original || $det['cantidad'] > $original->cantidad) {
                return back()->withErrors(['detalles' => 'La cantidad a devolver no puede superar la cantidad original.']);
            }

            $yaDevuelto = \App\Models\NotaCreditoDetalle::where('producto_id', $original->producto_id)
                ->whereHas('notaCredito', fn($q) => $q
                    ->where('factura_id', $factura->id)
                    ->where('estado', 'activa')
                )->sum('cantidad');

            if ((float)$yaDevuelto + (float)$det['cantidad'] > (float)$original->cantidad) {
                return back()->withErrors([
                    'detalles' => "La cantidad a devolver para \"{$original->descripcion}\" supera lo disponible. Ya devuelto: {$yaDevuelto}, original: {$original->cantidad}."
                ]);
            }
        }

        $subtotal = 0;
        $totalIva = 0;

        foreach ($request->detalles as $det) {
            $original   = $factura->detalles->firstWhere('id', $det['detalle_id']);
            $cantidad   = (float)$det['cantidad'];
            $precio     = (float)$original->precio_unitario;
            $descPct    = (float)$original->descuento_pct;
            $descuento  = $precio * $cantidad * ($descPct / 100);
            $neto       = ($precio * $cantidad) - $descuento;
            $iva        = (float)$original->porcentaje_iva > 0 ? $neto * ($original->porcentaje_iva / 100) : 0;

            $subtotal += $neto;
            $totalIva += $iva;
        }

        $total = $subtotal + $totalIva;

        // Costo (al costo real de salida) de la mercadería que vuelve por esta
        // NC — se usa para reversar el costo de ventas en el asiento.
        $costoDevuelto = 0.0;

        $notaCredito = DB::transaction(function () use ($request, $empresaId, $factura, $subtotal, $totalIva, $total, &$costoDevuelto) {
            $numero  = $this->secuencial->siguiente($empresaId, 'NC');
            $partes  = explode('-', $numero);

            $notaCredito = NotaCredito::create([
                'empresa_id'         => $empresaId,
                'factura_id'         => $factura->id,
                'cliente_id'         => $factura->cliente_id,
                'usuario_id'         => Auth::id(),
                'establecimiento'    => $partes[0] ?? '001',
                'punto_emision'      => $partes[1] ?? '001',
                'secuencial'         => $partes[2] ?? '000000001',
                'numero_completo'    => $numero,
                'fecha_emision'      => now()->toDateString(),
                'motivo'             => $request->motivo,
                'subtotal'           => $subtotal,
                'total_iva'          => $totalIva,
                'total'              => $total,
                'estado_sri'         => 'pendiente',
                'estado'             => 'activa',
                'genera_saldo_favor' => false,
            ]);

            $bodegaCuarentena = Bodega::where('empresa_id', $empresaId)
                ->where('tipo', 'cuarentena')
                ->first();

            foreach ($request->detalles as $det) {
                $original  = $factura->detalles->firstWhere('id', $det['detalle_id']);
                $cantidad  = (float)$det['cantidad'];
                $precio    = (float)$original->precio_unitario;
                $descPct   = (float)$original->descuento_pct;
                $descuento = $precio * $cantidad * ($descPct / 100);
                $neto      = ($precio * $cantidad) - $descuento;
                $ivaPct    = (float)$original->porcentaje_iva;
                $iva       = $ivaPct > 0 ? $neto * ($ivaPct / 100) : 0;

                NotaCreditoDetalle::create([
                    'nota_credito_id' => $notaCredito->id,
                    'producto_id'     => $original->producto_id,
                    'descripcion'     => $original->descripcion,
                    'cantidad'        => $cantidad,
                    'precio_unitario' => $precio,
                    'total'           => $neto + $iva,
                ]);

                if ($bodegaCuarentena && $original->producto_id) {
                    // El reingreso va al COSTO al que salió, no al precio de
                    // venta. Antes se pasaba `precio_unitario` como costo
                    // unitario: cada devolución metía el margen de venta dentro
                    // del costo promedio del producto (ingresarStock recalcula
                    // el promedio ponderado con el costo que recibe), inflando
                    // el costo del inventario de forma permanente e
                    // irreversible, y con él todos los costos de venta futuros.
                    //
                    // El costo real de esa salida está en el kárdex de la
                    // factura original.
                    $costoUnitario = (float) (DB::table('inventario_movimientos')
                        ->where('doc_tipo', 'FACTURA')
                        ->where('doc_id', $factura->id)
                        ->where('producto_id', $original->producto_id)
                        ->where('tipo', 'salida')
                        ->value('costo_unitario') ?? 0);

                    // Sin movimiento de salida registrado (facturas viejas o de
                    // servicios) se cae al costo promedio actual del producto.
                    if ($costoUnitario <= 0) {
                        $costoUnitario = (float) (DB::table('inventario_saldos')
                            ->where('producto_id', $original->producto_id)
                            ->orderByDesc('stock_actual')
                            ->value('costo_promedio') ?? 0);
                    }

                    $costoDevuelto += round($cantidad * $costoUnitario, 2);

                    try {
                        $this->inventario->ingresarStock(
                            productoId: $original->producto_id,
                            bodegaId:   $bodegaCuarentena->id,
                            cantidad:   $cantidad,
                            costoUnitario: $costoUnitario,
                            docTipo:    'NC',
                            docId:      $notaCredito->id,
                        );
                    } catch (\Throwable) {
                        // Ingreso de inventario no bloquea el flujo
                    }
                }
            }

            // Cruzar contra CxC pendiente
            $cxc = CuentaCobrar::where('factura_id', $factura->id)
                ->where('estado', 'pendiente')
                ->first();

            if ($cxc) {
                $nuevoSaldo = $cxc->saldo - $total;
                $cxc->update([
                    'saldo'  => max(0, $nuevoSaldo),
                    'estado' => $nuevoSaldo <= 0 ? 'cobrada' : $cxc->estado,
                ]);
            } else {
                $notaCredito->update(['genera_saldo_favor' => true]);
            }

            return $notaCredito;
        });

        try {
            // La contrapartida depende de cómo se pagó la factura original: si
            // fue a crédito se rebaja la CxC del cliente; si fue de contado sale
            // dinero de caja/banco. Antes siempre acreditaba Clientes, aunque la
            // venta hubiera sido en efectivo.
            $formaOriginal = (string) (DB::table('factura_pagos')
                ->where('factura_id', $factura->id)
                ->orderByDesc('valor')
                ->value('forma_pago') ?? 'credito');

            $asientoNC = $this->asiento->notaCreditoEmitida(
                empresaId:     $empresaId,
                notaCreditoId: $notaCredito->id,
                referencia:    $notaCredito->numero_completo,
                subtotal:      $subtotal,
                iva:           $totalIva,
                formaPago:     $formaOriginal,
                costoVenta:    $costoDevuelto,
                fecha:         $notaCredito->fecha_emision?->toDateString(),
            );
            $notaCredito->update(['asiento_id' => $asientoNC->id]);
        } catch (\Throwable $e) {
            \Log::warning("Contabilidad: NC {$notaCredito->numero_completo} sin asiento: {$e->getMessage()}");
            $this->asiento->notificarAsientoFallido(
                empresaId:  (int) $empresaId,
                tabla:      'notas_credito',
                registroId: $notaCredito->id,
                referencia: "Nota de crédito {$notaCredito->numero_completo}",
                mensaje:    $e->getMessage(),
            );
        }

        $this->auditoria->documento('crear', 'ventas', 'notas_credito', $notaCredito->id, "Nota de crédito {$notaCredito->numero_completo} emitida");

        return redirect()->route('ventas.notas-credito.show', $notaCredito->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Nota de crédito {$notaCredito->numero_completo} emitida correctamente."]);
    }

    public function show(NotaCredito $notaCredito)
    {
        $notaCredito->load(['factura', 'cliente', 'detalles']);

        return Inertia::render('Ventas/NotasCredito/Show', [
            'nota' => [
                'id'              => $notaCredito->id,
                'numero_completo' => $notaCredito->numero_completo ?? "NC-{$notaCredito->id}",
                'fecha'           => $notaCredito->fecha_emision?->toDateString(),
                'motivo'          => $notaCredito->motivo,
                'total'           => (float) $notaCredito->total,
                'estado_sri'      => $notaCredito->estado_sri,
                'factura'         => $notaCredito->factura ? [
                    'numero_completo' => $notaCredito->factura->numero_completo,
                    'fecha_emision'   => $notaCredito->factura->fecha_emision?->toDateString(),
                    'total'           => (float) $notaCredito->factura->total,
                ] : null,
                'cliente'         => $notaCredito->cliente ? [
                    'razon_social'   => $notaCredito->cliente->razon_social,
                    'identificacion' => $notaCredito->cliente->identificacion,
                ] : null,
                'detalles'        => $notaCredito->detalles->map(function ($d) {
                    $subtotal = (float) $d->precio_unitario * (float) $d->cantidad;
                    $valorIva = max(0, (float) $d->total - $subtotal);
                    $pctIva   = $subtotal > 0 ? (int) round($valorIva / $subtotal * 100) : 0;
                    return [
                        'id'              => $d->id,
                        'descripcion'     => $d->descripcion,
                        'cantidad'        => (float) $d->cantidad,
                        'precio_unitario' => (float) $d->precio_unitario,
                        'subtotal'        => $subtotal,
                        'porcentaje_iva'  => $pctIva,
                        'valor_iva'       => $valorIva,
                        'total'           => (float) $d->total,
                    ];
                }),
            ],
        ]);
    }

    public function enviarSri(NotaCredito $notaCredito)
    {
        // TODO: implementar ciclo SRI (XML + firma + webservice)
        // Pendiente — commit separado
        return response()->json(['message' => 'Funcionalidad SRI pendiente']);
    }
}
