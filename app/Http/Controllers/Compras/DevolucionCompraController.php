<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compra;
use App\Models\DevolucionCompra;
use App\Models\DevolucionCompraDetalle;
use App\Models\Proveedor;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

class DevolucionCompraController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

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

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $compras = Compra::where('empresa_id', $empresaId)
            ->where('estado', 'activa')
            ->orderByDesc('fecha_emision')
            ->get(['id', 'num_documento', 'proveedor_id'])
            ->map(fn($c) => [
                'id'           => $c->id,
                'num_documento'=> $c->num_documento,
                'proveedor_id' => $c->proveedor_id,
            ]);

        return Inertia::render('Compras/Devoluciones/Index', [
            'devoluciones' => $devoluciones,
            'proveedores'  => $proveedores,
            'compras'      => $compras,
            'filtros'      => $request->only(['estado', 'proveedor_id', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'proveedor_id'      => 'required|exists:proveedores,id',
            'compra_id'         => 'nullable|exists:compras,id',
            'num_documento'     => 'nullable|string|max:30',
            'fecha'             => 'required|date',
            'motivo'            => 'required|string|min:5|max:300',
            'detalles'          => 'required|array|min:1',
            'detalles.*.descripcion'    => 'required|string|max:200',
            'detalles.*.cantidad'       => 'required|numeric|min:0.001',
            'detalles.*.precio_unitario'=> 'required|numeric|min:0',
            'porcentaje_iva'    => 'integer|in:0,5,8,12,15',
        ]);

        $empresaId = session('empresa_activa_id');
        $pct = (int) ($request->porcentaje_iva ?? 15);

        DB::transaction(function () use ($request, $empresaId, $pct) {
            $subtotal = collect($request->detalles)->sum(fn($d) =>
                round((float)$d['cantidad'] * (float)$d['precio_unitario'], 4)
            );
            $iva   = round($subtotal * $pct / 100, 4);
            $total = round($subtotal + $iva, 4);

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
            $devolucion->update(['estado' => 'anulada']);
        });

        return back()->with('success', 'Devolución anulada correctamente.');
    }

    // ─── helpers privados para obtener cuentas contables ─────────────────────

    private function cuentaCxP(int $empresaId): int
    {
        return $this->cuentaParam('cta_cxp_proveedores', $empresaId)
            ?? $this->cuentaPorCodigos(['2.1.1', '2.1.01', '2.1.1.1'], $empresaId)
            ?? $this->primeraDelTipo('pasivo', $empresaId);
    }

    private function cuentaCompras(int $empresaId): int
    {
        return $this->cuentaParam('cta_compras', $empresaId)
            ?? $this->cuentaPorCodigos(['5.1', '5.1.1', '6.1.1'], $empresaId)
            ?? $this->primeraDelTipo('gasto', $empresaId);
    }

    private function cuentaIvaCobrar(int $empresaId): int
    {
        return $this->cuentaParam('cta_iva_credito_tributario', $empresaId)
            ?? $this->cuentaPorCodigos(['1.1.3.1', '1.1.2.3', '1.1.3'], $empresaId)
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
        return \App\Models\PlanCuenta::where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->where('permite_asientos', true)
            ->where('estado', true)
            ->value('id');
    }

    private function primeraDelTipo(string $tipo, int $empresaId): int
    {
        return \App\Models\PlanCuenta::where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->where('permite_asientos', true)
            ->where('estado', true)
            ->value('id') ?? 1;
    }
}
