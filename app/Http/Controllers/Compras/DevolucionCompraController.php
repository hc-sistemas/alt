<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\CuentaPagar;
use App\Models\DevolucionCompra;
use App\Models\DevolucionCompraDetalle;
use App\Models\Proveedor;
use App\Services\AsientoService;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DevolucionCompraController extends Controller
{
    public function __construct(
        private AsientoService $asientoService,
        private InventarioServiceInterface $inventario,
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        // Carga bajo demanda: mismo patrón que Cuentas por Pagar/
        // Proveedores/Anticipos Proveedores — la query solo se ejecuta
        // cuando el usuario dispara una búsqueda explícita (botón lupa).
        $devoluciones = null;

        if ($request->boolean('buscado')) {
            $query = DevolucionCompra::with(['proveedor', 'compra'])
                ->where('empresa_id', $empresaId);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('proveedor_id')) {
                $query->where('proveedor_id', $request->proveedor_id);
            }
            if ($request->filled('fecha_desde')) {
                $query->where('fecha', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->where('fecha', '<=', $request->fecha_hasta);
            }
            if ($request->filled('buscar')) {
                $q = $request->buscar;
                $query->where(function ($qb) use ($q) {
                    $qb->whereHas('proveedor', fn($p) => $p->where('razon_social', 'ilike', "%{$q}%"))
                       ->orWhere('num_documento', 'ilike', "%{$q}%")
                       ->orWhere('motivo', 'ilike', "%{$q}%");
                });
            }

            $devoluciones = $query->orderByDesc('fecha')->get()
                ->map(fn($d) => [
                    'id'             => $d->id,
                    'proveedor'      => $d->proveedor?->razon_social,
                    'proveedor_id'   => $d->proveedor_id,
                    'compra_id'      => $d->compra_id,
                    'num_compra'     => $d->compra?->num_documento,
                    'num_documento'  => $d->num_documento,
                    'fecha'          => $d->fecha?->format('d/m/Y'),
                    'motivo'         => $d->motivo,
                    'estado'         => $d->estado,
                    'subtotal'       => $d->subtotal,
                    'iva'            => $d->iva,
                    'total'          => $d->total,
                ]);
        }

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        // Se incluye el saldo de la CxP de cada compra para que el
        // frontend pueda deshabilitar (con tooltip) las facturas ya
        // pagadas al 100% en el selector — evita que el usuario intente
        // una devolución que el candado del backend va a rechazar igual.
        $compras = Compra::where('empresa_id', $empresaId)
            ->where('estado', 'activa')
            ->with('cuentaPagar:id,compra_id,saldo')
            ->orderByDesc('fecha_emision')
            ->get(['id', 'num_documento', 'proveedor_id', 'total'])
            ->map(fn($c) => [
                'id'             => $c->id,
                'num_documento'  => $c->num_documento,
                'proveedor_id'   => $c->proveedor_id,
                'total'          => (float) $c->total,
                'saldo_cxp'      => $c->cuentaPagar ? (float) $c->cuentaPagar->saldo : null,
            ]);

        return Inertia::render('Compras/Devoluciones/Index', [
            'devoluciones' => $devoluciones,
            'proveedores'  => $proveedores,
            'compras'      => $compras,
            'filtros'      => $request->only(['estado', 'proveedor_id', 'fecha_desde', 'fecha_hasta', 'buscar']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'proveedor_id'              => 'required|exists:proveedores,id',
            'compra_id'                 => 'required|exists:compras,id',
            'num_documento'             => 'nullable|string|max:30',
            'fecha'                     => 'required|date',
            'motivo'                    => 'required|string|min:5|max:300',
            'porcentaje_iva'            => 'integer|in:0,5,8,12,15',
            'detalles'                  => 'required|array|min:1',
            'detalles.*.descripcion'    => 'required|string|max:200',
            'detalles.*.cantidad'       => 'required|numeric|min:0.001',
            'detalles.*.precio_unitario'=> 'required|numeric|min:0',
            'detalles.*.producto_id'    => 'nullable|integer|exists:productos,id',
        ]);

        $empresaId = session('empresa_activa_id');
        $pct = (int) ($request->porcentaje_iva ?? 15);

        $subtotal = collect($request->detalles)->sum(fn($d) =>
            round((float)$d['cantidad'] * (float)$d['precio_unitario'], 4)
        );
        $iva   = round($subtotal * $pct / 100, 4);
        $total = round($subtotal + $iva, 4);

        $compra = Compra::findOrFail($request->compra_id);

        // Candado (b): no se puede devolver más de lo que se compró —
        // suma lo ya devuelto antes contra esta misma compra (sin contar
        // devoluciones anuladas, que no cuentan como devolución real) más
        // esta nueva devolución, contra el total original de la factura.
        $totalDevueltoPrevio = (float) DevolucionCompra::where('compra_id', $compra->id)
            ->where('estado', '!=', 'anulada')
            ->sum('total');

        if (round($totalDevueltoPrevio + $total, 4) > round((float) $compra->total, 4) + 0.0001) {
            return back()->with('error',
                "No se puede devolver más de lo comprado. Total de la factura: \$" . number_format((float) $compra->total, 2) .
                '. Ya devuelto: $' . number_format($totalDevueltoPrevio, 2) .
                '. Esta devolución: $' . number_format($total, 2) . '.'
            )->withInput();
        }

        // Candado (c) y (d): la Cuenta por Pagar de esta compra debe tener
        // saldo suficiente para reversar. Si ya está pagada al 100% (saldo
        // 0) o el monto a devolver supera lo que queda pendiente, se
        // bloquea — no se genera saldo a favor automático, el usuario debe
        // anular el pago en Bancos primero (mismo criterio que el candado
        // de inmutabilidad CxP-02).
        $cuentaPagar = CuentaPagar::where('compra_id', $compra->id)->first();

        if ($cuentaPagar) {
            if ((float) $cuentaPagar->saldo <= 0.0001) {
                return back()->with('error',
                    'Esta factura ya fue pagada en su totalidad. Para procesar la devolución, primero anule el ' .
                    'pago correspondiente en el módulo de Bancos para liberar la cuenta por pagar.'
                )->withInput();
            }

            if ($total > (float) $cuentaPagar->saldo + 0.0001) {
                return back()->with('error',
                    'El monto a devolver ($' . number_format($total, 2) . ') supera el saldo pendiente de la cuenta ' .
                    'por pagar ($' . number_format((float) $cuentaPagar->saldo, 2) . '). Para devolver más de lo que ' .
                    'queda pendiente, primero anule el pago correspondiente en el módulo de Bancos.'
                )->withInput();
            }
        }

        DB::transaction(function () use ($request, $empresaId, $subtotal, $iva, $total, $cuentaPagar) {
            $dev = DevolucionCompra::create([
                'empresa_id'    => $empresaId,
                'compra_id'     => $request->compra_id,
                'proveedor_id'  => $request->proveedor_id,
                'num_documento' => $request->num_documento,
                'fecha'         => $request->fecha,
                'motivo'        => $request->motivo,
                'estado'        => 'pendiente',
                'subtotal'      => $subtotal,
                'iva'           => $iva,
                'total'         => $total,
                'created_by'    => Auth::id(),
            ]);

            // Bodega: usar la de la compra de origen o la primera disponible
            $bodegaId = null;
            if ($request->compra_id) {
                $bodegaId = Compra::where('id', $request->compra_id)->value('bodega_id');
            }
            if (!$bodegaId) {
                $bodegaId = \App\Models\Bodega::where('empresa_id', $empresaId)
                    ->where('tipo', 'general')->value('id');
            }

            foreach ($request->detalles as $d) {
                $sub = round((float)$d['cantidad'] * (float)$d['precio_unitario'], 4);
                DevolucionCompraDetalle::create([
                    'devolucion_id'   => $dev->id,
                    'producto_id'     => $d['producto_id'] ?? null,
                    'descripcion'     => $d['descripcion'],
                    'cantidad'        => (float) $d['cantidad'],
                    'precio_unitario' => (float) $d['precio_unitario'],
                    'subtotal'        => $sub,
                ]);

                // Descontar del inventario cuando hay producto y bodega
                if (!empty($d['producto_id']) && $bodegaId) {
                    try {
                        $this->inventario->egresarStock(
                            productoId:  (int) $d['producto_id'],
                            bodegaId:    (int) $bodegaId,
                            cantidad:    (float) $d['cantidad'],
                            docTipo:     'DEVOLUCION',
                            docId:       $dev->id,
                            observacion: "Devolución compra #{$dev->id}: {$dev->motivo}",
                        );
                    } catch (\Exception $e) {
                        Log::warning("Inventario devolución #{$dev->id} producto {$d['producto_id']}: {$e->getMessage()}");
                    }
                }
            }

            // Ajuste de la Cuenta por Pagar: a diferencia de la reversión
            // de inventario y el asiento contable (que no bloquean la
            // operación si fallan, ver try/catch de arriba y abajo), este
            // ajuste va SIN try/catch a propósito — es la corrección real
            // que motivó esta tarea (el saldo de CxP no se estaba
            // ajustando), así que si falla debe revertir toda la
            // transacción, no quedar silenciosamente sin aplicar.
            if ($cuentaPagar) {
                $nuevoSaldo  = max(0, round((float) $cuentaPagar->saldo - $total, 4));
                $nuevoEstado = $nuevoSaldo <= 0.0001 ? 'pagada' : 'parcial';
                $cuentaPagar->update(['saldo' => $nuevoSaldo, 'estado' => $nuevoEstado]);
            }

            // Asiento contable automático (Nota Crédito Proveedor)
            try {
                $asiento = $this->asientoService->crear(
                    empresaId:     (int) $empresaId,
                    concepto:      "Devolución compra {$dev->num_documento} — {$dev->motivo}",
                    partidas: [
                        // Por pagar proveedor disminuye (Debe)
                        ['cuenta_id' => $this->cuentaCxP((int)$empresaId), 'debe' => $total, 'haber' => 0,
                         'descripcion' => "Devolución prov. {$request->num_documento}"],
                        // Inventario o gasto disminuye (Haber)
                        ['cuenta_id' => $this->cuentaCompras((int)$empresaId), 'debe' => 0, 'haber' => $subtotal,
                         'descripcion' => "Devolución prov. {$request->num_documento}"],
                        // IVA por pagar reduce (si hay IVA)
                        ...($iva > 0 ? [['cuenta_id' => $this->cuentaIvaCobrar((int)$empresaId), 'debe' => 0, 'haber' => $iva,
                                         'descripcion' => "IVA devolución {$request->num_documento}"]] : []),
                    ],
                    documentoTipo: 'DEVOLUCION_COMPRA',
                    documentoId:   $dev->id,
                    documentoRef:  $dev->num_documento ?? "DEV-{$dev->id}",
                    esAutomatico:  true,
                    fecha:         $request->fecha,
                );
                $dev->update(['asiento_id' => $asiento->id, 'estado' => 'procesada']);
            } catch (\Exception $e) {
                Log::warning("Asiento devolución compra fallido: " . $e->getMessage());
            }
        });

        return back()->with('success', 'Devolución de compra registrada correctamente.');
    }

    public function anular(Request $request, DevolucionCompra $devolucion): RedirectResponse
    {
        if ($devolucion->estado === 'anulada') {
            return back()->with('error', 'Esta devolución ya está anulada.');
        }

        $request->validate(['motivo' => 'required|string|min:5|max:200']);

        DB::transaction(function () use ($request, $devolucion) {
            if ($devolucion->asiento_id && $devolucion->asiento) {
                try {
                    $this->asientoService->anular(
                        $devolucion->asiento,
                        "Anulación devolución #{$devolucion->id}: {$request->motivo}"
                    );
                } catch (\Exception $e) {
                    Log::warning("Anulación asiento devolución: " . $e->getMessage());
                }
            }

            // Simétrico al ajuste de CxP que hace store(): si esta
            // devolución redujo el saldo de la Cuenta por Pagar, anularla
            // debe restaurarlo (tope: el monto original de la CxP, para no
            // pasarse si hubo otro movimiento intermedio).
            $cuentaPagar = CuentaPagar::where('compra_id', $devolucion->compra_id)->first();
            if ($cuentaPagar) {
                $nuevoSaldo = min(
                    (float) $cuentaPagar->monto,
                    round((float) $cuentaPagar->saldo + (float) $devolucion->total, 4)
                );
                $nuevoEstado = $nuevoSaldo <= 0.0001
                    ? 'pagada'
                    : ($nuevoSaldo >= (float) $cuentaPagar->monto - 0.0001 ? 'pendiente' : 'parcial');
                $cuentaPagar->update(['saldo' => $nuevoSaldo, 'estado' => $nuevoEstado]);
            }

            $devolucion->update(['estado' => 'anulada']);
        });

        return back()->with('success', 'Devolución anulada correctamente.');
    }

    // ─── helpers privados para obtener cuentas contables ─────────────────────

    // Nota: los códigos de parámetro y de respaldo aquí deben coincidir con los que
    // usa el resto del sistema (AsientoService::FALLBACK_PLAN) — el plan de cuentas
    // real tiene códigos duplicados de una migración legacy (ej. "2.1.1.1" y
    // "2.1.1.01" son ambos "Proveedores Locales", pero solo el segundo tiene
    // actividad real). Los códigos de respaldo genéricos usados antes ("2.1.1",
    // "1.1.3.1", "5.1") coincidían por accidente con cuentas de otro concepto
    // (Clientes Locales, Envíos) por esa duplicación.
    private function cuentaCxP(int $empresaId): int
    {
        return $this->cuentaParam('cta_proveedores_locales', $empresaId)
            ?? $this->cuentaPorCodigos(['2.1.1.01'], $empresaId)
            ?? $this->primeraDelTipo('pasivo', $empresaId);
    }

    private function cuentaCompras(int $empresaId): int
    {
        return $this->cuentaParam('cta_inventario_mercaderia', $empresaId)
            ?? $this->cuentaPorCodigos(['1.1.4.01'], $empresaId)
            ?? $this->primeraDelTipo('activo', $empresaId);
    }

    private function cuentaIvaCobrar(int $empresaId): int
    {
        return $this->cuentaParam('cta_iva_compras', $empresaId)
            ?? $this->cuentaPorCodigos(['1.1.5.01'], $empresaId)
            ?? $this->primeraDelTipo('activo', $empresaId);
    }

    private function cuentaParam(string $codigo, int $empresaId): ?int
    {
        return DB::table('parametros_contables')
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo)
            ->value('cuenta_id');
    }

    private function cuentaPorCodigos(array $codigos, int $empresaId): ?int
    {
        return \App\Models\PlanCuenta::whereIn('codigo', $codigos)
            ->where('permite_asientos', true)
            ->where('estado', true)
            ->value('id');
    }

    private function primeraDelTipo(string $tipo, int $empresaId): int
    {
        return \App\Models\PlanCuenta::where('tipo', $tipo)
            ->where('permite_asientos', true)
            ->where('estado', true)
            ->value('id') ?? 1;
    }
}
