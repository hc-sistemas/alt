<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Taller\Concerns\ResuelveBodegaTaller;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaPago;
use App\Models\Producto;
use App\Models\TallerOrdenTrabajo;
use App\Models\TallerOtRepuesto;
use App\Services\AsientoService;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use App\Services\SecuencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LiquidacionController extends Controller
{
    use ResuelveBodegaTaller;

    public function __construct(
        private AuditoriaService $auditoria,
        private InventarioServiceInterface $inventario,
        private SecuencialService $secuencial,
        private AsientoService $asiento,
    ) {}

    /**
     * Estados de OT desde los que se puede liquidar/facturar. 'garantia' se
     * agregó para D3 (CHECKLIST_ERRORES_COMPLICACIONES.md): antes una orden en
     * garantía no se podía liquidar nunca. Decisión: la mano de obra se cobra
     * normal; solo los repuestos van a precio de costo (ver liquidar()).
     */
    private const ESTADOS_LIQUIDABLES = ['listo', 'entregado', 'garantia'];

    public function show(TallerOrdenTrabajo $orden): Response
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        if (!in_array($orden->estado, self::ESTADOS_LIQUIDABLES, true)) {
            return redirect()->route('taller.ordenes.show', $orden->id)
                ->with('flash', ['tipo' => 'error', 'mensaje' => 'La orden debe estar en estado "Listo", "Entregado" o "Garantía" para liquidarse.']);
        }

        $orden->load([
            'ingreso.cliente', 'ingreso.equipo', 'tecnico',
            'repuestos' => fn($q) => $q->where('estado', 'reservado')->with('producto'),
        ]);

        return Inertia::render('Taller/Liquidacion/Show', [
            'orden' => $orden,
        ]);
    }

    public function liquidar(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $data = $request->validate([
            'costo_mano_obra' => 'required|numeric|min:0',
            'forma_pago'      => 'required|string|in:efectivo,transferencia,tarjeta,cheque,datafast',
            'observaciones'   => 'nullable|string',
        ]);

        if ($orden->estado === 'facturado') {
            return back()->withErrors(['error' => 'Esta orden ya fue facturada.']);
        }

        if (!in_array($orden->estado, self::ESTADOS_LIQUIDABLES, true)) {
            return back()->withErrors(['error' => 'La orden debe estar en estado "Listo", "Entregado" o "Garantía" para liquidarse.']);
        }

        // Solo los repuestos vigentes (reservados); los devueltos por una anulación previa no se cobran.
        $orden->load([
            'repuestos' => fn($q) => $q->where('estado', 'reservado')->with('producto'),
            'ingreso.cliente',
        ]);

        if ($orden->repuestos->isEmpty() && (float) $data['costo_mano_obra'] === 0.0) {
            return back()->withErrors(['error' => 'La orden no tiene repuestos ni costo de mano de obra.']);
        }

        $empresaId = session('empresa_activa_id');

        $bodegaId = null;
        if ($orden->repuestos->isNotEmpty()) {
            try {
                $bodegaId = $this->bodegaTallerId();
            } catch (\RuntimeException $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        try {
            $factura = DB::transaction(function () use ($orden, $data, $empresaId, $bodegaId) {
                // Bloqueo para evitar doble liquidación concurrente de la misma OT.
                $bloqueada = TallerOrdenTrabajo::lockForUpdate()->find($orden->id);
                if (!in_array($bloqueada->estado, self::ESTADOS_LIQUIDABLES, true)) {
                    throw new \RuntimeException('La orden ya no está disponible para liquidar.');
                }

                // D3: en garantía, los repuestos van a precio de costo (margen
                // 0); la mano de obra se cobra normal. `precio_venta` guardado
                // en el repuesto no se toca — solo se usa costo_unitario para
                // ESTA factura.
                $esGarantia = $orden->estado === 'garantia';

                $subtotal0 = 0;
                $subtotal15 = 0;
                $totalIva = 0;

                foreach ($orden->repuestos as $rep) {
                    $precioLinea = $esGarantia ? (float) $rep->costo_unitario : (float) $rep->precio_venta;
                    $subtotal = $precioLinea * $rep->cantidad;
                    $porcentajeIva = $rep->producto?->porcentaje_iva ?? 15;
                    $iva = $subtotal * ($porcentajeIva / 100);
                    if ($porcentajeIva > 0) {
                        $subtotal15 += $subtotal;
                    } else {
                        $subtotal0 += $subtotal;
                    }
                    $totalIva += $iva;
                }

                // Mano de obra: sin IVA por defecto
                $subtotal0 += (float) $data['costo_mano_obra'];
                $total = $subtotal0 + $subtotal15 + $totalIva;

                $cliente = $orden->ingreso->cliente;
                $numero = $this->secuencial->siguiente($empresaId, 'FAC');
                [$est, $pe, $sec] = explode('-', $numero);

                $factura = Factura::create([
                    'empresa_id'          => $empresaId,
                    'cliente_id'          => $cliente->id,
                    'usuario_id'          => Auth::id(),
                    'establecimiento'     => $est,
                    'punto_emision'       => $pe,
                    'secuencial'          => ltrim($sec, '0') ?: '1',
                    'numero_completo'     => $numero,
                    'fecha_emision'       => now()->toDateString(),
                    'hora_emision'        => now()->toTimeString(),
                    'estado_sri'          => 'pendiente',
                    'tipo_identificacion' => $cliente->tipo_identificacion,
                    'identificacion'      => $cliente->identificacion,
                    'razon_social'        => $cliente->razon_social,
                    'email_cliente'       => $cliente->email,
                    'telefono_cliente'    => $cliente->telefono,
                    'direccion_cliente'   => $cliente->direccion,
                    'subtotal_0'          => $subtotal0,
                    'subtotal_15'         => $subtotal15,
                    'descuento_total'     => 0,
                    'total_iva'           => $totalIva,
                    'total'               => $total,
                    'observaciones'       => $data['observaciones'] ?? null,
                    'tipo'                => 3, // tipo 3 = taller
                    'estado'              => 'activa',
                    'email_enviado'       => false,
                ]);

                foreach ($orden->repuestos as $rep) {
                    $precioLinea = $esGarantia ? (float) $rep->costo_unitario : (float) $rep->precio_venta;
                    $subtotal = $precioLinea * $rep->cantidad;
                    $pctIva = $rep->producto?->porcentaje_iva ?? 15;
                    FacturaDetalle::create([
                        'factura_id'      => $factura->id,
                        'producto_id'     => $rep->producto_id,
                        'descripcion'     => ($esGarantia ? '[GARANTÍA] ' : '') . ($rep->producto?->nombre ?? 'Repuesto'),
                        'cantidad'        => $rep->cantidad,
                        'precio_unitario' => $precioLinea,
                        'descuento_pct'   => 0,
                        'descuento_valor' => 0,
                        'subtotal'        => $subtotal,
                        'porcentaje_iva'  => $pctIva,
                        'valor_iva'       => $subtotal * ($pctIva / 100),
                        'total'           => $subtotal + $subtotal * ($pctIva / 100),
                    ]);
                }

                if ((float) $data['costo_mano_obra'] > 0) {
                    FacturaDetalle::create([
                        'factura_id'      => $factura->id,
                        'producto_id'     => null,
                        'descripcion'     => 'Mano de obra — OT ' . ($orden->numero ?? $orden->id),
                        'cantidad'        => 1,
                        'precio_unitario' => (float) $data['costo_mano_obra'],
                        'descuento_pct'   => 0,
                        'descuento_valor' => 0,
                        'subtotal'        => (float) $data['costo_mano_obra'],
                        'porcentaje_iva'  => 0,
                        'valor_iva'       => 0,
                        'total'           => (float) $data['costo_mano_obra'],
                    ]);
                }

                FacturaPago::create([
                    'factura_id' => $factura->id,
                    'forma_pago' => $data['forma_pago'],
                    'valor'      => $total,
                ]);

                foreach ($orden->repuestos as $rep) {
                    $this->inventario->confirmarSalida(
                        $rep->producto_id,
                        $bodegaId,
                        'taller_ot',
                        $rep->id
                    );
                    $rep->update(['estado' => 'usado']);
                }

                $orden->update([
                    'factura_id'      => $factura->id,
                    'costo_mano_obra' => (float) $data['costo_mano_obra'],
                    'costo_total'     => $total,
                    'fecha_fin_real'  => now()->toDateString(),
                    'estado'          => 'facturado',
                ]);
                $orden->ingreso->update(['estado' => 4]);

                return $factura;
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        // Asiento contable automático — el liquidar una orden de Taller genera una
        // Factura real (ventas + inventario + pago), pero nunca pasaba por
        // AsientoService: la venta quedaba invisible para la contabilidad.
        try {
            // Costo de los repuestos que salieron de bodega por esta orden. En
            // Taller el kárdex registra las salidas como TALLER_OT con doc_id =
            // id del repuesto, no como FACTURA, así que se suman por ahí.
            $costoRepuestos = (float) DB::table('inventario_movimientos')
                ->where('doc_tipo', 'TALLER_OT')
                ->whereIn('doc_id', $orden->repuestos()->pluck('id'))
                ->where('tipo', 'salida')
                ->sum('costo_total');

            $asientoFactura = $this->asiento->facturaAutorizada(
                empresaId:     $empresaId,
                facturaId:     $factura->id,
                numeroFactura: $factura->numero_completo,
                subtotal:      (float) $factura->subtotal_0 + (float) $factura->subtotal_15,
                iva:           (float) $factura->total_iva,
                total:         (float) $factura->total,
                formaPago:     $data['forma_pago'],
                costoVenta:    $costoRepuestos,
                fecha:         $factura->fecha_emision?->toDateString(),
            );
            $factura->update(['asiento_id' => $asientoFactura->id]);
            $orden->update(['asiento_id' => $asientoFactura->id]);
        } catch (\Throwable $e) {
            \Log::warning("Contabilidad: liquidación Taller {$factura->numero_completo} sin asiento: {$e->getMessage()}");
            $this->asiento->notificarAsientoFallido(
                empresaId:  (int) $empresaId,
                tabla:      'facturas',
                registroId: $factura->id,
                referencia: "Liquidación Taller — factura {$factura->numero_completo}",
                mensaje:    $e->getMessage(),
            );
        }

        // Bancos: igual que Ventas, el cobro entra a la caja/banco configurado.
        // Nunca bloquea la liquidación.
        try {
            app(\App\Services\CobroBancoService::class)
                ->ingresosFactura($factura->fresh(['pagos']), $factura->fresh()->asiento_id);
        } catch (\Throwable $e) {
            \Log::warning("Bancos: cobro de factura {$factura->numero_completo} (Taller) no registrado: {$e->getMessage()}");
        }

        $this->auditoria->documento('crear', 'taller', 'liquidacion', $orden->id,
            "Orden {$orden->numero} liquidada — factura {$factura->numero_completo}");

        return redirect()->route('ventas.facturas.show', $factura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => 'Orden liquidada y factura generada correctamente.']);
    }

    public function agregarRepuesto(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $data = $request->validate([
            'producto_id'  => 'required|integer|exists:productos,id',
            'cantidad'     => 'required|integer|min:1',
            'numero_serie' => 'nullable|string',
            'precio_venta' => 'required|numeric|min:0',
        ]);

        if ($orden->estado !== 'en_proceso') {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'Solo se pueden agregar repuestos a órdenes en proceso.']);
        }

        $producto = Producto::where('estado', true)->findOrFail($data['producto_id']);

        try {
            $bodegaId = $this->bodegaTallerId();
        } catch (\RuntimeException $e) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
        }

        try {
            DB::transaction(function () use ($orden, $data, $producto, $bodegaId) {
                $repuesto = TallerOtRepuesto::create([
                    'orden_id'       => $orden->id,
                    'producto_id'    => $data['producto_id'],
                    'numero_serie'   => $data['numero_serie'] ?? null,
                    'cantidad'       => $data['cantidad'],
                    'costo_unitario' => $producto->costo,
                    'precio_venta'   => $data['precio_venta'],
                    'estado'         => 'reservado',
                ]);

                $this->inventario->reservarStock($data['producto_id'], $bodegaId, $data['cantidad'], 'taller_ot', $repuesto->id);

                $this->recalcularCostos($orden);
            });
        } catch (\RuntimeException $e) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
        }

        $this->auditoria->documento('crear', 'taller', 'ot_repuestos', $orden->id,
            "Repuesto {$producto->nombre} agregado a la orden " . ($orden->numero ?? $orden->id));

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => 'Repuesto agregado correctamente.']);
    }

    public function quitarRepuesto(TallerOrdenTrabajo $orden, TallerOtRepuesto $repuesto): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);
        abort_if((int) $repuesto->orden_id !== (int) $orden->id, 404);

        if ($repuesto->estado !== 'reservado' || $orden->estado === 'facturado') {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'Solo se pueden quitar repuestos reservados de una orden no facturada.']);
        }

        try {
            $bodegaId = $this->bodegaTallerId();
        } catch (\RuntimeException $e) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);
        }

        DB::transaction(function () use ($orden, $repuesto, $bodegaId) {
            $this->inventario->liberarReserva((int) $repuesto->producto_id, $bodegaId, (float) $repuesto->cantidad);
            $repuesto->delete();
            $this->recalcularCostos($orden);
        });

        $this->auditoria->documento('eliminar', 'taller', 'ot_repuestos', $orden->id,
            "Repuesto #{$repuesto->id} quitado de la orden " . ($orden->numero ?? $orden->id));

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => 'Repuesto quitado y reserva liberada.']);
    }

    private function recalcularCostos(TallerOrdenTrabajo $orden): void
    {
        $orden->costo_repuestos = (float) $orden->repuestos()->where('estado', 'reservado')
            ->selectRaw('COALESCE(SUM(precio_venta * cantidad), 0) as t')->value('t');
        $orden->costo_total = (float) $orden->costo_mano_obra + (float) $orden->costo_repuestos;
        $orden->save();
    }

    public function saldoDisponible(Request $request, TallerOrdenTrabajo $orden): JsonResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $request->validate([
            'producto_id' => 'required|integer',
        ]);

        try {
            $bodegaId = $this->bodegaTallerId();
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $disponible = $this->inventario->getSaldoDisponible(
            (int) $request->input('producto_id'),
            $bodegaId
        );

        return response()->json(['disponible' => $disponible]);
    }
}
