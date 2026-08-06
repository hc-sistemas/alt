<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Exports\ComprasExport;
use App\Models\Bodega;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CuentaPagar;
use App\Models\EjercicioContable;
use App\Models\EtiquetaProducto;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\Proveedor;
use App\Models\PlanCuenta;
use App\Models\RecepcionBodega;
use App\Models\CentroCosto;
use App\Models\Importacion;
use App\Models\Producto;
use App\Models\Retencion;
use App\Models\RetencionDetalle;
use App\Services\AsientoService;
use App\Services\Contracts\InventarioServiceInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Response as HttpResponse;
use Maatwebsite\Excel\Facades\Excel;
use Picqer\Barcode\BarcodeGenerator;
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

        // Carga bajo demanda: mismo patrón que Asientos Contables — la query
        // paginada solo se ejecuta cuando el usuario dispara una búsqueda
        // explícita (botón lupa del FilterToolbar), nunca en la carga inicial
        // de la página. Los catálogos de apoyo (proveedores, productos, etc.)
        // sí se cargan siempre: los necesita el modal "Nueva Factura" y los
        // selects de filtro incluso antes de buscar.
        $compras = null;

        if ($request->boolean('buscado')) {
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

            $compras = $query->orderByDesc('fecha_emision')->paginate(20)->withQueryString();
        }

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

        $productos = Producto::where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'unidad', 'costo', 'porcentaje_iva', 'tipo', 'pvp']);

        $importacionesActivas = Importacion::where('empresa_id', $empresaId)
            ->whereIn('estado', ['en_transito', 'en_aduana'])
            ->orderByDesc('created_at')
            ->get(['id', 'nombre', 'pais_embarque', 'costo_fob', 'divisa', 'estado']);

        // Soporte para abrir el modal pre-llenado desde importaciones
        $prefillExterior = null;
        if ($request->filled('iniciar_exterior')) {
            $imp = Importacion::find((int) $request->iniciar_exterior);
            if ($imp && $imp->empresa_id === $empresaId) {
                $prefillExterior = [
                    'tipo_documento'      => 'EXT',
                    'proveedor_id'        => $imp->proveedor_id,
                    'num_documento'       => $imp->num_invoice ?? '',
                    'fecha_emision'       => $imp->fecha_llegada
                                            ? $imp->fecha_llegada->format('Y-m-d')
                                            : now()->format('Y-m-d'),
                    'importacion_id'      => $imp->id,
                    'sustento_tributario' => '06',
                    'dias_credito'        => 0,
                    'metodo_envio'        => 'FOB',
                    'divisa'              => $imp->divisa ?? 'USD',
                    'concepto'            => $imp->nombre,
                ];
            }
        }

        return Inertia::render('Compras/Compras/Index', [
            'compras'               => $compras,
            'proveedores'           => $proveedores,
            'centros'               => $centros,
            'cuentas'               => $cuentas,
            'bodegas'               => $bodegas,
            'productos'             => $productos,
            'importacionesActivas'  => $importacionesActivas,
            'prefillExterior'       => $prefillExterior,
            'filtros'               => $request->only(['buscar', 'estado', 'fecha_desde', 'fecha_hasta']),
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
            'importacion_id'             => 'nullable|exists:importaciones,id',
            'gasto_no_deducible'         => 'boolean',
            'concepto'                   => 'nullable|string|max:500',
            'metodo_envio'               => 'nullable|string|max:10',
            'divisa'                     => 'nullable|string|max:10',
            'tipo_cambio'                => 'nullable|numeric|min:0.0001',
            'num_orden_compra'           => 'nullable|string|max:50',
            'num_contrato'               => 'nullable|string|max:50',
            'vigencia_desde'             => 'nullable|date',
            'vigencia_hasta'             => 'nullable|date',
            'retencion_ir'               => 'nullable|numeric|min:0',
            'retencion_iva'              => 'nullable|numeric|min:0',
            'detalles'                   => 'required|array|min:1',
            'detalles.*.descripcion'     => 'required|string|max:500',
            'detalles.*.cantidad'        => 'required|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_iva'  => 'numeric|min:0|max:100',
            'detalles.*.peso'            => 'nullable|numeric|min:0',
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
                $esExterior  = $request->tipo_documento === 'EXT';

                $esGastoNoDeducible = $request->boolean('gasto_no_deducible');

                $detalles = collect($request->detalles)->map(function ($d) use (&$subtotal0, &$subtotalIva, &$totalIva, $esExterior, $esGastoNoDeducible) {
                    $subtotal = round($d['cantidad'] * $d['precio_unitario'] - ($d['descuento'] ?? 0), 4);
                    // Exterior y gasto no deducible siempre IVA 0% (CxP-03)
                    $porcIva  = ($esExterior || $esGastoNoDeducible) ? 0 : (float)($d['porcentaje_iva'] ?? 15);
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

                $tipo = $request->tipo_documento;
                $sustento = match($tipo) {
                    'EXT'   => 6,
                    default => $request->sustento_tributario ? (int) $request->sustento_tributario : null,
                };

                $compra = Compra::create([
                    'empresa_id'          => $empresaId,
                    'proveedor_id'        => $request->proveedor_id,
                    'centro_costo_id'     => $request->centro_costo_id,
                    'importacion_id'      => in_array($tipo, ['EXT', 'LIQ']) ? $request->importacion_id : null,
                    'bodega_id'           => $request->bodega_id,
                    'tipo_documento'      => $tipo,
                    'num_documento'       => $request->num_documento,
                    'num_autorizacion'    => in_array($tipo, ['EXT', 'TIK']) ? null : $request->num_autorizacion,
                    'fecha_emision'       => $request->fecha_emision,
                    'fecha_registro'      => now()->toDateString(),
                    'fecha_vencimiento'   => $fechaVenc,
                    'dias_credito'        => $tipo === 'TIK' ? 0 : ($request->dias_credito ?? 0),
                    'subtotal_0'          => $subtotal0,
                    'subtotal_iva'        => $subtotalIva,
                    'total_iva'           => $totalIva,
                    'total'               => $total,
                    'retencion_ir'        => $esGastoNoDeducible ? 0.0 : (float) ($request->retencion_ir  ?? 0),
                    'retencion_iva'       => $esGastoNoDeducible ? 0.0 : (float) ($request->retencion_iva ?? 0),
                    'iva_asumido'         => $request->boolean('iva_asumido'),
                    'gasto_no_deducible'  => $esGastoNoDeducible,
                    'sustento_tributario' => $sustento,
                    'concepto'            => $request->concepto,
                    'estado'              => 'pendiente',
                    'created_by'          => Auth::id(),
                    'metodo_envio'        => $tipo === 'EXT' ? $request->metodo_envio : null,
                    'divisa'              => $tipo === 'EXT' ? ($request->divisa ?? 'USD') : null,
                    'tipo_cambio'         => $tipo === 'EXT' && $request->divisa !== 'USD' ? $request->tipo_cambio : null,
                    'num_orden_compra'    => $tipo === 'EXT' ? $request->num_orden_compra : null,
                    'num_contrato'        => $tipo === 'CON' ? $request->num_contrato : null,
                    'vigencia_desde'      => $tipo === 'CON' ? $request->vigencia_desde : null,
                    'vigencia_hasta'      => $tipo === 'CON' ? $request->vigencia_hasta : null,
                ]);

                foreach ($detalles as $d) {
                    CompraDetalle::create(array_merge(
                        collect($d)->only([
                            'producto_id', 'cuenta_id', 'descripcion', 'cantidad', 'peso',
                            'precio_unitario', 'descuento', 'subtotal',
                            'porcentaje_iva', 'valor_iva', 'total', 'es_activo_fijo',
                        ])->toArray(),
                        ['compra_id' => $compra->id]
                    ));
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
            $this->aplicarEfectosOperativos($compra, $empresaId);
        });

        return back()->with('success',
            "Factura {$compra->num_documento} confirmada. Inventario y CxP actualizados.");
    }

    // ── Efectos operativos de activar una compra: inventario, recepción, CxP,
    //    retención y asiento contable. Reutilizado por activar() y por update()
    //    cuando una factura ya activa se corrige y hay que regenerar todo. ──────
    private function aplicarEfectosOperativos(Compra $compra, int $empresaId): void
    {
        $compra->loadMissing('detalles');

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

        // Crear retención si aplica — CxP-03: jamás si es gasto no deducible
        $retIR  = (float) ($compra->retencion_ir  ?? 0);
        $retIVA = (float) ($compra->retencion_iva ?? 0);
        if (!$compra->gasto_no_deducible && ($retIR > 0 || $retIVA > 0)) {
            try {
                $proveedor = $compra->proveedor;
                $empresa   = \App\Models\Empresa::find($empresaId);
                $secuencial = \DB::table('secuenciales')
                    ->where('tipo_documento', 'retencion')
                    ->first();
                $numSec = $secuencial
                    ? str_pad($secuencial->siguiente, 9, '0', STR_PAD_LEFT)
                    : str_pad(1, 9, '0', STR_PAD_LEFT);
                $establecimiento = $empresa->cod_establecimiento ?? '001';
                $puntoEmision    = $empresa->cod_punto_emision    ?? '001';
                $numeroCompleto  = "{$establecimiento}-{$puntoEmision}-{$numSec}";

                $retencion = Retencion::create([
                    'empresa_id'       => $empresaId,
                    'compra_id'        => $compra->id,
                    'usuario_id'       => Auth::id(),
                    'establecimiento'  => $establecimiento,
                    'punto_emision'    => $puntoEmision,
                    'secuencial'       => $numSec,
                    'numero_completo'  => $numeroCompleto,
                    'fecha_emision'    => now()->toDateString(),
                    'identificacion'   => $proveedor?->identificacion,
                    'razon_social'     => $proveedor?->razon_social,
                    'num_comp_retenido'=> $compra->num_documento,
                    'total'            => round($retIR + $retIVA, 4),
                    'estado'           => 'activa',
                ]);

                if ($retIR > 0) {
                    RetencionDetalle::create([
                        'retencion_id'  => $retencion->id,
                        'tipo'          => 'IR',
                        'codigo'        => '304',
                        'porcentaje'    => $compra->subtotal_0 + $compra->subtotal_iva > 0
                            ? round($retIR / ($compra->subtotal_0 + $compra->subtotal_iva) * 100, 2)
                            : 1,
                        'base_imponible'=> $compra->subtotal_0 + $compra->subtotal_iva,
                        'valor_retenido'=> $retIR,
                    ]);
                }
                if ($retIVA > 0) {
                    RetencionDetalle::create([
                        'retencion_id'  => $retencion->id,
                        'tipo'          => 'IVA',
                        'codigo'        => '9',
                        'porcentaje'    => $compra->total_iva > 0
                            ? round($retIVA / $compra->total_iva * 100, 2)
                            : 30,
                        'base_imponible'=> $compra->total_iva,
                        'valor_retenido'=> $retIVA,
                    ]);
                }

                if ($secuencial) {
                    \DB::table('secuenciales')
                        ->where('tipo_documento', 'retencion')
                        ->increment('siguiente');
                }
            } catch (\Throwable $e) {
                \Log::warning("Retención compra {$compra->num_documento}: {$e->getMessage()}");
            }
        }

        try {
            // CxP-03: gasto no deducible → cuenta 5.4.1.01, sin IVA ni retenciones
            $tipoAsiento = match(true) {
                $compra->gasto_no_deducible            => 'no_deducible',
                $compra->tipo_documento === 'EXT'      => 'gasto',
                !$compra->detalles->contains(fn($d) => $d->producto_id !== null) => 'gasto',
                default                                => 'inventario',
            };
            $asiento = $this->asientoService->compraRegistrada(
                empresaId:     $empresaId,
                compraId:      $compra->id,
                referencia:    $compra->num_documento,
                subtotal:      $compra->subtotal_0 + $compra->subtotal_iva,
                iva:           $compra->gasto_no_deducible ? 0.0 : $compra->total_iva,
                retencionIR:   $compra->gasto_no_deducible ? 0.0 : $retIR,
                retencionIVA:  $compra->gasto_no_deducible ? 0.0 : $retIVA,
                tipo:          $tipoAsiento,
                centroCostoId: $compra->centro_costo_id,
                fecha:         $compra->fecha_emision?->toDateString(),
            );
            $compra->update(['asiento_id' => $asiento->id, 'asiento_error' => null]);
        } catch (\Throwable $e) {
            \Log::warning("Asiento compra {$compra->num_documento}: {$e->getMessage()}");
            $compra->update(['asiento_error' => $e->getMessage()]);
            $this->asientoService->notificarAsientoFallido(
                empresaId:  $empresaId,
                tabla:      'compras',
                registroId: $compra->id,
                referencia: "Compra {$compra->num_documento}",
                mensaje:    $e->getMessage(),
            );
        }
    }

    // ── Revertir inventario y asiento de una compra activa (sin tocar CxP,
    //    que cada llamador maneja según su propia semántica: anular() la
    //    cancela, update()/destroy() la elimina para regenerarla). ──────────────
    private function revertirInventarioYAsiento(Compra $compra, int $empresaId, string $motivo): void
    {
        $compra->loadMissing(['detalles', 'asiento']);

        $bodegaEfectiva = $compra->bodega_id
            ?? optional(Bodega::where('empresa_id', $empresaId)
                ->where('tipo', 'general')->first())->id;

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
                        observacion: $motivo,
                    );
                } catch (\Exception $e) {
                    \Log::warning("Reversión inv. producto {$d->producto_id}: {$e->getMessage()}");
                }
            }
        }

        if ($compra->asiento_id && $compra->asiento) {
            try {
                $this->asientoService->anular($compra->asiento, $motivo);
            } catch (\Exception $e) {
                \Log::warning("Asiento compra no revertido: {$e->getMessage()}");
            }
        }
    }

    // ── Verificar si una compra tiene un pago vinculado (candado CxP-02) ────────
    private function tienePagoVinculado(Compra $compra): bool
    {
        if ($compra->tiene_pago) return true;

        return MovimientoBancario::where('documento_tipo', 'COMPRA')
            ->where('documento_id', $compra->id)
            ->where('tipo', 'egreso')
            ->where('anulado', false)
            ->exists();
    }

    // ── Verificar si algún producto de la compra ya tiene salidas por venta ─────
    private function tieneVentasRegistradas(Compra $compra): bool
    {
        $compra->loadMissing('detalles');
        $productoIds = $compra->detalles->pluck('producto_id')->filter()->unique();

        if ($productoIds->isEmpty() || !$compra->fecha_emision) {
            return false;
        }

        return DB::table('inventario_movimientos')
            ->whereIn('producto_id', $productoIds)
            ->where('tipo', 'salida')
            ->where(fn($q) => $q
                ->whereNull('doc_tipo')
                ->orWhereNotIn('doc_tipo', ['ANULACION', 'ANULACION_COMPRA', 'AJUSTE', 'COMPRA'])
            )
            ->whereRaw("DATE(created_at) >= ?", [$compra->fecha_emision->toDateString()])
            ->exists();
    }

    // ── Verificar si la fecha dada cae en un período contable ya cerrado ────────
    private function ejercicioCerradoParaFecha(int $empresaId, string $fecha): ?EjercicioContable
    {
        $f  = \Carbon\Carbon::parse($fecha);
        $ej = EjercicioContable::where('empresa_id', $empresaId)
            ->where('anio', $f->year)
            ->where('mes', $f->month)
            ->first();

        return ($ej && $ej->estaCerrado()) ? $ej : null;
    }

    // ── Limpiar recepción y etiquetas de una compra antes de regenerarla o
    //    eliminarla — evita violar el FK de compra_id al reversar/eliminar. ─────
    private function limpiarRecepcionYEtiquetas(Compra $compra): void
    {
        $recepcion = RecepcionBodega::where('compra_id', $compra->id)->first();
        if ($recepcion) {
            \App\Models\RecepcionDetalle::where('recepcion_id', $recepcion->id)->delete();
            $recepcion->delete();
        }
        EtiquetaProducto::where('compra_id', $compra->id)->delete();
    }

    // ── Detalles de una compra para el modal de devoluciones ────────────────────

    public function detallesCompra(Compra $compra): JsonResponse
    {
        $compra->load('detalles');
        return response()->json([
            'bodega_id' => $compra->bodega_id,
            'detalles'  => $compra->detalles->map(fn($d) => [
                'id'              => $d->id,
                'producto_id'     => $d->producto_id,
                'cuenta_id'       => $d->cuenta_id,
                'descripcion'     => $d->descripcion,
                'cantidad'        => (float) $d->cantidad,
                'peso'            => $d->peso !== null ? (float) $d->peso : null,
                'precio_unitario' => (float) $d->precio_unitario,
                'descuento'       => (float) $d->descuento,
                'porcentaje_iva'  => (float) $d->porcentaje_iva,
                'es_activo_fijo'  => (bool) $d->es_activo_fijo,
            ])->values(),
        ]);
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

    public function generarEtiquetasPdf(Request $request, Compra $compra): HttpResponse|JsonResponse
    {
        // Candado: una factura genera etiquetas UNA sola vez
        if (EtiquetaProducto::where('compra_id', $compra->id)->exists()) {
            abort(response()->json([
                'error'  => true,
                'mensaje' => 'Esta factura ya tiene etiquetas generadas. Use la opción Reimprimir para volver a imprimirlas.',
            ], 422));
        }

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
            $yaExisteRecepcion = RecepcionBodega::where('compra_id', $compra->id)->exists();

            if (!$yaExisteRecepcion) {
                $bodegaEfectiva = $compra->bodega_id
                    ?? Bodega::where('empresa_id', $empresaId)
                        ->where('tipo', 'general')
                        ->value('id');

                if ($bodegaEfectiva) {
                    $recepcion = RecepcionBodega::create([
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
                        $generator->getBarcode($codigoBarras, BarcodeGenerator::TYPE_CODE_128, 2, 60)
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

            $png = $generator->getBarcode((string) $codigoBarras, BarcodeGenerator::TYPE_CODE_128, 2, 60);
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
                        $generator->getBarcode($codigoBarras, BarcodeGenerator::TYPE_CODE_128, 2, 60)
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
                    'mensaje'   => "Esta factura tiene un pago de \${$monto} en {$banco}. Debes usar el botón \"Anular Pago\" primero y luego podrás anular la factura.",
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

        if ($compra->tiene_pago) {
            return back()->with('error',
                "Primero debes anular el pago registrado para poder anular la factura {$compra->num_documento}.");
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

            $this->revertirInventarioYAsiento(
                $compra, $empresaId,
                "Anulación compra {$compra->num_documento}: {$request->motivo}"
            );

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

    // ── Anular solo el pago (la factura sigue activa) ───────────────────────────

    public function anularPago(Compra $compra): RedirectResponse
    {
        if (!$compra->tiene_pago) {
            return back()->with('error', 'Esta factura no tiene un pago registrado.');
        }
        if ($compra->estaAnulada()) {
            return back()->with('error', 'No se puede anular el pago de una factura anulada.');
        }

        $empresaId = session('empresa_activa_id');

        DB::transaction(function () use ($compra, $empresaId) {
            // 1. Buscar y revertir movimiento bancario de pago
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
                    'centro_costo_id' => $movPago->centro_costo_id,
                    'descripcion'    => "Reversión pago anulado — {$compra->num_documento}",
                    'documento_tipo' => 'ANULACION_PAGO',
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
                            "Reversión pago — {$compra->num_documento}"
                        );
                    } catch (\Exception $e) {
                        \Log::warning("Asiento pago no revertido: {$e->getMessage()}");
                    }
                }
            }

            // 2. Restaurar CxP a pendiente con saldo completo
            CuentaPagar::where('compra_id', $compra->id)->update([
                'estado' => 'pendiente',
                'saldo'  => $compra->total,
            ]);

            // 3. Marcar factura sin pago — estado sigue 'activa'
            $compra->update(['tiene_pago' => false]);
        });

        return back()->with('success',
            "Pago de {$compra->num_documento} anulado. La factura sigue activa y la deuda fue restaurada en Cuentas por Pagar.");
    }

    // ── Verificar si una factura puede editarse/eliminarse — Regla CxP-02 ───────
    public function verificarEdicion(Compra $compra): JsonResponse
    {
        try {
            if ($compra->estaAnulada()) {
                return response()->json([
                    'puede'  => false,
                    'motivo' => 'Esta compra está anulada.',
                ]);
            }

            if ($this->tienePagoVinculado($compra)) {
                return response()->json([
                    'puede'  => false,
                    'motivo' => 'No se puede editar/eliminar: tiene un pago registrado. Anule el pago primero desde el módulo de Bancos.',
                ]);
            }

            $empresaId = session('empresa_activa_id');
            $ejCerrado = $this->ejercicioCerradoParaFecha($empresaId, $compra->fecha_emision->toDateString());
            if ($ejCerrado) {
                return response()->json([
                    'puede'  => false,
                    'motivo' => "No se puede editar/eliminar: el período {$ejCerrado->periodo_label} está cerrado.",
                ]);
            }

            if ($compra->estaActiva() && $this->tieneVentasRegistradas($compra)) {
                return response()->json([
                    'puede'  => false,
                    'motivo' => 'No se puede editar/eliminar: uno o más productos de esta factura ya tienen ventas registradas.',
                ]);
            }

            return response()->json([
                'puede'     => true,
                'es_activa' => $compra->estaActiva(),
                'motivo'    => $compra->estaActiva()
                    ? 'Esta factura ya generó movimientos contables y de inventario. Editar/eliminar revertirá y regenerará esos efectos.'
                    : null,
            ]);
        } catch (\Throwable $e) {
            \Log::error("verificarEdicion error compra#{$compra->id}: {$e->getMessage()} en {$e->getFile()}:{$e->getLine()}");
            return response()->json([
                'puede'  => false,
                'motivo' => 'Error al verificar: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ── Editar factura de compra — candado condicional Regla CxP-02 ─────────────
    public function update(Request $request, Compra $compra): RedirectResponse
    {
        if ($compra->estaAnulada()) {
            return back()->with('error', 'No se puede editar una factura anulada.');
        }

        if ($this->tienePagoVinculado($compra)) {
            return back()->with('error',
                "No se puede editar esta factura porque tiene un pago registrado. Anule primero el pago desde el módulo de Bancos.");
        }

        $empresaId = session('empresa_activa_id');

        $ejCerradoActual = $this->ejercicioCerradoParaFecha($empresaId, $compra->fecha_emision->toDateString());
        if ($ejCerradoActual) {
            return back()->with('error',
                "No se puede editar: el período {$ejCerradoActual->periodo_label} (fecha actual de la factura) está cerrado.");
        }

        $request->validate([
            'proveedor_id'               => 'required|exists:proveedores,id',
            'tipo_documento'             => 'required|in:FAC,LIQ,TIK,CON,EXT',
            'num_documento'              => 'required|string|max:30',
            'fecha_emision'              => 'required|date',
            'dias_credito'               => 'integer|min:0',
            'bodega_id'                  => 'nullable|exists:bodegas,id',
            'importacion_id'             => 'nullable|exists:importaciones,id',
            'gasto_no_deducible'         => 'boolean',
            'concepto'                   => 'nullable|string|max:500',
            'metodo_envio'               => 'nullable|string|max:10',
            'divisa'                     => 'nullable|string|max:10',
            'tipo_cambio'                => 'nullable|numeric|min:0.0001',
            'num_orden_compra'           => 'nullable|string|max:50',
            'num_contrato'               => 'nullable|string|max:50',
            'vigencia_desde'             => 'nullable|date',
            'vigencia_hasta'             => 'nullable|date',
            'retencion_ir'               => 'nullable|numeric|min:0',
            'retencion_iva'              => 'nullable|numeric|min:0',
            'detalles'                   => 'required|array|min:1',
            'detalles.*.descripcion'     => 'required|string|max:500',
            'detalles.*.cantidad'        => 'required|numeric|min:0.0001',
            'detalles.*.precio_unitario' => 'required|numeric|min:0',
            'detalles.*.porcentaje_iva'  => 'numeric|min:0|max:100',
            'detalles.*.peso'            => 'nullable|numeric|min:0',
        ]);

        $ejCerradoNuevo = $this->ejercicioCerradoParaFecha($empresaId, $request->fecha_emision);
        if ($ejCerradoNuevo) {
            return back()->with('error',
                "No se puede mover la factura al período {$ejCerradoNuevo->periodo_label}: está cerrado.");
        }

        $existe = Compra::where('empresa_id', $empresaId)
            ->where('proveedor_id', $request->proveedor_id)
            ->where('num_documento', $request->num_documento)
            ->where('id', '!=', $compra->id)
            ->exists();

        if ($existe) {
            return back()->with('error',
                "Ya existe otra compra con el documento {$request->num_documento} de este proveedor.");
        }

        $eraActiva = $compra->estaActiva();

        if ($eraActiva && $this->tieneVentasRegistradas($compra)) {
            return back()->with('error',
                'No se puede editar: uno o más productos de esta factura ya tienen ventas registradas. Anule la factura y registre una nueva corregida en su lugar.');
        }

        try {
            DB::transaction(function () use ($request, $compra, $empresaId, $eraActiva) {
                if ($eraActiva) {
                    $this->revertirInventarioYAsiento(
                        $compra, $empresaId,
                        "Corrección de factura {$compra->num_documento}"
                    );
                    CuentaPagar::where('compra_id', $compra->id)->delete();

                    $retencionIds = Retencion::where('compra_id', $compra->id)->pluck('id');
                    if ($retencionIds->isNotEmpty()) {
                        RetencionDetalle::whereIn('retencion_id', $retencionIds)->delete();
                        Retencion::whereIn('id', $retencionIds)->delete();
                    }

                    $this->limpiarRecepcionYEtiquetas($compra);

                    $compra->update(['asiento_id' => null, 'asiento_error' => null]);
                }

                $subtotal0   = 0;
                $subtotalIva = 0;
                $totalIva    = 0;
                $esExterior  = $request->tipo_documento === 'EXT';
                $esGastoNoDeducible = $request->boolean('gasto_no_deducible');

                $detalles = collect($request->detalles)->map(function ($d) use (&$subtotal0, &$subtotalIva, &$totalIva, $esExterior, $esGastoNoDeducible) {
                    $subtotal = round($d['cantidad'] * $d['precio_unitario'] - ($d['descuento'] ?? 0), 4);
                    // Exterior y gasto no deducible siempre IVA 0% (CxP-03)
                    $porcIva  = ($esExterior || $esGastoNoDeducible) ? 0 : (float)($d['porcentaje_iva'] ?? 15);
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

                $tipo = $request->tipo_documento;
                $sustento = match($tipo) {
                    'EXT'   => 6,
                    default => $request->sustento_tributario ? (int) $request->sustento_tributario : null,
                };

                $compra->update([
                    'proveedor_id'        => $request->proveedor_id,
                    'centro_costo_id'     => $request->centro_costo_id,
                    'importacion_id'      => in_array($tipo, ['EXT', 'LIQ']) ? $request->importacion_id : null,
                    'bodega_id'           => $request->bodega_id,
                    'tipo_documento'      => $tipo,
                    'num_documento'       => $request->num_documento,
                    'num_autorizacion'    => in_array($tipo, ['EXT', 'TIK']) ? null : $request->num_autorizacion,
                    'fecha_emision'       => $request->fecha_emision,
                    'fecha_vencimiento'   => $fechaVenc,
                    'dias_credito'        => $tipo === 'TIK' ? 0 : ($request->dias_credito ?? 0),
                    'subtotal_0'          => $subtotal0,
                    'subtotal_iva'        => $subtotalIva,
                    'total_iva'           => $totalIva,
                    'total'               => $total,
                    'retencion_ir'        => $esGastoNoDeducible ? 0.0 : (float) ($request->retencion_ir  ?? 0),
                    'retencion_iva'       => $esGastoNoDeducible ? 0.0 : (float) ($request->retencion_iva ?? 0),
                    'iva_asumido'         => $request->boolean('iva_asumido'),
                    'gasto_no_deducible'  => $esGastoNoDeducible,
                    'sustento_tributario' => $sustento,
                    'concepto'            => $request->concepto,
                    'metodo_envio'        => $tipo === 'EXT' ? $request->metodo_envio : null,
                    'divisa'              => $tipo === 'EXT' ? ($request->divisa ?? 'USD') : null,
                    'tipo_cambio'         => $tipo === 'EXT' && $request->divisa !== 'USD' ? $request->tipo_cambio : null,
                    'num_orden_compra'    => $tipo === 'EXT' ? $request->num_orden_compra : null,
                    'num_contrato'        => $tipo === 'CON' ? $request->num_contrato : null,
                    'vigencia_desde'      => $tipo === 'CON' ? $request->vigencia_desde : null,
                    'vigencia_hasta'      => $tipo === 'CON' ? $request->vigencia_hasta : null,
                ]);

                $compra->detalles()->delete();
                foreach ($detalles as $d) {
                    CompraDetalle::create(array_merge(
                        collect($d)->only([
                            'producto_id', 'cuenta_id', 'descripcion', 'cantidad', 'peso',
                            'precio_unitario', 'descuento', 'subtotal',
                            'porcentaje_iva', 'valor_iva', 'total', 'es_activo_fijo',
                        ])->toArray(),
                        ['compra_id' => $compra->id]
                    ));
                }

                if ($eraActiva) {
                    $compra->refresh();
                    $compra->load('detalles');
                    $this->aplicarEfectosOperativos($compra, $empresaId);
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success',
            "Factura {$compra->num_documento} actualizada correctamente." .
            ($eraActiva ? ' Se revirtieron y regeneraron los efectos contables y de inventario.' : ''));
    }

    // ── Eliminar factura de compra — candado condicional Regla CxP-02 ──────────
    public function destroy(Compra $compra): RedirectResponse
    {
        if ($compra->estaAnulada()) {
            return back()->with('error', 'Esta factura ya está anulada. No se puede eliminar, solo consultar.');
        }

        if ($this->tienePagoVinculado($compra)) {
            return back()->with('error',
                "No se puede eliminar esta factura porque tiene un pago registrado. Anule primero el pago desde el módulo de Bancos.");
        }

        $empresaId = session('empresa_activa_id');

        $ejCerrado = $this->ejercicioCerradoParaFecha($empresaId, $compra->fecha_emision->toDateString());
        if ($ejCerrado) {
            return back()->with('error',
                "No se puede eliminar: el período {$ejCerrado->periodo_label} está cerrado.");
        }

        $compra->load(['detalles', 'cuentaPagar', 'asiento']);

        $eraActiva = $compra->estaActiva();

        if ($eraActiva && $this->tieneVentasRegistradas($compra)) {
            return back()->with('error',
                'No se puede eliminar: uno o más productos de esta factura ya tienen ventas registradas. Anule la factura en su lugar.');
        }

        $numero = $compra->num_documento;

        try {
            DB::transaction(function () use ($compra, $empresaId, $eraActiva) {
                if ($eraActiva) {
                    $this->revertirInventarioYAsiento(
                        $compra, $empresaId,
                        "Eliminación de factura {$compra->num_documento}"
                    );
                    CuentaPagar::where('compra_id', $compra->id)->delete();

                    $retencionIds = Retencion::where('compra_id', $compra->id)->pluck('id');
                    if ($retencionIds->isNotEmpty()) {
                        RetencionDetalle::whereIn('retencion_id', $retencionIds)->delete();
                        Retencion::whereIn('id', $retencionIds)->delete();
                    }

                    $this->limpiarRecepcionYEtiquetas($compra);
                }

                $compra->detalles()->delete();
                $compra->delete();
            });
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Factura {$numero} eliminada correctamente.");
    }

    public function pdf(Request $request): \Illuminate\Http\Response
    {
        // DomPDF con el histórico completo sin filtro (~2,239 facturas reales)
        // midió un pico de ~1,754MB — margen sobre ese pico medido, no un
        // valor arbitrario (mismo hallazgo que tenía ExportarComprasJob).
        ini_set('memory_limit', '2560M');

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

    public function parsearXml(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xml|max:2048',
        ]);

        try {
            $contenido = file_get_contents($request->file('archivo')->getRealPath());
            $xml = new \SimpleXMLElement($contenido);

            // Extraer información tributaria del proveedor
            $info    = $xml->infoTributaria ?? $xml->InfoTributaria ?? null;
            $factura = $xml->infoFactura    ?? $xml->InfoFactura    ?? null;

            $ruc          = (string) ($info->ruc            ?? $info->rucProveedor       ?? '');
            $razonSocial  = (string) ($info->razonSocial    ?? $info->nombreComercial     ?? '');
            $estab        = (string) ($info->estab          ?? '001');
            $ptoEmi       = (string) ($info->ptoEmi         ?? '001');
            $secuencial   = (string) ($info->secuencial     ?? '');
            $claveAcceso  = (string) ($info->claveAcceso    ?? '');

            $fechaEmision     = (string) ($factura->fechaEmision        ?? '');
            $totalSinIva      = (float)  ($factura->totalSinImpuestos   ?? 0);
            $descuento        = (float)  ($factura->totalDescuento       ?? 0);
            $importeTotal     = (float)  ($factura->importeTotal         ?? 0);

            // Calcular IVA desde totalConImpuestos
            $totalIva = 0.0;
            foreach ($factura->totalConImpuestos->totalImpuesto ?? [] as $imp) {
                if ((int)($imp->codigo ?? 0) === 2) {  // código 2 = IVA
                    $totalIva += (float) ($imp->valor ?? 0);
                }
            }

            // Num documento: estab-ptoEmi-secuencial
            $numDocumento = trim("{$estab}-{$ptoEmi}-{$secuencial}", '-');

            // Buscar proveedor por RUC
            $empresaId = session('empresa_activa_id');
            $proveedor = null;
            if ($ruc) {
                $proveedor = Proveedor::where('empresa_id', $empresaId)
                    ->where('identificacion', $ruc)
                    ->first(['id', 'razon_social', 'identificacion']);
            }

            // Detalles de la factura
            $detalles = [];
            foreach ($xml->detalles->detalle ?? [] as $d) {
                $detalles[] = [
                    'codigo'       => (string) ($d->codigoPrincipal  ?? $d->codigoAuxiliar ?? ''),
                    'descripcion'  => (string) ($d->descripcion ?? ''),
                    'cantidad'     => (float)  ($d->cantidad       ?? 1),
                    'precio'       => (float)  ($d->precioUnitario ?? 0),
                    'subtotal'     => (float)  ($d->precioTotalSinImpuesto ?? $d->subtotal ?? 0),
                ];
            }

            // Convertir fecha dd/mm/yyyy → yyyy-mm-dd
            $fechaFormateada = null;
            if ($fechaEmision) {
                $partes = explode('/', $fechaEmision);
                if (count($partes) === 3) {
                    $fechaFormateada = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
                }
            }

            return response()->json([
                'ok'           => true,
                'ruc'          => $ruc,
                'razon_social' => $razonSocial,
                'proveedor_id' => $proveedor?->id,
                'num_documento'=> $numDocumento,
                'clave_acceso' => $claveAcceso,
                'fecha_emision'=> $fechaFormateada,
                'subtotal'     => round($totalSinIva - $descuento, 4),
                'descuento'    => round($descuento, 4),
                'iva'          => round($totalIva, 4),
                'total'        => round($importeTotal, 4),
                'detalles'     => $detalles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo leer el archivo XML: ' . $e->getMessage(),
            ], 422);
        }
    }
}
