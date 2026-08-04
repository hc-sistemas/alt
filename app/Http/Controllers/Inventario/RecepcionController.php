<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Compra;
use App\Models\CuentaPagar;
use App\Models\EtiquetaProducto;
use App\Models\Producto;
use App\Models\RecepcionBodega;
use App\Models\RecepcionDetalle;
use App\Models\RecepcionEscaneo;
use App\Services\AsientoService;
use App\Services\AuditoriaService;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RecepcionController extends Controller
{
    public function __construct(
        private InventarioServiceInterface $inventario,
        private AuditoriaService $auditoria,
        private AsientoService $asientoService,
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');
        $busquedaRealizada = $request->boolean('buscado');

        $recepciones = null;

        if ($busquedaRealizada) {
            $query = RecepcionBodega::with(['compra.proveedor', 'bodega'])
                ->where('empresa_id', $empresaId);

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->whereHas('compra', fn($q) => $q->where('num_documento', 'ilike', "%{$search}%"))
                      ->orWhereHas('compra.proveedor', fn($q) => $q->where('razon_social', 'ilike', "%{$search}%"));
                });
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('fecha_desde')) {
                $query->where('created_at', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->where('created_at', '<=', $request->fecha_hasta . ' 23:59:59');
            }

            $recepciones = $query->orderByDesc('created_at')->paginate(20)->withQueryString();
        }

        $bodegas = Bodega::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('Inventario/Recepciones/Index', [
            'recepciones' => $recepciones,
            'filtros'     => $request->only(['search', 'estado', 'fecha_desde', 'fecha_hasta']),
            'bodegas'     => $bodegas,
        ]);
    }

    public function show(RecepcionBodega $recepcion): Response
    {
        $empresaId = session('empresa_activa_id');

        if ($recepcion->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $recepcion->load([
            'compra.proveedor',
            'bodega',
            'detalles.producto',
            'detalles.compraDetalle',
            'recibidoPor',
        ]);

        return Inertia::render('Inventario/Recepciones/Show', [
            'recepcion' => $recepcion,
        ]);
    }

    public function buscarCompra(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $q = $request->input('q', '');

        $compras = Compra::with([
            'proveedor:id,razon_social',
            'detalles' => fn($qb) => $qb->whereNotNull('producto_id')->with('producto:id,codigo,nombre'),
        ])
        ->where('empresa_id', $empresaId)
        ->where('estado', 'activa')
        ->where('num_documento', 'ilike', "%{$q}%")
        ->whereHas('detalles', fn($qb) => $qb->whereNotNull('producto_id'))
        ->orderByDesc('fecha_emision')
        ->limit(10)
        ->get(['id', 'num_documento', 'fecha_emision', 'proveedor_id']);

        return response()->json($compras);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'compra_id'                    => 'required|integer|exists:compras,id',
            'bodega_id'                    => 'required|integer|exists:bodegas,id',
            'detalles'                     => 'required|array|min:1',
            'detalles.*.compra_detalle_id' => 'required|integer|exists:compra_detalles,id',
            'detalles.*.producto_id'       => 'required|integer|exists:productos,id',
            'detalles.*.cantidad_esperada' => 'required|numeric|min:0.0001',
        ]);

        $compra = Compra::where('empresa_id', $empresaId)
            ->where('id', $request->compra_id)
            ->firstOrFail();

        $yaExiste = RecepcionBodega::where('compra_id', $compra->id)
            ->where('bodega_id', $request->bodega_id)
            ->exists();

        if ($yaExiste) {
            return back()->with('error', 'Ya existe una recepción para esta compra en esa bodega.');
        }

        DB::transaction(function () use ($request, $compra, $empresaId) {
            $recepcion = RecepcionBodega::create([
                'empresa_id' => $empresaId,
                'compra_id'  => $compra->id,
                'bodega_id'  => $request->bodega_id,
                'estado'     => 'pendiente',
            ]);

            foreach ($request->detalles as $d) {
                RecepcionDetalle::create([
                    'recepcion_id'      => $recepcion->id,
                    'compra_detalle_id' => $d['compra_detalle_id'],
                    'producto_id'       => $d['producto_id'],
                    'cantidad_esperada' => $d['cantidad_esperada'],
                    'cantidad_recibida' => 0,
                    'estado'            => 'pendiente',
                ]);
            }

            $this->auditoria->documento(
                'crear',
                'inventario',
                'recepciones_bodega',
                $recepcion->id,
                "Recepción manual creada desde compra #{$compra->num_documento}"
            );
        });

        return redirect()->route('inventario.recepciones.index')
            ->with('success', 'Recepción creada correctamente.');
    }

    public function buscarProducto(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $codigo = $request->input('codigo');

        if (!$codigo) {
            return response()->json(['encontrado' => false, 'producto' => null]);
        }

        $producto = Producto::where('estado', true)
            ->where(fn($q) => $q->where('codigo', $codigo)->orWhere('codigo_externo', $codigo))
            ->first(['id', 'codigo', 'nombre', 'unidad']);

        return response()->json([
            'encontrado' => $producto !== null,
            'producto'   => $producto,
        ]);
    }

    public function escanear(Request $request, RecepcionBodega $recepcion): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($recepcion->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        if (!$recepcion->isPendiente()) {
            return back()->with('error', 'Esta recepción ya fue procesada.');
        }

        $request->validate(['codigo' => 'required|string|max:100']);

        $codigo = trim($request->codigo);

        $ultimoGuion = strrpos($codigo, '-');
        if ($ultimoGuion === false) {
            return back()->with('error', 'Formato de código inválido.');
        }

        $codigoProducto = substr($codigo, 0, $ultimoGuion);
        $correlativoStr = substr($codigo, $ultimoGuion + 1);

        if (!ctype_digit($correlativoStr) || strlen($correlativoStr) !== 6) {
            return back()->with('error', 'Formato de código inválido.');
        }

        $correlativo = (int) $correlativoStr;

        $etiqueta = EtiquetaProducto::where('codigo_producto', $codigoProducto)
            ->where('correlativo_desde', '<=', $correlativo)
            ->where('correlativo_hasta', '>=', $correlativo)
            ->where('compra_id', $recepcion->compra_id)
            ->first();

        if (!$etiqueta) {
            return back()->with('error', 'Código no pertenece a esta compra.');
        }

        $detalle = RecepcionDetalle::where('recepcion_id', $recepcion->id)
            ->where('producto_id', $etiqueta->producto_id)
            ->first();

        if (!$detalle) {
            return back()->with('error', 'Producto no encontrado en esta recepción.');
        }

        $escaneosDetalle = RecepcionEscaneo::where('recepcion_detalle_id', $detalle->id)->count();

        if ($escaneosDetalle >= (int) $detalle->cantidad_esperada) {
            return back()->with('error', 'Ya se recibieron todas las unidades de este producto.');
        }

        $duplicado = RecepcionEscaneo::where('recepcion_id', $recepcion->id)
            ->where('codigo_escaneado', $codigo)
            ->exists();

        if ($duplicado) {
            return back()->with('error', 'Este código ya fue escaneado.');
        }

        RecepcionEscaneo::create([
            'recepcion_id'        => $recepcion->id,
            'recepcion_detalle_id' => $detalle->id,
            'producto_id'         => $etiqueta->producto_id,
            'codigo_escaneado'    => $codigo,
            'correlativo'         => $correlativo,
            'usuario_id'          => Auth::id(),
            'created_at'          => now(),
        ]);

        $cantidadVerificada = $escaneosDetalle + 1;
        $detalleCompleto = $cantidadVerificada >= (int) $detalle->cantidad_esperada;

        return back()->with('escaneo', [
            'detalle_id'          => $detalle->id,
            'cantidad_verificada' => $cantidadVerificada,
            'detalle_completo'    => $detalleCompleto,
        ]);
    }

    public function confirmar(Request $request, RecepcionBodega $recepcion): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($recepcion->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        if (!$recepcion->isPendiente()) {
            return back()->with('error', 'Esta recepción ya fue procesada.');
        }

        DB::transaction(function () use ($recepcion, $empresaId) {
            $detalles = $recepcion->detalles;
            $todosCompletados  = true;
            $algunoParcial     = false;
            $cantidadesRecibidas = []; // producto_id => cantidad_recibida

            foreach ($detalles as $detalle) {
                $cantEscaneos = RecepcionEscaneo::where('recepcion_detalle_id', $detalle->id)->count();
                $cantEsperada = (int) $detalle->cantidad_esperada;

                if ($cantEscaneos >= $cantEsperada) {
                    $estadoDetalle = 'completado';
                } elseif ($cantEscaneos > 0) {
                    $estadoDetalle = 'parcial';
                    $todosCompletados = false;
                    $algunoParcial    = true;
                } else {
                    $estadoDetalle = 'pendiente';
                    $todosCompletados = false;
                }

                $detalle->update([
                    'cantidad_recibida' => $cantEscaneos,
                    'estado'            => $estadoDetalle,
                ]);

                if ($detalle->producto_id && $cantEscaneos > 0) {
                    $cantidadesRecibidas[$detalle->producto_id] = $cantEscaneos;
                }
            }

            $estadoRecepcion = $todosCompletados ? 'completada' : ($algunoParcial ? 'parcial' : 'pendiente');

            $recepcion->update([
                'estado'          => $estadoRecepcion,
                'recibido_por'    => Auth::id(),
                'fecha_recepcion' => now()->toDateString(),
            ]);

            if ($estadoRecepcion === 'completada') {
                $compra = $recepcion->compra->fresh();
                $eraCompraPendiente = $compra->estaPendiente();

                $compra->update(['estado' => 'activa']);

                // Solo ejecutar efectos financieros si la compra era pendiente
                // (si ya estaba activa, CompraController::activar() ya los procesó)
                if ($eraCompraPendiente) {
                    $compra->load('detalles');

                    // Ingresar stock por unidades físicamente recibidas
                    foreach ($cantidadesRecibidas as $productoId => $cantRecibida) {
                        $cd = $compra->detalles->firstWhere('producto_id', $productoId);
                        $costo = $cd ? (float) $cd->precio_unitario : 0;
                        try {
                            $this->inventario->ingresarStock(
                                productoId:    (int) $productoId,
                                bodegaId:      (int) $recepcion->bodega_id,
                                cantidad:      (float) $cantRecibida,
                                costoUnitario: $costo,
                                docTipo:       'COMPRA',
                                docId:         $compra->id,
                                docNumero:     $compra->num_documento,
                                observacion:   "Recepción #{$recepcion->id}: {$compra->num_documento}",
                            );
                            Producto::where('id', $productoId)
                                ->update(['costo' => $costo, 'updated_at' => now()]);
                        } catch (\Exception $e) {
                            \Log::warning("Stock recepción #{$recepcion->id} prod {$productoId}: {$e->getMessage()}");
                        }
                    }

                    // CxP solo si no existe ya para esta compra
                    if ($compra->dias_credito > 0 && !$compra->cuentaPagar()->exists()) {
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

                    // Asiento contable (no bloquear si falla)
                    try {
                        $asiento = $this->asientoService->compraRegistrada(
                            empresaId:  (int) $empresaId,
                            compraId:   $compra->id,
                            referencia: $compra->num_documento,
                            subtotal:   $compra->subtotal_0 + $compra->subtotal_iva,
                            iva:        $compra->total_iva,
                            tipo:       $compra->gasto_no_deducible ? 'gasto' : 'inventario',
                        );
                        $compra->update(['asiento_id' => $asiento->id]);
                    } catch (\Throwable $e) {
                        \Log::warning("Asiento compra {$compra->num_documento}: {$e->getMessage()}");
                    }
                }
            }

            $this->auditoria->documento(
                'confirmar',
                'inventario',
                'recepciones_bodega',
                $recepcion->id,
                "Recepción #{$recepcion->id} confirmada — estado: {$estadoRecepcion}"
            );
        });

        return back()->with('success', 'Recepción confirmada correctamente.');
    }

    public function etiquetasPendientes(RecepcionBodega $recepcion): JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($recepcion->empresa_id !== (int) $empresaId) {
            abort(403);
        }

        $recepcion->load('detalles.producto');

        $escaneosCodigos = RecepcionEscaneo::where('recepcion_id', $recepcion->id)
            ->pluck('codigo_escaneado')
            ->flip();

        $etiquetas = EtiquetaProducto::where('compra_id', $recepcion->compra_id)->get();

        $resultado = [];

        foreach ($etiquetas as $etiqueta) {
            $detalle = $recepcion->detalles->firstWhere('producto_id', $etiqueta->producto_id);
            if (!$detalle) {
                continue;
            }

            for ($i = $etiqueta->correlativo_desde; $i <= $etiqueta->correlativo_hasta; $i++) {
                $codigoEscaneado = $etiqueta->codigo_producto . '-' . str_pad($i, 6, '0', STR_PAD_LEFT);

                $resultado[] = [
                    'detalle_id'       => $detalle->id,
                    'producto_id'      => $detalle->producto_id,
                    'producto_nombre'  => $detalle->producto->nombre ?? '',
                    'codigo_escaneado' => $codigoEscaneado,
                    'verificado'       => $escaneosCodigos->has($codigoEscaneado),
                ];
            }
        }

        return response()->json($resultado);
    }
}
