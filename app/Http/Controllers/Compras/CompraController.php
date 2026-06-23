<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Exports\ComprasExport;
use App\Models\Bodega;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CuentaPagar;
use App\Models\EtiquetaProducto;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\Proveedor;
use App\Models\PlanCuenta;
use App\Models\CentroCosto;
use App\Models\Producto;
use App\Services\AsientoService;
use App\Services\Contracts\InventarioServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Picqer\Barcode\BarcodeGeneratorPNG;

class CompraController extends Controller
{
    public function __construct(
        private AsientoService $asientoService,
        private InventarioServiceInterface $inventario,
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $query = Compra::with(['proveedor', 'centroCosto', 'recepcionBodega:id,compra_id'])
            ->withExists('etiquetasProductos as has_etiquetas')
            ->withCount(['detalles as tiene_productos_codificados' => fn($q) => $q->whereNotNull('producto_id')])
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
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'unidad', 'costo', 'porcentaje_iva']);

        return Inertia::render('Compras/Compras/Index', [
            'compras'     => $compras,
            'proveedores' => $proveedores,
            'centros'     => $centros,
            'cuentas'     => $cuentas,
            'bodegas'     => $bodegas,
            'productos'   => $productos,
            'filtros'     => $request->only(['buscar', 'estado', 'fecha_desde', 'fecha_hasta']),
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
                    'estado'              => 'pendiente',
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

                // Crear recepción pendiente automáticamente si hay productos y bodega seleccionada
                $detallesProducto = collect($request->detalles)->filter(
                    fn($d) => !empty($d['producto_id'])
                );
                $yaExisteRecepcion = \App\Models\RecepcionBodega::where('compra_id', $compra->id)->exists();
                if (!$yaExisteRecepcion && $detallesProducto->isNotEmpty() && !empty($request->bodega_id)) {
                    $recepcion = \App\Models\RecepcionBodega::create([
                        'empresa_id' => $empresaId,
                        'compra_id'  => $compra->id,
                        'bodega_id'  => $request->bodega_id,
                        'estado'     => 'pendiente',
                    ]);

                    foreach ($detallesProducto as $d) {
                        $detalle = \App\Models\CompraDetalle::where('compra_id', $compra->id)
                            ->where('producto_id', $d['producto_id'])
                            ->first();

                        if ($detalle) {
                            \App\Models\RecepcionDetalle::create([
                                'recepcion_id'      => $recepcion->id,
                                'compra_detalle_id' => $detalle->id,
                                'producto_id'       => $d['producto_id'],
                                'cantidad_esperada' => $d['cantidad'],
                                'cantidad_recibida' => 0,
                                'estado'            => 'pendiente',
                            ]);
                        }
                    }
                }
            });

            return back()->with('success',
                "Compra {$request->num_documento} ingresada. Pendiente de confirmación en bodega.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Confirmar recepción en bodega ────────────────────────────────────────────

    public function activar(Compra $compra): RedirectResponse
    {
        if (!$compra->estaPendiente()) {
            return back()->with('error', 'Solo se pueden activar facturas en estado pendiente.');
        }

        $empresaId = session('empresa_activa_id');

        DB::transaction(function () use ($compra, $empresaId) {
            $compra->update(['estado' => 'activa']);

            $bodegaEfectiva = $compra->bodega_id
                ?? optional(Bodega::where('empresa_id', $empresaId)
                    ->where('tipo', 'general')->first())->id;

            if ($bodegaEfectiva) {
                foreach ($compra->detalles as $d) {
                    if (empty($d->producto_id)) continue;
                    try {
                        $this->inventario->ingresarStock(
                            productoId:    (int) $d->producto_id,
                            bodegaId:      (int) $bodegaEfectiva,
                            cantidad:      (float) $d->cantidad,
                            costoUnitario: (float) $d->precio_unitario,
                            docTipo:       'COMPRA',
                            docId:         $compra->id,
                            docNumero:     $compra->num_documento,
                            observacion:   "Compra confirmada: {$compra->num_documento}",
                        );
                        Producto::where('id', $d->producto_id)
                            ->update(['costo' => $d->precio_unitario, 'updated_at' => now()]);
                    } catch (\Exception $e) {
                        \Log::warning("Inventario: error producto {$d->producto_id}: {$e->getMessage()}");
                    }
                }
            }

            // Fallback: crear recepción pendiente si no se generaron etiquetas antes
            $detallesProducto = $compra->detalles->filter(fn($d) => !empty($d->producto_id));
            $yaExisteRecepcion = \App\Models\RecepcionBodega::where('compra_id', $compra->id)->exists();
            if (!$yaExisteRecepcion && $detallesProducto->isNotEmpty() && $bodegaEfectiva) {
                $recepcion = \App\Models\RecepcionBodega::create([
                    'empresa_id' => $empresaId,
                    'compra_id'  => $compra->id,
                    'bodega_id'  => $bodegaEfectiva,
                    'estado'     => 'pendiente',
                ]);

                foreach ($detallesProducto as $detalle) {
                    \App\Models\RecepcionDetalle::create([
                        'recepcion_id'      => $recepcion->id,
                        'compra_detalle_id' => $detalle->id,
                        'producto_id'       => $detalle->producto_id,
                        'cantidad_esperada' => $detalle->cantidad,
                        'cantidad_recibida' => 0,
                        'estado'            => 'pendiente',
                    ]);
                }
            }

            if ($compra->dias_credito > 0) {
                CuentaPagar::create([
                    'empresa_id'        => $empresaId,
                    'proveedor_id'      => $compra->proveedor_id,
                    'compra_id'         => $compra->id,
                    'monto'             => $compra->total,
                    'saldo'             => $compra->total,
                    'fecha_emision'     => now()->toDateString(),
                    'fecha_vencimiento' => $compra->fecha_vencimiento,
                    'estado'            => 'pendiente',
                ]);
            }

            try {
                $asiento = $this->asientoService->compraRegistrada(
                    empresaId:  $empresaId,
                    compraId:   $compra->id,
                    referencia: $compra->num_documento,
                    subtotal:   $compra->subtotal_0 + $compra->subtotal_iva,
                    iva:        $compra->total_iva,
                    tipo:       $compra->gasto_no_deducible ? 'gasto' : 'inventario',
                );
                $compra->update(['asiento_id' => $asiento->id]);
            } catch (\Exception) {
                // No bloquear si período contable cerrado
            }
        });

        return back()->with('success',
            "Factura {$compra->num_documento} confirmada. Inventario y CxP actualizados.");
    }

    // ── Etiquetas — datos para el modal ─────────────────────────────────────────

    public function etiquetasData(Compra $compra): JsonResponse
    {
        try {
            $compra->load(['detalles' => function ($q) {
                $q->with('producto:id,codigo,nombre');
            }]);

            $detalles = $compra->detalles
                ->filter(fn($d) => $d->producto_id !== null)
                ->map(function ($d) {
                    $codigo  = $d->producto?->codigo ?? '?';
                    $nombre  = $d->producto?->nombre ?? $d->descripcion;
                    $prefijo = EtiquetaProducto::extraerPrefijo($codigo);
                    $ultimo  = EtiquetaProducto::ultimoCorrelativoPorPrefijo($prefijo);
                    $cant    = (int) ceil((float) $d->cantidad);
                    $desde   = $ultimo + 1;
                    return [
                        'id'                      => $d->id,
                        'producto_id'             => $d->producto_id,
                        'codigo'                  => $codigo,
                        'nombre'                  => $nombre,
                        'descripcion'             => $d->descripcion,
                        'cantidad'                => $cant,
                        'prefijo'                 => $prefijo,
                        'ultima_etiqueta_prefijo' => $ultimo,
                        'desde'                   => $desde,
                        'hasta'                   => $desde + $cant - 1,
                        'num_etiquetas'           => $cant,
                    ];
                })
                ->values();

            return response()->json(['detalles' => $detalles]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // ── Etiquetas — generar PDF y guardar en BD ──────────────────────────────────

    public function generarEtiquetasPdf(Request $request, Compra $compra): \Illuminate\Http\Response
    {
        $request->validate([
            'productos'                 => 'required|array|min:1',
            'productos.*.producto_id'   => 'required|integer',
            'productos.*.detalle_id'    => 'nullable|integer',
            'productos.*.codigo'        => 'required|string',
            'productos.*.descripcion'   => 'required|string',
            'productos.*.num_etiquetas' => 'required|integer|min:1',
        ]);

        $empresaId = session('empresa_activa_id');

        // Calcular correlativos y persistir dentro de una transacción para
        // evitar duplicados si dos usuarios generan etiquetas simultáneamente
        $registros = DB::transaction(function () use ($request, $compra, $empresaId) {
            $registros = [];

            foreach ($request->productos as $p) {
                $productoId   = (int) $p['producto_id'];
                $numEtiquetas = (int) $p['num_etiquetas'];
                $codigo       = $p['codigo'];
                $prefijo      = EtiquetaProducto::extraerPrefijo($codigo);

                // Recalcular dentro de la transacción — ignora el 'desde' del frontend
                $ultimo = EtiquetaProducto::ultimoCorrelativoPorPrefijo($prefijo);
                $desde  = $ultimo + 1;
                $hasta  = $desde + $numEtiquetas - 1;

                EtiquetaProducto::create([
                    'empresa_id'        => $empresaId,
                    'compra_id'         => $compra->id,
                    'compra_detalle_id' => $p['detalle_id'] ?? null,
                    'producto_id'       => $productoId,
                    'codigo_producto'   => $codigo,
                    'correlativo_desde' => $desde,
                    'correlativo_hasta' => $hasta,
                    'cantidad'          => $numEtiquetas,
                    'generado_por'      => Auth::id(),
                    'created_at'        => now(),
                ]);

                $registros[] = [
                    'codigo'      => $codigo,
                    'descripcion' => $p['descripcion'],
                    'desde'       => $desde,
                    'hasta'       => $hasta,
                ];
            }

            // Crear recepción pendiente si no existe ya una para esta compra
            $yaExisteRecepcion = \App\Models\RecepcionBodega::where('compra_id', $compra->id)->exists();

            if (!$yaExisteRecepcion) {
                $bodegaEfectiva = $compra->bodega_id
                    ?? \App\Models\Bodega::where('empresa_id', $empresaId)
                        ->where('tipo', 'general')
                        ->value('id');

                if ($bodegaEfectiva) {
                    $recepcion = \App\Models\RecepcionBodega::create([
                        'empresa_id' => $empresaId,
                        'compra_id'  => $compra->id,
                        'bodega_id'  => $bodegaEfectiva,
                        'estado'     => 'pendiente',
                    ]);

                    $compra->load('detalles');
                    foreach ($compra->detalles->filter(fn($d) => !empty($d->producto_id)) as $detalle) {
                        \App\Models\RecepcionDetalle::create([
                            'recepcion_id'      => $recepcion->id,
                            'compra_detalle_id' => $detalle->id,
                            'producto_id'       => $detalle->producto_id,
                            'cantidad_esperada' => $detalle->cantidad,
                            'cantidad_recibida' => 0,
                            'estado'            => 'pendiente',
                        ]);
                    }
                }
            }

            return $registros;
        });

        // Construir lista de etiquetas individuales
        $generator = new BarcodeGeneratorPNG();
        $etiquetas = [];
        foreach ($registros as $r) {
            for ($i = $r['desde']; $i <= $r['hasta']; $i++) {
                $codigoBarras = $r['codigo'] . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);
                $etiquetas[]  = [
                    'codigo_barras' => $codigoBarras,
                    'descripcion'   => $r['descripcion'],
                    'barcode_png'   => base64_encode(
                        $generator->getBarcode($codigoBarras, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 60)
                    ),
                ];
            }
        }

        $etiquetas = array_values(array_filter($etiquetas, fn($e) => !empty($e['barcode_png'])));

        $empresa = Empresa::find($empresaId);
        // 9cm × 3.5cm en puntos (1cm = 28.3465pt)
        $pdf     = Pdf::loadView('pdf.etiquetas', compact('etiquetas', 'empresa'))
            ->setPaper([0, 0, 255.12, 99.21], 'portrait');

        return $pdf->stream('etiquetas-' . $compra->num_documento . '.pdf');
    }


    // ── Etiquetas — listado para modal de selección ──────────────────────────────

    public function etiquetasListado(Compra $compra): JsonResponse
    {
        $registros = EtiquetaProducto::where('compra_id', $compra->id)
            ->with('producto:id,nombre')
            ->orderBy('producto_id')
            ->orderBy('correlativo_desde')
            ->get();

        if ($registros->isEmpty()) {
            return response()->json(['productos' => []]);
        }

        $compra->load(['detalles:id,compra_id,producto_id,descripcion']);
        $descripcionPorProductoId = $compra->detalles
            ->whereNotNull('producto_id')
            ->keyBy('producto_id')
            ->map(fn($d) => $d->descripcion);

        $agrupado = [];

        foreach ($registros as $reg) {
            $codigo  = $reg->codigo_producto;
            $nombre  = $reg->producto?->nombre
                ?? $descripcionPorProductoId[$reg->producto_id]
                ?? $codigo;

            if (!array_key_exists($codigo, $agrupado)) {
                $agrupado[$codigo] = ['codigo' => $codigo, 'nombre' => $nombre, 'etiquetas' => []];
            }

            for ($i = (int) $reg->correlativo_desde; $i <= (int) $reg->correlativo_hasta; $i++) {
                $agrupado[$codigo]['etiquetas'][] = $codigo . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);
            }
        }

        return response()->json(['productos' => array_values($agrupado)]);
    }

    // ── Etiquetas — reimprimir selección específica ───────────────────────────────

    public function reimprimirSeleccion(Request $request, Compra $compra): \Illuminate\Http\Response
    {
        $request->validate([
            'etiquetas'   => 'required|array|min:1',
            'etiquetas.*' => 'required|string',
        ]);

        $compra->load(['detalles:id,compra_id,producto_id,descripcion']);
        $registros = EtiquetaProducto::where('compra_id', $compra->id)
            ->get(['producto_id', 'codigo_producto']);

        // Mapa: codigo_producto → descripcion del detalle
        $descripcionPorCodigo = [];
        foreach ($registros as $reg) {
            if (isset($descripcionPorCodigo[$reg->codigo_producto])) continue;
            $det = $compra->detalles->firstWhere('producto_id', $reg->producto_id);
            $descripcionPorCodigo[$reg->codigo_producto] = $det?->descripcion ?? $reg->codigo_producto;
        }

        $generator = new BarcodeGeneratorPNG();
        $etiquetas = [];

        foreach ($request->etiquetas as $codigoBarras) {
            // Extrae el código de producto: "AMP-001-000052" → "AMP-001" (antes del último guion)
            $pos            = strrpos((string) $codigoBarras, '-');
            $codigoProducto = $pos !== false ? substr((string) $codigoBarras, 0, $pos) : (string) $codigoBarras;
            $descripcion    = $descripcionPorCodigo[$codigoProducto] ?? $codigoProducto;

            $png = $generator->getBarcode((string) $codigoBarras, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 60);
            if (!$png) continue;

            $etiquetas[] = [
                'codigo_barras' => (string) $codigoBarras,
                'descripcion'   => $descripcion,
                'barcode_png'   => base64_encode($png),
            ];
        }

        if (empty($etiquetas)) {
            abort(400, 'No se pudo generar ninguna etiqueta.');
        }

        $empresa = Empresa::find(session('empresa_activa_id'));
        $pdf = Pdf::loadView('pdf.etiquetas', compact('etiquetas', 'empresa'))
            ->setPaper([0, 0, 255.12, 99.21], 'portrait');

        return $pdf->stream('reimprimir-seleccion-' . $compra->num_documento . '.pdf');
    }

    // ── Etiquetas — reimprimir todas las ya generadas para esta compra ───────────

    public function reimprimirEtiquetasPdf(Compra $compra): \Illuminate\Http\Response
    {
        $registros = EtiquetaProducto::where('compra_id', $compra->id)->orderBy('id')->get();

        if ($registros->isEmpty()) {
            abort(404, 'No hay etiquetas generadas para esta factura.');
        }

        $compra->load(['detalles:id,compra_id,descripcion']);
        $descripcionesByDetalleId = $compra->detalles->keyBy('id')
            ->map(fn($d) => $d->descripcion);

        $generator = new BarcodeGeneratorPNG();
        $etiquetas = [];

        foreach ($registros as $reg) {
            $descripcion = $descripcionesByDetalleId[$reg->compra_detalle_id] ?? $reg->codigo_producto;
            for ($i = (int) $reg->correlativo_desde; $i <= (int) $reg->correlativo_hasta; $i++) {
                $codigoBarras = $reg->codigo_producto . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);
                $etiquetas[]  = [
                    'codigo_barras' => $codigoBarras,
                    'descripcion'   => $descripcion,
                    'barcode_png'   => base64_encode(
                        $generator->getBarcode($codigoBarras, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 60)
                    ),
                ];
            }
        }

        $etiquetas = array_values(array_filter($etiquetas, fn($e) => !empty($e['barcode_png'])));

        $empresa = Empresa::find(session('empresa_activa_id'));
        $pdf     = Pdf::loadView('pdf.etiquetas', compact('etiquetas', 'empresa'))
            ->setPaper([0, 0, 255.12, 99.21], 'portrait');

        return $pdf->stream('reimprimir-etiquetas-' . $compra->num_documento . '.pdf');
    }

    public function show(Compra $compra): Response
    {
        $compra->load(['proveedor', 'centroCosto', 'detalles.cuenta',
                       'cuentaPagar', 'asiento', 'creadoPor', 'recepcionBodega']);
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

    // ── Verificar escenario de anulación ────────────────────────────────────────

    public function verificarAnulacion(Compra $compra): JsonResponse
    {
        try {
            if ($compra->estaAnulada()) {
                return response()->json([
                    'escenario' => 'ANULADA',
                    'mensaje'   => 'Esta compra ya está anulada.',
                ]);
            }

            // Escenario especial para pendiente — nunca tuvo inventario ni CxP
            if ($compra->estaPendiente()) {
                return response()->json([
                    'escenario' => 'A',
                    'mensaje'   => 'Esta factura está pendiente de recepción. Se anulará sin reversión de inventario.',
                ]);
            }

            $compra->loadMissing(['detalles' => fn($q) => $q->with('producto:id,nombre')->whereNotNull('producto_id')]);
            $productoIds = $compra->detalles->pluck('producto_id')->filter()->unique()->values();

            // Escenario C: salidas reales por VENTAS
            // Columnas reales: tipo (no tipo_movimiento), doc_tipo (no documento_tipo)
            // No existe columna 'fecha' — usar DATE(created_at)
            if ($productoIds->isNotEmpty() && $compra->fecha_emision) {
                $salidas = DB::table('inventario_movimientos')
                    ->whereIn('producto_id', $productoIds)
                    ->where('tipo', 'salida')
                    ->where(fn($q) => $q
                        ->whereNull('doc_tipo')
                        ->orWhereNotIn('doc_tipo', ['ANULACION', 'ANULACION_COMPRA', 'AJUSTE', 'COMPRA'])
                    )
                    ->whereRaw("DATE(created_at) >= ?", [$compra->fecha_emision->toDateString()])
                    ->select('producto_id', DB::raw('SUM(cantidad) as total_salida'))
                    ->groupBy('producto_id')
                    ->get();

                if ($salidas->isNotEmpty()) {
                    $productosVendidos = $salidas->map(function ($m) use ($compra) {
                        $det = $compra->detalles->firstWhere('producto_id', $m->producto_id);
                        return [
                            'nombre'          => $det?->producto?->nombre ?? $det?->descripcion ?? '—',
                            'cantidad_salida' => (float) $m->total_salida,
                        ];
                    })->values();

                    return response()->json([
                        'escenario'          => 'C',
                        'mensaje'            => 'No se puede anular: uno o más productos de esta factura tienen ventas registradas.',
                        'productos_vendidos' => $productosVendidos,
                    ]);
                }
            }

            // Escenario B: hay egreso bancario activo
            $movPago = MovimientoBancario::with('bancoCaja')
                ->where('documento_tipo', 'COMPRA')
                ->where('documento_id', $compra->id)
                ->where('tipo', 'egreso')
                ->where('anulado', false)
                ->first();

            if ($compra->tiene_pago || $movPago !== null) {
                $monto = $movPago ? (float) $movPago->monto : (float) $compra->total;
                $banco = $movPago?->bancoCaja?->nombre ?? '—';
                return response()->json([
                    'escenario' => 'B',
                    'mensaje'   => "Esta factura tiene un pago de \${$monto} en {$banco}. Se revertirá automáticamente.",
                    'monto'     => $monto,
                    'banco'     => $banco,
                ]);
            }

            // Escenario A: activa sin pago, sin ventas
            return response()->json([
                'escenario' => 'A',
                'mensaje'   => 'La factura puede anularse. Se revertirá el inventario y se cancelará la CxP.',
            ]);

        } catch (\Throwable $e) {
            \Log::error("verificarAnulacion error compra#{$compra->id}: {$e->getMessage()} en {$e->getFile()}:{$e->getLine()}");
            return response()->json([
                'escenario' => 'ERROR',
                'mensaje'   => 'Error al verificar: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Anular factura de compra (escenarios A / B) ──────────────────────────────

    public function anular(Request $request, Compra $compra): RedirectResponse
    {
        $request->validate(['motivo' => 'required|string|min:10|max:300']);

        if ($compra->estaAnulada()) {
            return back()->with('error', 'Esta compra ya está anulada.');
        }

        $empresaId      = session('empresa_activa_id');
        $estadoAnterior = $compra->estado;

        // ── Pendiente: nunca entró al inventario ni generó CxP/asiento ──────────
        if ($compra->estaPendiente()) {
            DB::transaction(function () use ($compra, $request, $estadoAnterior) {
                $compra->update(['estado' => 'anulada']);
                DB::table('log_cambios_criticos')->insert([
                    'usuario_id'     => Auth::id(),
                    'tabla'          => 'compras',
                    'registro_id'    => $compra->id,
                    'campo'          => 'estado',
                    'valor_anterior' => $estadoAnterior,
                    'valor_nuevo'    => "anulada — {$request->motivo}",
                    'ip_address'     => request()->ip(),
                ]);
            });
            return back()->with('success',
                "Factura pendiente {$compra->num_documento} anulada correctamente.");
        }

        // ── Activa: verificar que no haya ventas (columnas reales de la BD) ─────
        $compra->load(['detalles', 'cuentaPagar', 'asiento.detalles']);
        $productoIds = $compra->detalles->pluck('producto_id')->filter()->unique();

        if ($productoIds->isNotEmpty() && $compra->fecha_emision) {
            $hayVentas = DB::table('inventario_movimientos')
                ->whereIn('producto_id', $productoIds)
                ->where('tipo', 'salida')
                ->where(fn($q) => $q
                    ->whereNull('doc_tipo')
                    ->orWhereNotIn('doc_tipo', ['ANULACION', 'ANULACION_COMPRA', 'AJUSTE', 'COMPRA'])
                )
                ->whereRaw("DATE(created_at) >= ?", [$compra->fecha_emision->toDateString()])
                ->exists();

            if ($hayVentas) {
                return back()->with('error',
                    'No se puede anular: los productos de esta factura tienen ventas registradas.');
            }
        }

        DB::transaction(function () use ($compra, $request, $empresaId, $estadoAnterior) {

            $bodegaEfectiva = $compra->bodega_id
                ?? optional(Bodega::where('empresa_id', $empresaId)
                    ->where('tipo', 'general')->first())->id;

            // Revertir inventario
            if ($bodegaEfectiva) {
                foreach ($compra->detalles as $d) {
                    if (!$d->producto_id) continue;
                    try {
                        $this->inventario->egresarStock(
                            productoId:  (int) $d->producto_id,
                            bodegaId:    (int) $bodegaEfectiva,
                            cantidad:    (float) $d->cantidad,
                            docTipo:     'ANULACION',
                            docId:       $compra->id,
                            docNumero:   $compra->num_documento,
                            observacion: "Anulación compra: {$request->motivo}",
                        );
                    } catch (\Exception $e) {
                        \Log::warning("Anulación inv. producto {$d->producto_id}: {$e->getMessage()}");
                    }
                }
            }

            // Escenario B: revertir pago bancario activo
            $movPago = MovimientoBancario::where('documento_tipo', 'COMPRA')
                ->where('documento_id', $compra->id)
                ->where('tipo', 'egreso')
                ->where('anulado', false)
                ->first();

            if ($movPago) {
                MovimientoBancario::create([
                    'empresa_id'     => $empresaId,
                    'banco_caja_id'  => $movPago->banco_caja_id,
                    'tipo'           => 'ingreso',
                    'sub_tipo'       => $movPago->sub_tipo,
                    'fecha'          => now()->toDateString(),
                    'monto'          => $movPago->monto,
                    'persona_tipo'   => 'proveedor',
                    'persona_id'     => $compra->proveedor_id,
                    'num_documento'  => $compra->num_documento,
                    'descripcion'    => "Reversión pago — Anulación {$compra->num_documento}: {$request->motivo}",
                    'documento_tipo' => 'ANULACION_COMPRA',
                    'documento_id'   => $compra->id,
                    'created_by'     => Auth::id(),
                ]);
                DB::table('bancos_cajas')
                    ->where('id', $movPago->banco_caja_id)
                    ->increment('saldo_actual', (float) $movPago->monto);
                $movPago->update(['anulado' => true]);

                if ($movPago->asiento_id) {
                    try {
                        $movPago->loadMissing('asiento.detalles');
                        $this->asientoService->anular(
                            $movPago->asiento,
                            "Reversión pago — Anulación {$compra->num_documento}"
                        );
                    } catch (\Exception $e) {
                        \Log::warning("Asiento pago no revertido: {$e->getMessage()}");
                    }
                }
            }

            // Revertir asiento de la compra
            if ($compra->asiento_id && $compra->asiento) {
                try {
                    $this->asientoService->anular(
                        $compra->asiento,
                        "Anulación compra {$compra->num_documento}: {$request->motivo}"
                    );
                } catch (\Exception $e) {
                    \Log::warning("Asiento compra no revertido: {$e->getMessage()}");
                }
            }

            // Cancelar CxP
            if ($compra->cuentaPagar) {
                $compra->cuentaPagar->update(['estado' => 'pagada', 'saldo' => 0]);
            }

            $compra->update(['estado' => 'anulada']);

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'tabla'          => 'compras',
                'registro_id'    => $compra->id,
                'campo'          => 'estado',
                'valor_anterior' => $estadoAnterior,
                'valor_nuevo'    => "anulada — {$request->motivo}",
                'ip_address'     => request()->ip(),
            ]);
        });

        return back()->with('success', "Compra {$compra->num_documento} anulada correctamente.");
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
