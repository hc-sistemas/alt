<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Empresa;
use App\Models\InventarioMovimiento;
use App\Models\InventarioSaldo;
use App\Models\Producto;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class KardexController extends Controller
{
    public function __construct(
        private InventarioServiceInterface $inventario,
        private AuditoriaService $auditoria
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $buscar    = $request->string('buscar')->trim()->toString();
        $bodegaId  = $request->integer('bodega_id') ?: null;

        $resultados = [];

        $productosPaginados = Producto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->when($buscar !== '', fn($q) => $q->where(fn($q2) => $q2
                ->where('codigo', 'ilike', "%{$buscar}%")
                ->orWhere('nombre', 'ilike', "%{$buscar}%")
            ))
            ->when(
                $bodegaId || $request->fecha_desde || $request->fecha_hasta || $request->tipo,
                fn($q) => $q->whereExists(fn($sub) => $sub
                    ->from('inventario_movimientos')
                    ->whereColumn('producto_id', 'productos.id')
                    ->when($bodegaId, fn($s) => $s->where('bodega_id', $bodegaId))
                    ->when($request->fecha_desde, fn($s) => $s->whereDate('created_at', '>=', $request->fecha_desde))
                    ->when($request->fecha_hasta, fn($s) => $s->whereDate('created_at', '<=', $request->fecha_hasta))
                    ->when($request->tipo, fn($s) => $s->where('tipo', $request->tipo))
                )
            )
            ->orderByRaw('(EXISTS (SELECT 1 FROM inventario_movimientos WHERE producto_id = productos.id)) DESC')
            ->orderBy('nombre')
            ->paginate(5)
            ->withQueryString();

        $productosPage = $productosPaginados->getCollection();
        $productoIds   = $productosPage->pluck('id')->all();

        if (count($productoIds) > 0) {
            $saldosAnteriores = [];
            if ($request->fecha_desde) {
                $saldoQuery = InventarioMovimiento::query()
                    ->whereIn('producto_id', $productoIds)
                    ->whereDate('created_at', '<', $request->fecha_desde)
                    ->when($bodegaId, fn($q) => $q->where('bodega_id', $bodegaId));

                $saldoRaw = "producto_id, COALESCE(SUM(CASE
                    WHEN tipo = 'entrada' THEN cantidad
                    WHEN tipo = 'salida' THEN -cantidad
                    ELSE 0 END), 0) as saldo";

                $saldosAnteriores = $saldoQuery
                    ->selectRaw($saldoRaw)
                    ->groupBy('producto_id')
                    ->pluck('saldo', 'producto_id')
                    ->map(fn($v) => (float) $v)
                    ->all();
            }

            $todosMovimientos = InventarioMovimiento::with(['bodega', 'usuario'])
                ->whereIn('producto_id', $productoIds)
                ->when($bodegaId, fn($q) => $q->where('bodega_id', $bodegaId))
                ->when($request->fecha_desde, fn($q) => $q->whereDate('created_at', '>=', $request->fecha_desde))
                ->when($request->fecha_hasta, fn($q) => $q->whereDate('created_at', '<=', $request->fecha_hasta))
                ->when($request->tipo, fn($q) => $q->where('tipo', $request->tipo))
                ->orderBy('created_at', 'asc')
                ->orderBy('id', 'asc')
                ->get()
                ->groupBy('producto_id');

            foreach ($productosPage as $producto) {
                $movs = $todosMovimientos->get($producto->id, collect());
                $saldoAnterior = $saldosAnteriores[$producto->id] ?? 0.0;
                $saldoActual   = $saldoAnterior;

                foreach ($movs as $m) {
                    $esIngreso = $this->determinarEsIngreso($m, $bodegaId);
                    $delta = $esIngreso === true
                        ? (float) $m->cantidad
                        : ($esIngreso === false ? -((float) $m->cantidad) : 0.0);

                    $m->saldo_anterior  = $saldoActual;
                    $saldoActual       += $delta;
                    $m->saldo_posterior = $saldoActual;
                    $m->es_ingreso      = $esIngreso;
                    $m->tipo_descriptivo = $m->tipo ? $this->tipoDescriptivo($m->tipo, $m->doc_tipo, $esIngreso) : 'MOVIMIENTO';
                }

                $resultados[] = [
                    'producto'       => $producto,
                    'movimientos'    => $movs->values()->toArray(),
                    'saldo_anterior' => $saldoAnterior,
                ];
            }
        }

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Kardex/Index', [
            'resultados'          => $resultados,
            'productos_paginados' => $productosPaginados,
            'bodegas'             => $bodegas,
            'filters'             => $request->only(['buscar', 'bodega_id', 'fecha_desde', 'fecha_hasta', 'tipo']),
        ]);
    }

    private function determinarEsIngreso(InventarioMovimiento $movimiento, ?int $bodegaId): ?bool
    {
        return match ($movimiento->tipo) {
            'entrada' => true,
            'salida'  => false,
            default   => null,
        };
    }

    private function tipoDescriptivo(?string $tipoMovimiento, ?string $docTipo, ?bool $esIngreso): string
    {
        $doc = $docTipo ? strtolower($docTipo) : '';

        return match ($tipoMovimiento) {
            'entrada' => match (true) {
                str_contains($doc, 'compra'), str_contains($doc, 'factura'), $doc === '' => 'INGRESO DE COMPRA',
                str_contains($doc, 'ajuste') => 'INGRESO DE AJUSTE',
                str_contains($doc, 'importacion') => 'INGRESO DE IMPORTACIÓN',
                str_contains($doc, 'traslado') => 'INGRESO DE TRANSFERENCIA',
                str_contains($doc, 'prefactura'), str_contains($doc, 'proforma'), str_contains($doc, 'produccion') => 'INGRESO DE PRODUCCIÓN',
                default => 'INGRESO',
            },
            'salida' => match (true) {
                str_contains($doc, 'factura'), str_contains($doc, 'venta'), str_contains($doc, 'prefactura'), str_contains($doc, 'proforma') => 'EGRESO POR VENTAS',
                str_contains($doc, 'ajuste') => 'EGRESO POR AJUSTE',
                str_contains($doc, 'traslado') => 'EGRESO TRANSFERENCIA',
                default => 'EGRESO',
            },
            'traslado' => match ($esIngreso) {
                true  => 'INGRESO DE TRANSFERENCIA',
                false => 'EGRESO TRANSFERENCIA',
                null  => 'TRASLADO',
            },
            'ajuste' => match ($esIngreso) {
                true  => 'INGRESO POR AJUSTE',
                false => 'EGRESO POR AJUSTE',
                null  => 'AJUSTE',
            },
            'reserva'          => 'RESERVA',
            'reserva_liberada' => 'RESERVA LIBERADA',
            default            => strtoupper($tipoMovimiento),
        };
    }

    public function saldos(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $saldos = InventarioSaldo::with(['producto', 'bodega'])
            ->join('productos', 'inventario_saldos.producto_id', '=', 'productos.id')
            ->join('bodegas', 'inventario_saldos.bodega_id', '=', 'bodegas.id')
            ->where('productos.empresa_id', $empresaId)
            ->when($request->bodega_id, fn($q) => $q->where('inventario_saldos.bodega_id', $request->bodega_id))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('productos.codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('productos.nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->boolean('solo_criticos'), fn($q) =>
                $q->whereColumn('inventario_saldos.stock_actual', '<=', 'productos.stock_minimo')
            )
            ->select([
                'inventario_saldos.*',
                'productos.codigo as producto_codigo',
                'productos.nombre as producto_nombre',
                'productos.stock_minimo as producto_stock_minimo',
            ])
            ->orderBy('productos.nombre')
            ->paginate(25)
            ->withQueryString();

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Kardex/Saldos', [
            'saldos'  => $saldos,
            'bodegas' => $bodegas,
            'filters' => $request->only(['search', 'bodega_id', 'solo_criticos']),
        ]);
    }

    public function reporteSaldos(Request $request): HttpResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = Empresa::findOrFail($empresaId);

        $saldos = InventarioSaldo::with(['producto', 'bodega'])
            ->join('productos', 'inventario_saldos.producto_id', '=', 'productos.id')
            ->join('bodegas', 'inventario_saldos.bodega_id', '=', 'bodegas.id')
            ->where('productos.empresa_id', $empresaId)
            ->select('inventario_saldos.*')
            ->orderBy('productos.nombre')
            ->get();

        $pdf = Pdf::loadView('reportes.inventario.saldos', [
            'saldos'  => $saldos,
            'empresa' => $empresa,
            'usuario' => auth()->user(),
        ])->setPaper('a4', 'landscape');

        return $request->boolean('download')
            ? $pdf->download('kardex_saldos.pdf')
            : $pdf->stream('kardex_saldos.pdf');
    }

    public function ajuste(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Inventario/Kardex/Ajuste', [
            'productos'  => Producto::where('empresa_id', $empresaId)
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
            'bodegas'    => Bodega::where('empresa_id', $empresaId)
                ->where('estado', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']),
            'productoId' => $request->integer('producto_id') ?: null,
            'bodegaId'   => $request->integer('bodega_id') ?: null,
            'redirect_to' => $request->input('redirect_to', route('inventario.kardex.saldos')),
        ]);
    }

    public function storeAjuste(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'producto_id'    => ['required', 'integer', 'exists:productos,id'],
            'bodega_id'      => ['required', 'integer', 'exists:bodegas,id'],
            'tipo_ajuste'    => ['required', 'in:positivo,negativo'],
            'cantidad'       => ['required', 'integer', 'min:1'],
            'costo_unitario' => ['required_if:tipo_ajuste,positivo', 'nullable', 'numeric', 'min:0'],
            'motivo'         => ['required', 'string', 'max:255'],
            'redirect_to'   => ['nullable', 'string', 'max:500'],
        ]);

        $producto = Producto::where('id', $data['producto_id'])
            ->where('empresa_id', $empresaId)
            ->firstOrFail();

        try {
            if ($data['tipo_ajuste'] === 'positivo') {
                $this->inventario->ingresarStock(
                    (int) $data['producto_id'],
                    (int) $data['bodega_id'],
                    (float) $data['cantidad'],
                    (float) ($data['costo_unitario'] ?? 0),
                    'ajuste',
                    0
                );
            } else {
                $this->inventario->egresarStock(
                    (int) $data['producto_id'],
                    (int) $data['bodega_id'],
                    (float) $data['cantidad'],
                    'ajuste',
                    0
                );
            }
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        $tipoTexto = $data['tipo_ajuste'] === 'positivo' ? 'positivo' : 'negativo';
        $this->auditoria->documento(
            'ajuste',
            'inventario',
            'inventario_movimientos',
            0,
            "Ajuste {$tipoTexto} de {$data['cantidad']} unidades — {$producto->codigo}: {$data['motivo']}"
        );

        $redirectTo = $request->input('redirect_to', route('inventario.kardex.saldos'));

        return redirect($redirectTo)
            ->with('success', 'Ajuste de inventario registrado correctamente.');
    }

    public function getSaldo(Request $request): JsonResponse
    {
        $productoId = $request->integer('producto_id');
        $bodegaId   = $request->integer('bodega_id');
        $disponible = $this->inventario->getSaldoDisponible($productoId, $bodegaId);

        return response()->json(['disponible' => $disponible]);
    }
}
