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
     * Estados desde los que se factura una orden (02-10 §2.9). Una orden en «Garantía» NO se factura al
     * cliente: se cierra con cerrarGarantia() (§2.8).
     */
    private const ESTADOS_LIQUIDABLES = ['listo', 'entregado'];

    /** IVA que se aplica a la mano de obra; configurable por empresa (la regla del valor del servicio es DEC-052). */
    private function ivaServicioPct(int $empresaId): float
    {
        $v = DB::table('configuraciones')->where('empresa_id', $empresaId)
            ->where('clave', 'taller_servicio_iva_pct')->value('valor');

        return $v === null || $v === '' ? 15.0 : max(0.0, (float) $v);
    }

    /** Centro de costo Altamira FIX de la empresa (02-10 §2.9): toda factura y asiento de taller va ahí. */
    private function centroCostoFix(int $empresaId): ?int
    {
        $id = DB::table('centros_costo')->where('empresa_id', $empresaId)
            ->where('es_taller', true)->where('estado', true)->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    public function show(TallerOrdenTrabajo $orden): Response|RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $esGarantia = $orden->estado === 'garantia';
        if (!$esGarantia && !in_array($orden->estado, self::ESTADOS_LIQUIDABLES, true)) {
            return redirect()->route('taller.ordenes.show', $orden->id)
                ->with('flash', ['tipo' => 'error', 'mensaje' => 'La orden debe estar en estado "Listo" o "Entregado" para facturarse, o en "Garantía" para cerrar la garantía.']);
        }

        $orden->load([
            'ingreso.cliente', 'ingreso.equipo', 'tecnico',
            'repuestos' => fn($q) => $q->where('estado', 'reservado')->with('producto'),
        ]);

        $ccId = $this->centroCostoFix((int) $orden->empresa_id);

        return Inertia::render('Taller/Liquidacion/Show', [
            'orden'           => $orden,
            'iva_servicio'    => $this->ivaServicioPct((int) $orden->empresa_id),
            'centro_costo'    => $ccId ? DB::table('centros_costo')->where('id', $ccId)->value('nombre') : null,
            'empresa_factura' => DB::table('empresas')->where('id', $orden->empresa_id)->first(['razon_social', 'ruc']),
            'bancos'          => \App\Models\BancoCaja::where('empresa_id', $orden->empresa_id)
                ->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
        ]);
    }

    /**
     * Factura automática de la orden (02-10 §2.9). Dos tipos de línea: servicio técnico (PC-TAL-01) y
     * repuestos (PC-TAL-02). Se emite con el RUC de la empresa de la orden (UIO) y el centro de costo FIX.
     * El valor del servicio (DEC-052) y el momento de generarla (DEC-053) siguen pendientes: hoy el valor se
     * digita al facturar y la factura se genera con el botón cuando la orden está Lista.
     */
    public function liquidar(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $data = $request->validate([
            'costo_mano_obra' => 'required|numeric|min:0',
            'forma_pago'      => 'required|string|in:efectivo,transferencia,tarjeta,cheque,datafast',
            'banco_caja_id'   => 'nullable|integer|exists_empresa:bancos_cajas',
            'referencia'      => 'nullable|string|max:60',
            'num_cheque'      => 'nullable|string|max:30',
            'fecha_cheque'    => 'nullable|date',
            'observaciones'   => 'nullable|string',
        ]);

        if ($orden->estado === 'facturado') {
            return back()->withErrors(['error' => 'Esta orden ya fue facturada.']);
        }
        if ($orden->estado === 'garantia') {
            return back()->withErrors(['error' => 'Una orden en garantía no se factura al cliente: use "Cerrar garantía".']);
        }
        if (!in_array($orden->estado, self::ESTADOS_LIQUIDABLES, true)) {
            return back()->withErrors(['error' => 'La orden debe estar en estado "Listo" o "Entregado" para facturarse.']);
        }

        // Transferencia: banco y referencia obligatorios (02-04 §2.2). Cheque: queda en cartera hasta depositarlo (PC-VEN-13), sin banco.
        if ($data['forma_pago'] === 'transferencia' && empty($data['banco_caja_id'])) {
            return back()->withErrors(['error' => 'Elija el banco donde se recibe el cobro.'])->withInput();
        }
        if ($data['forma_pago'] === 'transferencia' && trim((string) ($data['referencia'] ?? '')) === '') {
            return back()->withErrors(['error' => 'Ingrese la referencia (N° de comprobante) de la transferencia.'])->withInput();
        }
        if ($data['forma_pago'] === 'cheque' && trim((string) ($data['num_cheque'] ?? '')) === '') {
            return back()->withErrors(['error' => 'Ingrese el número del cheque.'])->withInput();
        }
        if (!empty($data['banco_caja_id'])
            && !\App\Models\BancoCaja::where('id', $data['banco_caja_id'])->where('empresa_id', $orden->empresa_id)->exists()) {
            return back()->withErrors(['error' => 'El banco o caja elegido no pertenece a esta empresa.'])->withInput();
        }

        // Solo los repuestos vigentes (reservados); los devueltos por una anulación previa no se cobran.
        $orden->load([
            'repuestos' => fn($q) => $q->where('estado', 'reservado')->with('producto'),
            'ingreso.cliente',
        ]);

        $manoObra = round((float) $data['costo_mano_obra'], 2);
        if ($orden->repuestos->isEmpty() && $manoObra <= 0) {
            return back()->withErrors(['error' => 'La orden no tiene repuestos ni costo de mano de obra.']);
        }

        $empresaId = (int) $orden->empresa_id;

        $centroCostoId = $this->centroCostoFix($empresaId);
        if (!$centroCostoId) {
            return back()->withErrors(['error' => 'No hay un centro de costo de taller (Altamira FIX) configurado para esta empresa. El taller factura con el RUC de Altamira UIO.']);
        }

        $bodegaId = null;
        if ($orden->repuestos->isNotEmpty()) {
            try {
                $bodegaId = $this->bodegaTallerId();
            } catch (\RuntimeException $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        $ivaServicioPct = $this->ivaServicioPct($empresaId);

        // Contabilidad lista antes de operar: período abierto y parámetros PC-TAL-01 / PC-TAL-02
        try {
            $this->asiento->validarConfiguracion(
                $empresaId,
                AsientoService::codigosFacturaTaller([$data['forma_pago']], $manoObra > 0, $orden->repuestos->isNotEmpty(), true),
                now()->toDateString(),
            );
        } catch (\DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        try {
            $factura = DB::transaction(function () use ($orden, $data, $empresaId, $bodegaId, $manoObra, $ivaServicioPct, $centroCostoId) {
                // Bloqueo para evitar doble liquidación concurrente de la misma OT.
                $bloqueada = TallerOrdenTrabajo::lockForUpdate()->find($orden->id);
                if (!in_array($bloqueada->estado, self::ESTADOS_LIQUIDABLES, true)) {
                    throw new \RuntimeException('La orden ya no está disponible para facturar.');
                }

                $subtotalRepuestos = 0.0;
                $ivaRepuestos      = 0.0;
                $subtotal0         = 0.0;
                $subtotal15        = 0.0;
                foreach ($orden->repuestos as $rep) {
                    $sub = round((float) $rep->precio_venta * $rep->cantidad, 2);
                    $pct = (float) ($rep->producto?->porcentaje_iva ?? 15);
                    $subtotalRepuestos += $sub;
                    $ivaRepuestos      += round($sub * $pct / 100, 2);
                    $pct > 0 ? $subtotal15 += $sub : $subtotal0 += $sub;
                }
                $ivaServicio = round($manoObra * $ivaServicioPct / 100, 2);
                $ivaServicioPct > 0 ? $subtotal15 += $manoObra : $subtotal0 += $manoObra;
                $totalIva = round($ivaRepuestos + $ivaServicio, 2);
                $total    = round($subtotalRepuestos + $manoObra + $totalIva, 2);

                $cliente = $orden->ingreso->cliente;
                $numero = $this->secuencial->siguiente($empresaId, 'FAC');
                [$est, $pe, $sec] = explode('-', $numero);

                $factura = Factura::create([
                    'empresa_id'          => $empresaId,
                    'centro_costo_id'     => $centroCostoId,
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

                // Línea de repuestos (PC-TAL-02)
                foreach ($orden->repuestos as $rep) {
                    $subtotal = round((float) $rep->precio_venta * $rep->cantidad, 2);
                    $pctIva   = (float) ($rep->producto?->porcentaje_iva ?? 15);
                    FacturaDetalle::create([
                        'factura_id'      => $factura->id,
                        'producto_id'     => $rep->producto_id,
                        'descripcion'     => $rep->producto?->nombre ?? 'Repuesto',
                        'cantidad'        => $rep->cantidad,
                        'precio_unitario' => $rep->precio_venta,
                        'descuento_pct'   => 0,
                        'descuento_valor' => 0,
                        'subtotal'        => $subtotal,
                        'porcentaje_iva'  => $pctIva,
                        'valor_iva'       => round($subtotal * $pctIva / 100, 2),
                        'total'           => round($subtotal * (1 + $pctIva / 100), 2),
                    ]);
                }

                // Línea de servicio técnico (PC-TAL-01)
                if ($manoObra > 0) {
                    FacturaDetalle::create([
                        'factura_id'      => $factura->id,
                        'producto_id'     => null,
                        'descripcion'     => 'Servicio técnico — OT ' . ($orden->numero ?? $orden->id),
                        'cantidad'        => 1,
                        'precio_unitario' => $manoObra,
                        'descuento_pct'   => 0,
                        'descuento_valor' => 0,
                        'subtotal'        => $manoObra,
                        'porcentaje_iva'  => $ivaServicioPct,
                        'valor_iva'       => $ivaServicio,
                        'total'           => round($manoObra + $ivaServicio, 2),
                    ]);
                }

                $pagoFactura = FacturaPago::create([
                    'factura_id'    => $factura->id,
                    'forma_pago'    => $data['forma_pago'],
                    'valor'         => $total,
                    'banco_caja_id' => $data['forma_pago'] === 'cheque' ? null : ($data['banco_caja_id'] ?? null),
                    'referencia'    => $data['forma_pago'] === 'transferencia' ? ($data['referencia'] ?? null) : null,
                    'num_cheque'    => $data['forma_pago'] === 'cheque' ? ($data['num_cheque'] ?? null) : null,
                    'fecha_cheque'  => $data['forma_pago'] === 'cheque' ? ($data['fecha_cheque'] ?? now()->toDateString()) : null,
                ]);
                if ($data['forma_pago'] === 'cheque') {
                    \App\Models\ChequeRecibido::create([
                        'empresa_id' => $orden->empresa_id, 'cliente_id' => $cliente->id, 'factura_id' => $factura->id,
                        'factura_pago_id' => $pagoFactura->id, 'numero' => $data['num_cheque'], 'monto' => $total,
                        'fecha_recepcion' => now()->toDateString(), 'fecha_cheque' => $data['fecha_cheque'] ?? now()->toDateString(),
                        'estado' => 'en_cartera', 'created_by' => Auth::id(),
                    ]);
                }

                foreach ($orden->repuestos as $rep) {
                    $this->inventario->confirmarSalida($rep->producto_id, $bodegaId, 'taller_ot', $rep->id);
                    $rep->update(['estado' => 'usado']);
                }

                // Costo de los repuestos que salieron de bodega por esta orden (el kárdex registra TALLER_OT).
                $costoRepuestos = (float) DB::table('inventario_movimientos')
                    ->where('doc_tipo', 'TALLER_OT')
                    ->whereIn('doc_id', $orden->repuestos->pluck('id'))
                    ->where('tipo', 'salida')
                    ->sum('costo_total');

                // Asiento PC-TAL-01 / PC-TAL-02 dentro de la misma transacción: si falla, no queda factura sin asiento.
                $asientoFactura = $this->asiento->facturaTaller(
                    empresaId:         $empresaId,
                    facturaId:         $factura->id,
                    numeroFactura:     $factura->numero_completo,
                    subtotalServicio:  $manoObra,
                    subtotalRepuestos: $subtotalRepuestos,
                    iva:               $totalIva,
                    total:             $total,
                    formasPago:        [['forma' => $data['forma_pago'], 'monto' => $total, 'banco_caja_id' => $data['forma_pago'] === 'cheque' ? null : ($data['banco_caja_id'] ?? null)]],
                    costoRepuestos:    $costoRepuestos,
                    centroCostoId:     $centroCostoId,
                    fecha:             $factura->fecha_emision?->toDateString(),
                );
                $factura->update(['asiento_id' => $asientoFactura->id]);

                $orden->update([
                    'factura_id'      => $factura->id,
                    'asiento_id'      => $asientoFactura->id,
                    'costo_mano_obra' => $manoObra,
                    'costo_repuestos' => $subtotalRepuestos,
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

        // Bancos: igual que Ventas, el cobro entra a la caja/banco configurado. Nunca bloquea la factura.
        try {
            app(\App\Services\CobroBancoService::class)
                ->ingresosFactura($factura->fresh(['pagos']), $factura->fresh()->asiento_id);
        } catch (\Throwable $e) {
            \Log::warning("Bancos: cobro de factura {$factura->numero_completo} (Taller) no registrado: {$e->getMessage()}");
        }

        $this->auditoria->documento('crear', 'taller', 'liquidacion', $orden->id,
            "Orden {$orden->numero} facturada — factura {$factura->numero_completo}");

        return redirect()->route('ventas.facturas.show', $factura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => 'Orden facturada correctamente.']);
    }

    /**
     * Cierre de una orden en «Garantía» (02-10 §2.8): no genera factura al cliente. Los repuestos salen del
     * kárdex y su costo queda registrado en la orden (costo_garantia) para ver el costo de garantías por
     * producto. La cuenta contable y la recuperación con el proveedor son DEC-047 / PC-TAL-04 (pendiente):
     * por eso este cierre NO genera asiento; el costo queda solo en el kárdex y en la orden.
     */
    public function cerrarGarantia(TallerOrdenTrabajo $orden): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        if ($orden->estado !== 'garantia') {
            return back()->withErrors(['error' => 'Solo se puede cerrar la garantía de una orden en estado "Garantía".']);
        }
        if ($orden->garantia_cerrada_at) {
            return back()->withErrors(['error' => 'La garantía de esta orden ya fue cerrada.']);
        }

        $orden->load(['repuestos' => fn($q) => $q->where('estado', 'reservado')->with('producto')]);

        $bodegaId = null;
        if ($orden->repuestos->isNotEmpty()) {
            try {
                $bodegaId = $this->bodegaTallerId();
            } catch (\RuntimeException $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        try {
            DB::transaction(function () use ($orden, $bodegaId) {
                $bloqueada = TallerOrdenTrabajo::lockForUpdate()->find($orden->id);
                if ($bloqueada->estado !== 'garantia' || $bloqueada->garantia_cerrada_at) {
                    throw new \RuntimeException('La orden ya no está disponible para cerrar la garantía.');
                }

                foreach ($orden->repuestos as $rep) {
                    $this->inventario->confirmarSalida($rep->producto_id, $bodegaId, 'taller_ot', $rep->id);
                    $rep->update(['estado' => 'usado']);
                }

                $costo = (float) DB::table('inventario_movimientos')
                    ->where('doc_tipo', 'TALLER_OT')
                    ->whereIn('doc_id', $orden->repuestos->pluck('id'))
                    ->where('tipo', 'salida')
                    ->sum('costo_total');

                $orden->update([
                    'es_garantia'         => true,
                    'costo_garantia'      => round($costo, 2),
                    'garantia_cerrada_at' => now(),
                    'fecha_fin_real'      => now()->toDateString(),
                ]);
                $orden->ingreso()->update(['estado' => 4]);
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $this->auditoria->documento('editar', 'taller', 'garantias', $orden->id,
            "Garantía de la orden {$orden->numero} cerrada sin factura al cliente");

        return redirect()->route('taller.ordenes.show', $orden->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => 'Garantía cerrada: no se factura al cliente. El costo quedó registrado en la orden (la cuenta contable está pendiente de definir: DEC-047).']);
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

        // 02-10 §2.6: los repuestos salen solo de la categoría «Repuestos e Insumos de Taller»
        if (!OrdenTrabajoController::esRepuestoDeTaller($producto)) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'Solo se pueden agregar productos de la categoría "Repuestos e Insumos de Taller".']);
        }

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
