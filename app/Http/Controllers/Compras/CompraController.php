<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Exports\ComprasExport;
use App\Models\Bodega;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CuentaPagar;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\PlanCuenta;
use App\Models\CentroCosto;
use App\Models\Producto;
use App\Services\AsientoService;
use App\Services\Contracts\InventarioServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CompraController extends Controller
{
    public function __construct(
        private AsientoService $asientoService,
        private InventarioServiceInterface $inventario,
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $query = Compra::with(['proveedor', 'centroCosto'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->where('num_documento', 'ilike', "%{$q}%")
                   ->orWhere('concepto', 'ilike', "%{$q}%")
                   ->orWhereHas('proveedor', fn($p) =>
                       $p->where('razon_social', 'ilike', "%{$q}%"))
            );
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $compras     = $query->orderByDesc('fecha_emision')->paginate(20)->withQueryString();
        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social', 'nombre_comercial',
                   'identificacion', 'tiene_credito', 'dias_credito', 'tipo']);
        $centros = CentroCosto::where('empresa_id', $empresaId)
            ->get(['id', 'nombre', 'codigo']);
        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'unidad', 'costo', 'iva_porcentaje']);

        return Inertia::render('Compras/Compras/Index', [
            'compras'     => $compras,
            'proveedores' => $proveedores,
            'centros'     => $centros,
            'cuentas'     => $cuentas,
            'bodegas'     => $bodegas,
            'productos'   => $productos,
            'filtros'     => $request->only(['buscar', 'estado', 'fecha_desde', 'fecha_hasta']),
            'stats' => [
                'total'    => Compra::where('empresa_id', $empresaId)->count(),
                'activas'  => Compra::where('empresa_id', $empresaId)->where('estado', 'activa')->count(),
                'anuladas' => Compra::where('empresa_id', $empresaId)->where('estado', 'anulada')->count(),
                'con_pago' => Compra::where('empresa_id', $empresaId)->where('tiene_pago', true)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'proveedor_id'               => 'required|exists:proveedores,id',
            'tipo_documento'             => 'required|in:FAC,LIQ,TIK,CON,EXT',
            'num_documento'              => 'required|string|max:30',
            'fecha_emision'              => 'required|date',
            'dias_credito'               => 'integer|min:0',
            'bodega_id'                  => 'nullable|exists:bodegas,id',
            'gasto_no_deducible'         => 'boolean',
            'concepto'                   => 'nullable|string|max:500',
            'detalles'                   => 'required|array|min:1',
            'detalles.*.descripcion'     => 'required|string|max:500',
            'detalles.*.cantidad'        => 'required|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_iva'  => 'numeric|min:0|max:100',
        ]);

        $existe = Compra::where('empresa_id', $empresaId)
            ->where('proveedor_id', $request->proveedor_id)
            ->where('num_documento', $request->num_documento)
            ->exists();

        if ($existe) {
            return back()->with('error',
                "Ya existe una compra con el documento {$request->num_documento} de este proveedor.");
        }

        try {
            DB::transaction(function () use ($request, $empresaId) {
                $subtotal0   = 0;
                $subtotalIva = 0;
                $totalIva    = 0;

                $detalles = collect($request->detalles)->map(function ($d) use (&$subtotal0, &$subtotalIva, &$totalIva) {
                    $subtotal = round($d['cantidad'] * $d['precio_unitario'] - ($d['descuento'] ?? 0), 4);
                    $porcIva  = (float)($d['porcentaje_iva'] ?? 15);
                    $iva      = $porcIva > 0 ? round($subtotal * $porcIva / 100, 4) : 0;

                    if ($porcIva > 0) $subtotalIva += $subtotal;
                    else              $subtotal0   += $subtotal;
                    $totalIva += $iva;

                    return array_merge($d, [
                        'subtotal'  => $subtotal,
                        'valor_iva' => $iva,
                        'total'     => $subtotal + $iva,
                        'descuento' => $d['descuento'] ?? 0,
                    ]);
                });

                $total = $subtotal0 + $subtotalIva + $totalIva;

                $fechaVenc = $request->dias_credito > 0
                    ? now()->addDays($request->dias_credito)->toDateString()
                    : $request->fecha_emision;

                $compra = Compra::create([
                    'empresa_id'          => $empresaId,
                    'proveedor_id'        => $request->proveedor_id,
                    'centro_costo_id'     => $request->centro_costo_id,
                    'importacion_id'      => $request->importacion_id,
                    'bodega_id'           => $request->bodega_id,
                    'tipo_documento'      => $request->tipo_documento,
                    'num_documento'       => $request->num_documento,
                    'num_autorizacion'    => $request->num_autorizacion,
                    'fecha_emision'       => $request->fecha_emision,
                    'fecha_registro'      => now()->toDateString(),
                    'fecha_vencimiento'   => $fechaVenc,
                    'dias_credito'        => $request->dias_credito ?? 0,
                    'subtotal_0'          => $subtotal0,
                    'subtotal_iva'        => $subtotalIva,
                    'total_iva'           => $totalIva,
                    'total'               => $total,
                    'iva_asumido'         => $request->boolean('iva_asumido'),
                    'gasto_no_deducible'  => $request->boolean('gasto_no_deducible'),
                    'sustento_tributario' => $request->sustento_tributario,
                    'concepto'            => $request->concepto,
                    'estado'              => 'activa',
                    'created_by'          => Auth::id(),
                ]);

                foreach ($detalles as $d) {
                    CompraDetalle::create(array_merge(
                        collect($d)->only([
                            'producto_id', 'cuenta_id', 'descripcion', 'cantidad',
                            'precio_unitario', 'descuento', 'subtotal',
                            'porcentaje_iva', 'valor_iva', 'total', 'es_activo_fijo',
                        ])->toArray(),
                        ['compra_id' => $compra->id]
                    ));
                }

                // Ingreso a inventario para detalles con producto_id
                if ($request->bodega_id) {
                    foreach ($detalles as $d) {
                        if (empty($d['producto_id'])) {
                            continue;
                        }
                        try {
                            $this->inventario->ingresarStock(
                                productoId:   (int) $d['producto_id'],
                                bodegaId:     (int) $request->bodega_id,
                                cantidad:     (float) $d['cantidad'],
                                costoUnitario:(float) $d['precio_unitario'],
                                docTipo:      'compra',
                                docId:        $compra->id,
                                notas:        "Compra {$request->num_documento}",
                            );
                        } catch (\Exception) {
                            // No bloquear la compra si falla el inventario
                        }
                    }
                }

                if ($request->dias_credito > 0) {
                    CuentaPagar::create([
                        'empresa_id'        => $empresaId,
                        'proveedor_id'      => $request->proveedor_id,
                        'compra_id'         => $compra->id,
                        'monto'             => $total,
                        'saldo'             => $total,
                        'fecha_emision'     => now()->toDateString(),
                        'fecha_vencimiento' => $fechaVenc,
                        'estado'            => 'pendiente',
                    ]);
                }

                try {
                    $asiento = $this->asientoService->compraRegistrada(
                        empresaId:  $empresaId,
                        compraId:   $compra->id,
                        referencia: $request->num_documento,
                        subtotal:   $subtotal0 + $subtotalIva,
                        iva:        $totalIva,
                        tipo:       $request->boolean('gasto_no_deducible') ? 'gasto' : 'inventario',
                    );
                    $compra->update(['asiento_id' => $asiento->id]);
                } catch (\Exception) {
                    // No bloquear si el período contable está cerrado
                }
            });

            return back()->with('success',
                "Compra {$request->num_documento} registrada correctamente.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(Compra $compra): Response
    {
        $compra->load(['proveedor', 'centroCosto', 'detalles.cuenta',
                       'cuentaPagar', 'asiento', 'creadoPor']);
        return Inertia::render('Compras/Compras/Show', [
            'compra' => $compra,
        ]);
    }

    public function pdfIndividual(Compra $compra): \Illuminate\Http\Response
    {
        $compra->load(['proveedor', 'centroCosto', 'detalles.cuenta', 'cuentaPagar', 'creadoPor']);
        $empresa = Empresa::find(session('empresa_activa_id'));
        $pdf = Pdf::loadView('pdf.compra', compact('compra', 'empresa'))->setPaper('a4', 'portrait');
        return $pdf->stream('compra-' . $compra->num_documento . '-' . now()->format('Y-m-d') . '.pdf');
    }

    public function anular(Request $request, Compra $compra): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:300',
        ]);

        if ($compra->estaAnulada()) {
            return back()->with('error', 'Esta compra ya está anulada.');
        }
        if ($compra->tiene_pago) {
            return back()->with('error',
                'No se puede anular: tiene un pago registrado. Anula el pago primero.');
        }

        DB::transaction(function () use ($compra, $request) {
            $compra->update(['estado' => 'anulada']);

            if ($compra->cuentaPagar) {
                $compra->cuentaPagar->update(['estado' => 'pagada', 'saldo' => 0]);
            }

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'username'       => Auth::user()?->email ?? '',
                'tabla'          => 'compras',
                'registro_id'    => $compra->id,
                'campo'          => 'estado',
                'valor_anterior' => 'activa',
                'valor_nuevo'    => 'anulada',
                'motivo'         => $request->motivo,
                'ip'             => $request->ip(),
                'fecha'          => now(),
            ]);
        });

        return back()->with('success',
            "Compra {$compra->num_documento} anulada correctamente.");
    }

    public function pdf(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $query     = Compra::with('proveedor')->where('empresa_id', $empresaId);
        if ($request->filled('estado'))      { $query->where('estado', $request->estado); }
        if ($request->filled('fecha_desde')) { $query->where('fecha_emision', '>=', $request->fecha_desde); }
        if ($request->filled('fecha_hasta')) { $query->where('fecha_emision', '<=', $request->fecha_hasta); }
        $compras = $query->orderByDesc('fecha_emision')->get();
        $empresa = Empresa::find($empresaId);
        $pdf = Pdf::loadView('pdf.compras', compact('compras', 'empresa'))->setPaper('a4', 'landscape');
        return $pdf->stream('facturas-compra-' . now()->format('Y-m-d') . '.pdf');
    }

    public function excel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $empresaId = session('empresa_activa_id');
        return Excel::download(
            new ComprasExport((int) $empresaId, $request->only(['estado', 'fecha_desde', 'fecha_hasta'])),
            'facturas-compra-' . now()->format('Y-m-d') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
