<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Ventas\Concerns\ResuelveBodegasFijas;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaPago;
use App\Models\LimiteDescuento;
use App\Models\Producto;
use App\Models\Proforma;
use App\Models\ProformaDetalle;
use App\Models\Usuario;
use App\Services\AprobacionService;
use App\Services\AuditoriaService;
use App\Services\DescuentoService;
use App\Services\DocumentoVentaPdf;
use App\Services\InventarioService;
use App\Services\SecuencialService;
use App\Support\ReglasPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProformaController extends Controller
{
    use ResuelveBodegasFijas;

    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
        private DescuentoService  $descuento,
        private InventarioService $inventario,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        // Primera visita: fechas por defecto = hoy. Si el usuario borra una fecha,
        // el parámetro llega vacío (has() = true) y no se vuelve a forzar.
        $hoy = now()->toDateString();
        $request->merge([
            'fecha_desde' => $request->has('fecha_desde') ? $request->fecha_desde : $hoy,
            'fecha_hasta' => $request->has('fecha_hasta') ? $request->fecha_hasta : $hoy,
        ]);

        $query = Proforma::with(['cliente', 'usuario'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        // Búsqueda libre: cada palabra debe aparecer en algún campo (número, cliente,
        // RUC, vendedor, observaciones o productos), sin importar el orden.
        if ($request->filled('cliente')) {
            foreach (preg_split('/\s+/', trim((string) $request->cliente)) as $termino) {
                $like = '%' . addcslashes($termino, '%_\\') . '%';

                $query->where(function ($q) use ($like) {
                    $q->where('numero', 'ilike', $like)
                      ->orWhere('observaciones', 'ilike', $like)
                      ->orWhereHas('cliente', fn ($c) => $c
                          ->where('razon_social', 'ilike', $like)
                          ->orWhere('nombre_comercial', 'ilike', $like)
                          ->orWhere('identificacion', 'ilike', $like)
                          ->orWhere('email', 'ilike', $like)
                          ->orWhere('telefono', 'ilike', $like))
                      ->orWhereHas('usuario', fn ($u) => $u->where('nombre', 'ilike', $like))
                      ->orWhereHas('detalles', fn ($d) => $d
                          ->where('descripcion', 'ilike', $like)
                          ->orWhereHas('producto', fn ($p) => $p->where('codigo', 'ilike', $like)));
                });
            }
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $proformas = $query->paginate(25)->withQueryString();

        // "Nuevo": es la primera proforma que se le hizo a ese cliente.
        $primeras = Proforma::where('empresa_id', $empresaId)
            ->whereIn('cliente_id', $proformas->pluck('cliente_id')->unique())
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, min(id) as primera')
            ->pluck('primera', 'cliente_id');

        $proformas->through(function (Proforma $p) use ($primeras) {
            $bruto = (float) $p->subtotal + (float) $p->descuento_total;
            $p->setAttribute('cliente_nuevo', (int) ($primeras[$p->cliente_id] ?? 0) === $p->id);
            $p->setAttribute('desc_pct', $bruto > 0 ? round((float) $p->descuento_total / $bruto * 100, 2) : 0);
            $p->setAttribute('vendedor', $p->usuario?->nombre);

            return $p;
        });

        return Inertia::render('Ventas/Proformas/Index', [
            'proformas' => $proformas,
            'filtros'   => $request->only(['estado', 'cliente', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create()
    {
        $empresaId = session('empresa_activa_id');
        $usuario   = Auth::user();

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->select('id', 'identificacion', 'razon_social', 'tiene_credito', 'dias_credito',
        'cupo_maximo', 'tipo_identificacion', 'email', 'telefono', 'direccion', 'ciudad')
            ->orderBy('razon_social')
            ->get();

        // Sin 'costo': no se usa en Proforma (no hay chequeo de precio bajo
        // costo aquí) y no debe llegar al navegador del vendedor (CHECKLIST_ERRORES_COMPLICACIONES.md, A2).
        $productos = Producto::where('estado', true)
            ->select('id', 'codigo', 'nombre', 'pvp', 'pvd', 'porcentaje_iva')
            ->orderBy('nombre')
            ->get();

        $descuentosMaximos = $this->descuento->mapaMaximosPermitidos($productos->pluck('id')->all(), $empresaId);
        $productos->each(function ($p) use ($descuentosMaximos) {
            $p->descuento_max = $descuentosMaximos[$p->id] ?? 0.0;
        });

        // Solo informativo — Proforma no reserva ni descuenta stock, es una
        // cotización. Si la Bodega Principal no está configurada, se muestra
        // 0 en vez de romper la página (no es un dato crítico aquí).
        try {
            $bodegaPrincipalId = $this->bodegaPrincipalId();
            $saldos = DB::table('inventario_saldos')
                ->where('bodega_id', $bodegaPrincipalId)
                ->whereIn('producto_id', $productos->pluck('id'))
                ->select('producto_id', 'stock_actual', 'cantidad_reservada')
                ->get()
                ->keyBy('producto_id');
        } catch (\RuntimeException) {
            $saldos = collect();
        }

        $productos->each(function ($p) use ($saldos) {
            $saldo = $saldos->get($p->id);
            $p->stock_disponible = $saldo
                ? max(0, (float) $saldo->stock_actual - (float) ($saldo->cantidad_reservada ?? 0))
                : 0.0;
        });

        $perfilNombre = DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', $usuario->id)
            ->value('perfiles.nombre');

        if ($perfilNombre === 'vendedor') {
            $vendedores = collect([$usuario]);
        } else {
            $vendedores = Usuario::whereHas('perfil', fn($q) => $q->where('nombre', 'vendedor'))
                ->where('empresa_id', $empresaId)
                ->where('estado', true)
                ->select('id', 'nombre', 'email')
                ->orderBy('nombre')
                ->get();
        }

        $empresa = Empresa::findOrFail($empresaId);
        $est = $empresa->cod_establecimiento ?? '001';
        $pe  = $empresa->cod_punto_emision   ?? '001';

        $sec = DB::table('secuenciales')
            ->where('empresa_id', $empresaId)
            ->where('tipo_documento', 'PRF')
            ->first();

        $siguienteNumero = $sec
            ? sprintf('%s-%s-%09d', $est, $pe, (int)($sec->secuencial ?? $sec->siguiente ?? 1))
            : sprintf('%s-%s-%09d', $est, $pe, 1);

        $limite = LimiteDescuento::whereHas('perfil', fn($q) => $q->where('nombre', $perfilNombre))
            ->first();

        return Inertia::render('Ventas/Proformas/Form', [
            'clientes'         => $clientes,
            'productos'        => $productos,
            'vendedores'       => $vendedores,
            'formas_pago'      => ['efectivo', 'transferencia', 'tarjeta', 'cheque', 'credito'],
            'empresa_activa'   => $empresa,
            'siguiente_numero' => $siguienteNumero,
            'limites_descuento' => [
                'descuento_maximo_pct' => $limite?->porcentaje_maximo ?? 0,
                'puede_aprobar'        => $limite?->puede_aprobar ?? false,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'cliente_id'              => 'required|integer|exists:clientes,id',
            'detalles'                => 'required|array|min:1',
            'detalles.*.producto_id'  => 'required|integer',
            'detalles.*.cantidad'     => 'required|integer|min:1',
            'detalles.*.precio'       => 'required|numeric|min:0.01',
            'detalles.*.aprobacion_id'=> 'nullable|integer',
        ]);

        $usuario = Auth::user();

        $productoIds = collect($request->detalles)->pluck('producto_id')->unique()->all();
        $maximosPermitidos = $this->descuento->mapaMaximosPermitidos($productoIds, $empresaId);

        // Precio real desde la tabla productos (precio de lista, pvp). El
        // vendedor no puede cambiar el precio, solo dar descuento: el precio
        // que llega en el payload NUNCA se usa (ver
        // CHECKLIST_ERRORES_COMPLICACIONES.md, ítem A1).
        $preciosProductos = Producto::whereIn('id', $productoIds)->pluck('pvp', 'id');

        $perfilNombre = DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', $usuario->id)
            ->value('perfiles.nombre');

        $limiteDescuento = LimiteDescuento::whereHas('perfil', fn($q) => $q->where('nombre', $perfilNombre))
            ->first();

        $limiteMax = (float) ($limiteDescuento?->porcentaje_maximo ?? 0);

        // Aprobaciones usadas por línea — se marcan consumidas (una sola vez
        // por fila, aunque se repita en varias líneas) después de crear la
        // proforma.
        $aprobacionesUsadas = [];

        foreach ($request->detalles as $det) {
            $descPct = (float) ($det['descuento_pct'] ?? 0);
            $maximo  = $maximosPermitidos[$det['producto_id']] ?? 0.0;

            // Tope de producto/lista de precios — capa dura, sin excepción,
            // no se puede superar ni con aprobación especial.
            if ($descPct > $maximo) {
                return back()->withErrors([
                    'detalles' => "El descuento de {$det['codigo']} ({$descPct}%) supera el máximo permitido ({$maximo}%).",
                ])->withInput();
            }

            // Tope del perfil del vendedor — sí se puede superar con
            // aprobación especial de un supervisor, mientras no exceda el
            // tope de producto verificado arriba.
            if ($descPct > $limiteMax) {
                $aprobacionId = $det['aprobacion_id'] ?? null;
                if (!$aprobacionId) {
                    return back()->withErrors([
                        'detalles' => "El descuento de {$det['codigo']} ({$descPct}%) requiere aprobación especial.",
                    ])->withInput();
                }

                if (!isset($aprobacionesUsadas[$aprobacionId])) {
                    $aprobacion = DB::table('aprobaciones_especiales')
                        ->join('tipos_aprobacion', 'tipos_aprobacion.id', '=', 'aprobaciones_especiales.tipo_aprobacion_id')
                        ->where('aprobaciones_especiales.id', $aprobacionId)
                        ->where('aprobaciones_especiales.solicitado_por', $usuario->id)
                        ->where('tipos_aprobacion.clave', 'descuento_excedido')
                        ->whereNull('aprobaciones_especiales.registro_id')
                        ->select('aprobaciones_especiales.id', 'aprobaciones_especiales.valor_aprobado')
                        ->first();

                    if (!$aprobacion) {
                        return back()->withErrors([
                            'detalles' => 'La aprobación especial no es válida o ya fue utilizada.',
                        ])->withInput();
                    }

                    $aprobacionesUsadas[$aprobacionId] = $aprobacion;
                }

                if ($descPct > (float) $aprobacionesUsadas[$aprobacionId]->valor_aprobado) {
                    return back()->withErrors([
                        'detalles' => "La aprobación otorgada cubre hasta {$aprobacionesUsadas[$aprobacionId]->valor_aprobado}%, pero se solicita {$descPct}% en {$det['codigo']}.",
                    ])->withInput();
                }
            }
        }

        // Valores redondeados a centavos por línea; el IVA se calcula sobre la
        // base imponible total (igual que la factura).
        $subtotal  = 0;
        $descTotal = 0;
        $base15    = 0;

        foreach ($request->detalles as $det) {
            $cantidad  = (float)$det['cantidad'];
            $precio    = (float) ($preciosProductos[$det['producto_id']] ?? 0);
            $descPct   = (float)($det['descuento_pct'] ?? 0);
            $descuento = round($precio * $cantidad * ($descPct / 100), 2);
            $neto      = round(($precio * $cantidad) - $descuento, 2);
            $grabaIva  = (bool)($det['graba_iva'] ?? true);

            $subtotal  += $neto;
            $descTotal += $descuento;
            $base15    += $grabaIva ? $neto : 0;
        }

        $subtotal  = round($subtotal, 2);
        $descTotal = round($descTotal, 2);
        $totalIva  = round($base15 * 0.15, 2);
        $total     = round($subtotal + $totalIva, 2);

        $proforma = DB::transaction(function () use ($request, $empresaId, $subtotal, $descTotal, $totalIva, $total, $aprobacionesUsadas, $preciosProductos) {
            $numero = $this->secuencial->siguiente($empresaId, 'PRF');

            $proforma = Proforma::create([
                'empresa_id'      => $empresaId,
                'centro_costo_id' => $request->centro_costo_id,
                'cliente_id'      => $request->cliente_id,
                'usuario_id'      => Auth::id(),
                'numero'          => $numero,
                'fecha_emision'   => now()->toDateString(),
                'fecha_vencimiento'=> $request->fecha_vencimiento ?? now()->addDays(15)->toDateString(),
                'subtotal'        => $subtotal,
                'descuento_total' => $descTotal,
                'total_iva'       => $totalIva,
                'total'           => $total,
                'observaciones'   => $request->observaciones,
                'estado'          => 'pendiente',
            ]);

            foreach ($request->detalles as $det) {
                $cantidad  = (float)$det['cantidad'];
                $precio    = (float) ($preciosProductos[$det['producto_id']] ?? 0);
                $descPct   = (float)($det['descuento_pct'] ?? 0);
                $descuento = round($precio * $cantidad * ($descPct / 100), 2);
                $neto      = round(($precio * $cantidad) - $descuento, 2);
                $grabaIva  = (bool)($det['graba_iva'] ?? true);
                $iva       = $grabaIva ? round($neto * 0.15, 2) : 0;

                ProformaDetalle::create([
                    'proforma_id'    => $proforma->id,
                    'producto_id'    => $det['producto_id'],
                    'descripcion'    => $det['descripcion'] ?? null,
                    'cantidad'       => $cantidad,
                    'precio_unitario'=> $precio,
                    'descuento_pct'  => $descPct,
                    'subtotal'       => $neto,
                    'total'          => $neto + $iva,
                ]);
            }

            if (!empty($aprobacionesUsadas)) {
                DB::table('aprobaciones_especiales')
                    ->whereIn('id', array_keys($aprobacionesUsadas))
                    ->update([
                        'tabla_referencia' => 'proformas',
                        'registro_id'      => $proforma->id,
                        'updated_at'       => now(),
                    ]);
            }

            return $proforma;
        });

        $this->auditoria->documento('crear', 'ventas', 'proformas', $proforma->id, "Proforma {$proforma->numero} creada");

        return redirect()->route('ventas.proformas.show', $proforma->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Proforma {$proforma->numero} creada correctamente."]);
    }

    public function show(Proforma $proforma)
    {
        $proforma->load(['cliente', 'usuario', 'detalles.producto', 'empresa', 'factura']);

        return Inertia::render('Ventas/Proformas/Show', [
            'proforma' => $proforma,
        ]);
    }

    /** PDF de la proforma: en línea para el visor; con ?download=1 se descarga. */
    public function pdf(Request $request, Proforma $proforma, DocumentoVentaPdf $pdfs)
    {
        $pdf    = $pdfs->proforma($proforma);
        $nombre = 'Proforma-' . $proforma->numero . '.pdf';

        return $request->boolean('download') ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    /** Anula una proforma pendiente. Requiere el PIN de aprobación de un supervisor. */
    public function anular(Request $request, Proforma $proforma, AprobacionService $aprobaciones)
    {
        $request->validate(['aprobacion_especial_id' => 'required|integer']);

        if ($proforma->estado !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden anular proformas en estado pendiente.']);
        }

        $aprobacion = $aprobaciones->disponible((int) $request->aprobacion_especial_id, 'anulacion_factura');
        if (!$aprobacion) {
            return back()->withErrors(['error' => 'La aprobación especial no es válida o ya fue utilizada.']);
        }

        DB::transaction(function () use ($proforma, $aprobaciones, $aprobacion) {
            $proforma->update(['estado' => 'anulada']);
            $aprobaciones->consumir($aprobacion->id, 'proformas', $proforma->id);
        });

        $this->auditoria->documento('anular', 'ventas', 'proformas', $proforma->id, "Proforma {$proforma->numero} anulada");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Proforma {$proforma->numero} anulada."]);
    }

    /** Elimina la proforma con sus detalles. Solo SuperAdmin; exige escribir el número. */
    public function destroy(Request $request, Proforma $proforma, AprobacionService $aprobaciones)
    {
        $data = $request->validate(['confirmacion' => 'required|string']);

        if (!$aprobaciones->esSuperAdmin()) {
            return back()->withErrors(['error' => 'Solo el SuperAdmin puede eliminar proformas.']);
        }
        if (trim($data['confirmacion']) !== $proforma->numero) {
            return back()->withErrors(['error' => 'El número escrito no coincide con la proforma.']);
        }
        if ($proforma->estado === 'facturada' || $proforma->factura_id) {
            return back()->withErrors(['error' => 'La proforma ya fue convertida en factura y no se puede eliminar.']);
        }

        $resumen = "Proforma {$proforma->numero} eliminada (total {$proforma->total}, estado {$proforma->estado})";

        DB::transaction(function () use ($proforma) {
            ProformaDetalle::where('proforma_id', $proforma->id)->delete();
            $proforma->delete();
        });

        $this->auditoria->documento('eliminar', 'ventas', 'proformas', $proforma->id, $resumen);

        return redirect()->route('ventas.proformas.index')
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Proforma {$proforma->numero} eliminada."]);
    }

    public function convertirAFactura(Request $request, Proforma $proforma)
    {
        if ($proforma->estado !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden convertir proformas en estado pendiente.']);
        }

        $empresaId = session('empresa_activa_id');

        $request->validate([
            'formas_pago'         => 'required|array|min:1',
            'formas_pago.*.forma' => 'required|string',
            'formas_pago.*.monto' => 'required|numeric|min:0.01',
        ]);

        // Con tarjeta de crédito no hay descuento de ningún tipo.
        if ((float) $proforma->descuento_total > 0
            && ReglasPago::algunaTarjeta(collect($request->formas_pago)->pluck('forma'))) {
            return back()->withErrors(['error' => ReglasPago::MENSAJE_SIN_DESCUENTO . ' Esta proforma tiene descuento.']);
        }

        try {
            $factura = DB::transaction(function () use ($request, $proforma, $empresaId) {
                $numero  = (new SecuencialService())->siguiente($empresaId, 'FAC');
                [$est, $pe, $sec] = explode('-', $numero);

                $cliente = Cliente::findOrFail($proforma->cliente_id);

                $factura = Factura::create([
                    'empresa_id'          => $empresaId,
                    'centro_costo_id'     => $proforma->centro_costo_id,
                    'cliente_id'          => $proforma->cliente_id,
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
                    'subtotal_0'          => 0,
                    'subtotal_15'         => $proforma->subtotal,
                    'descuento_total'     => $proforma->descuento_total,
                    'total_iva'           => $proforma->total_iva,
                    'total'               => $proforma->total,
                    'observaciones'       => $proforma->observaciones,
                    'tipo'                => 1,
                    'estado'              => 'activa',
                    'tiene_descuento_especial' => false,
                    'email_enviado'       => false,
                ]);

                $productos = Producto::whereIn('id', $proforma->detalles->pluck('producto_id'))
                    ->get(['id', 'codigo', 'tipo'])
                    ->keyBy('id');

                foreach ($proforma->detalles as $det) {
                    $descuentoValor = $det->precio_unitario * $det->cantidad * ($det->descuento_pct / 100);
                    $valorIva = $det->subtotal * ($det->porcentaje_iva / 100);

                    FacturaDetalle::create([
                        'factura_id'      => $factura->id,
                        'producto_id'     => $det->producto_id,
                        'descripcion'     => $det->descripcion,
                        'cantidad'        => $det->cantidad,
                        'precio_unitario' => $det->precio_unitario,
                        'descuento_pct'   => $det->descuento_pct,
                        'descuento_valor' => $descuentoValor,
                        'subtotal'        => $det->subtotal,
                        'porcentaje_iva'  => $det->porcentaje_iva,
                        'valor_iva'       => $valorIva,
                        'total'           => $det->total,
                    ]);

                    $producto = $productos->get($det->producto_id);

                    if ($producto && $producto->tipo !== 'servicio') {
                        $bodegaId = $this->bodegaPrincipalId();

                        $disponible = $this->inventario->getSaldoDisponible((int) $det->producto_id, $bodegaId);

                        if ((float) $det->cantidad > $disponible) {
                            throw new \RuntimeException(
                                "Stock insuficiente para {$producto->codigo}: disponible {$disponible}, solicitado {$det->cantidad}."
                            );
                        }

                        $this->inventario->egresarStock(
                            (int) $det->producto_id,
                            $bodegaId,
                            (float) $det->cantidad,
                            'factura',
                            $factura->id
                        );
                    }
                }

                foreach ($request->formas_pago as $pago) {
                    FacturaPago::create([
                        'factura_id' => $factura->id,
                        'forma_pago' => $pago['forma'],
                        'valor'      => $pago['monto'],
                    ]);
                }

                $proforma->update(['estado' => 'facturada', 'factura_id' => $factura->id]);

                return $factura;
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['stock' => $e->getMessage()])->withInput();
        }

        $this->auditoria->documento('convertir', 'ventas', 'proformas', $proforma->id, "Proforma {$proforma->numero} convertida a factura {$factura->numero_completo}");

        return redirect()->route('ventas.facturas.show', $factura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Proforma convertida a factura {$factura->numero_completo}."]);
    }
}
