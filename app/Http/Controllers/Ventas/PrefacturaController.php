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
use App\Models\Prefactura;
use App\Models\PrefacturaAbono;
use App\Models\PrefacturaDetalle;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\AprobacionService;
use App\Services\AsientoService;
use App\Services\AuditoriaService;
use App\Services\DescuentoService;
use App\Services\DocumentoVentaPdf;
use App\Support\ReglasPago;
use App\Services\InventarioService;
use App\Services\SecuencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PrefacturaController extends Controller
{
    use ResuelveBodegasFijas;

    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
        private InventarioService $inventario,
        private AsientoService    $asiento,
        private DescuentoService  $descuento,
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

        $query = Prefactura::with(['cliente', 'usuario', 'detalles'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
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

        $prefacturas = $query->paginate(25)->withQueryString();

        // "Nuevo": es la primera prefactura que se le hizo a ese cliente.
        $primeras = Prefactura::where('empresa_id', $empresaId)
            ->whereIn('cliente_id', $prefacturas->pluck('cliente_id')->unique())
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, min(id) as primera')
            ->pluck('primera', 'cliente_id');

        $prefacturas->through(function (Prefactura $p) use ($primeras) {
            $bruto = 0.0;
            $desc  = 0.0;
            foreach ($p->detalles as $d) {
                $base   = (float) $d->cantidad * (float) $d->precio_unitario;
                $bruto += $base;
                $desc  += round($base * ((float) $d->descuento_pct / 100), 2);
            }
            $p->setAttribute('cliente_nuevo', (int) ($primeras[$p->cliente_id] ?? 0) === $p->id);
            $p->setAttribute('desc_pct', $bruto > 0 ? round($desc / $bruto * 100, 2) : 0);
            $p->setAttribute('vendedor', $p->usuario?->nombre);
            $p->unsetRelation('detalles');

            return $p;
        });

        return Inertia::render('Ventas/Prefacturas/Index', [
            'prefacturas' => $prefacturas,
            'filtros'     => $request->only(['estado', 'cliente', 'fecha_desde', 'fecha_hasta']),
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

        // Sin 'costo': no se usa en Prefactura (no hay chequeo de precio bajo
        // costo aquí) y no debe llegar al navegador del vendedor (CHECKLIST_ERRORES_COMPLICACIONES.md, A2).
        $productos = Producto::where('estado', true)
            ->select('id', 'codigo', 'nombre', 'pvp', 'pvd', 'porcentaje_iva')
            ->orderBy('nombre')
            ->get();

        // Mismo tope que Factura: promo vigente > lista de precios > producto.
        $descuentosMaximos = $this->descuento->mapaMaximosPermitidos($productos->pluck('id')->all(), $empresaId);
        $productos->each(function ($p) use ($descuentosMaximos) {
            $p->descuento_max = $descuentosMaximos[$p->id] ?? 0.0;
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
            ->where('tipo_documento', 'PRE')
            ->first();

        $siguienteNumero = $sec
            ? sprintf('%s-%s-%09d', $est, $pe, (int)($sec->secuencial ?? $sec->siguiente ?? 1))
            : sprintf('%s-%s-%09d', $est, $pe, 1);

        $limite = LimiteDescuento::whereHas('perfil', fn($q) => $q->where('nombre', $perfilNombre))
            ->first();

        return Inertia::render('Ventas/Prefacturas/Form', [
            'clientes'         => $clientes,
            'productos'        => $productos,
            'vendedores'       => $vendedores,
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
            'cliente_id'             => 'required|integer|exists:clientes,id',
            'detalles'               => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.cantidad'    => 'required|numeric|min:0.01',
            'detalles.*.precio'      => 'required|numeric|min:0.01',
        ]);

        $usuario = Auth::user();

        $perfilNombre = DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', $usuario->id)
            ->value('perfiles.nombre');

        $limiteDescuento = LimiteDescuento::whereHas('perfil', fn($q) => $q->where('nombre', $perfilNombre))->first();
        $limiteMax = (float) ($limiteDescuento?->porcentaje_maximo ?? 0);

        $productoIds       = collect($request->detalles)->pluck('producto_id')->unique()->all();
        $maximosPermitidos = $this->descuento->mapaMaximosPermitidos($productoIds, $empresaId);

        // Precio real desde la tabla productos (precio de lista, pvp). El
        // vendedor no puede cambiar el precio, solo dar descuento: el precio
        // que llega en el payload NUNCA se usa (ver
        // CHECKLIST_ERRORES_COMPLICACIONES.md, ítem A1).
        $preciosProductos = Producto::whereIn('id', $productoIds)->pluck('pvp', 'id');

        // Misma regla que FacturaController::store(): si el descuento supera el
        // límite del perfil o el tope del producto (promo vigente si aplica),
        // se exige una aprobación especial válida que cubra ese porcentaje.
        $aprobacionValida = null;
        $tieneDescuentoEspecial = false;
        // Redondeo a centavos por línea; IVA sobre la base imponible total (igual que la factura).
        $total = 0;
        $baseSinIva = 0;
        $baseIva    = 0;
        foreach ($request->detalles as $det) {
            $cantidad  = (float)$det['cantidad'];
            $precio    = (float) ($preciosProductos[$det['producto_id']] ?? 0);
            $descPct   = (float)($det['descuento_pct'] ?? 0);

            if ($descPct < 0 || $descPct > 100) {
                return back()->withErrors(['error' => 'El descuento debe estar entre 0 y 100%.'])->withInput();
            }

            $maximoProducto = $maximosPermitidos[$det['producto_id']] ?? 0.0;
            if ($descPct > $limiteMax || $descPct > $maximoProducto) {
                if (!$request->filled('aprobacion_especial_id')) {
                    return back()->withErrors(['error' => 'Se requiere aprobación especial para el descuento aplicado.'])->withInput();
                }

                if ($aprobacionValida === null) {
                    $aprobacionValida = DB::table('aprobaciones_especiales')
                        ->join('tipos_aprobacion', 'tipos_aprobacion.id', '=', 'aprobaciones_especiales.tipo_aprobacion_id')
                        ->where('aprobaciones_especiales.id', $request->input('aprobacion_especial_id'))
                        ->where('aprobaciones_especiales.solicitado_por', $usuario->id)
                        ->where('tipos_aprobacion.clave', 'descuento_excedido')
                        ->whereNull('aprobaciones_especiales.registro_id')
                        ->select('aprobaciones_especiales.id', 'aprobaciones_especiales.valor_aprobado')
                        ->first();

                    if (!$aprobacionValida) {
                        return back()->withErrors(['error' => 'La aprobación especial no es válida o ya fue utilizada.'])->withInput();
                    }
                }

                if ($descPct > (float) $aprobacionValida->valor_aprobado) {
                    return back()->withErrors([
                        'error' => "La aprobación otorgada cubre hasta {$aprobacionValida->valor_aprobado}%, pero se solicita {$descPct}%.",
                    ])->withInput();
                }

                $tieneDescuentoEspecial = true;
            }

            $descuento = round($precio * $cantidad * ($descPct / 100), 2);
            $neto      = round(($precio * $cantidad) - $descuento, 2);
            $grabaIva  = (bool)($det['graba_iva'] ?? true);
            if ($grabaIva) {
                $baseIva += $neto;
            } else {
                $baseSinIva += $neto;
            }
        }
        $total = round($baseSinIva + $baseIva + round($baseIva * 0.15, 2), 2);

        try {
            $bodegaPrincipalId = $this->bodegaPrincipalId();
            $bodegaReservasId  = $this->bodegaReservasId();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        try {
            $prefactura = DB::transaction(function () use ($request, $empresaId, $total, $tieneDescuentoEspecial, $aprobacionValida, $bodegaPrincipalId, $bodegaReservasId, $preciosProductos) {
                $numero = $this->secuencial->siguiente($empresaId, 'PRE');

                $prefactura = Prefactura::create([
                    'empresa_id'      => $empresaId,
                    'centro_costo_id' => $request->centro_costo_id,
                    'cliente_id'      => $request->cliente_id,
                    'usuario_id'      => Auth::id(),
                    'numero'          => $numero,
                    'fecha_emision'   => now()->toDateString(),
                    'total'           => $total,
                    'total_abonado'   => 0,
                    'saldo_pendiente' => $total,
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

                    $detalle = PrefacturaDetalle::create([
                        'prefactura_id'  => $prefactura->id,
                        'producto_id'    => $det['producto_id'],
                        'descripcion'    => $det['descripcion'] ?? null,
                        'cantidad'       => $cantidad,
                        'precio_unitario'=> $precio,
                        'descuento_pct'  => $descPct,
                        'total'          => $neto + $iva,
                    ]);

                    // Traslado atómico Bodega Principal UIO -> Bodega Reservas.
                    // egresarStock() no valida stock por sí solo (solo hace
                    // floor en 0), así que el pre-check con getSaldoDisponible()
                    // es lo que realmente evita reservar más de lo que hay.
                    $disponible = $this->inventario->getSaldoDisponible((int) $det['producto_id'], $bodegaPrincipalId);
                    if ($cantidad > $disponible) {
                        $ref = $det['codigo'] ?? $det['descripcion'] ?? "producto #{$det['producto_id']}";
                        throw new \RuntimeException(
                            "Stock insuficiente para {$ref} en Bodega Principal UIO: disponible {$disponible}, solicitado {$cantidad}."
                        );
                    }

                    $costoUnitario = (float) (DB::table('inventario_saldos')
                        ->where('producto_id', $det['producto_id'])
                        ->where('bodega_id', $bodegaPrincipalId)
                        ->value('costo_promedio') ?? 0);

                    $this->inventario->egresarStock((int) $det['producto_id'], $bodegaPrincipalId, $cantidad, 'prefactura_detalle', $detalle->id);
                    $this->inventario->ingresarStock((int) $det['producto_id'], $bodegaReservasId, $cantidad, $costoUnitario, 'prefactura_detalle', $detalle->id);
                    $this->inventario->reservarStock((int) $det['producto_id'], $bodegaReservasId, $cantidad, 'prefactura_detalle', $detalle->id);
                }

                if ($tieneDescuentoEspecial && $aprobacionValida) {
                    // Aprobación consumida: no se puede reutilizar en otro documento.
                    DB::table('aprobaciones_especiales')
                        ->where('id', $aprobacionValida->id)
                        ->update([
                            'tabla_referencia' => 'prefacturas',
                            'registro_id'      => $prefactura->id,
                            'updated_at'       => now(),
                        ]);
                }

                return $prefactura;
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        $this->auditoria->documento('crear', 'ventas', 'prefacturas', $prefactura->id, "Prefactura {$prefactura->numero} creada");

        return redirect()->route('ventas.prefacturas.show', $prefactura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Prefactura {$prefactura->numero} creada correctamente."]);
    }

    public function show(Prefactura $prefactura)
    {
        $prefactura->load(['cliente', 'usuario', 'detalles.producto', 'empresa', 'abonos', 'factura']);

        return Inertia::render('Ventas/Prefacturas/Show', [
            'prefactura' => $prefactura,
        ]);
    }

    public function abonar(Request $request, Prefactura $prefactura)
    {
        $request->validate([
            'valor'      => 'required|numeric|min:0.01',
            'forma_pago' => 'required|string',
        ]);

        // Con tarjeta de crédito no hay descuento de ningún tipo.
        if (ReglasPago::esTarjeta($request->forma_pago) && $this->tieneDescuento($prefactura)) {
            return back()->withErrors(['error' => ReglasPago::MENSAJE_SIN_DESCUENTO . ' Esta prefactura tiene descuento.']);
        }

        $valor = (float)$request->valor;

        if ($valor > $prefactura->saldo_pendiente) {
            return response()->json(['error' => 'El abono no puede superar el saldo pendiente.'], 422);
        }

        $abono = DB::transaction(function () use ($request, $prefactura, $valor) {
            $abono = PrefacturaAbono::create([
                'prefactura_id' => $prefactura->id,
                'valor'         => $valor,
                'forma_pago'    => $request->forma_pago,
                'fecha'         => now()->toDateString(),
                'usuario_id'    => Auth::id(),
            ]);

            $nuevoAbonado  = $prefactura->total_abonado + $valor;
            $nuevoSaldo    = $prefactura->saldo_pendiente - $valor;
            $nuevoEstado   = $nuevoSaldo <= 0 ? 'liquidada' : $prefactura->estado;

            $prefactura->update([
                'total_abonado'   => $nuevoAbonado,
                'saldo_pendiente' => max(0, $nuevoSaldo),
                'estado'          => $nuevoEstado,
            ]);

            return $abono;
        });

        try {
            $asientoAbono = $this->asiento->anticipoCliente(
                empresaId:   $prefactura->empresa_id,
                documentoId: $prefactura->id,
                referencia:  $prefactura->numero,
                monto:       $valor,
                formaPago:   $request->forma_pago,
            );
            $abono->update(['asiento_id' => $asientoAbono->id]);
        } catch (\Throwable $e) {
            \Log::warning("Contabilidad: abono prefactura {$prefactura->numero} sin asiento: {$e->getMessage()}");
            $this->asiento->notificarAsientoFallido(
                empresaId:  (int) $prefactura->empresa_id,
                tabla:      'prefacturas',
                registroId: $prefactura->id,
                referencia: "Abono prefactura {$prefactura->numero}",
                mensaje:    $e->getMessage(),
            );
        }

        $this->auditoria->documento('abonar', 'ventas', 'prefacturas', $prefactura->id, "Abono {$valor} a prefactura {$prefactura->numero}");

        $prefactura->refresh();

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => 'Abono registrado correctamente.']);
    }

    public function convertirAFactura(Request $request, Prefactura $prefactura)
    {
        if ($prefactura->saldo_pendiente > 0) {
            return back()->withErrors(['error' => 'La prefactura tiene saldo pendiente. Liquide completamente antes de convertir.']);
        }

        $empresaId = session('empresa_activa_id');

        $request->validate([
            'formas_pago'         => 'nullable|array',
            'formas_pago.*.forma' => 'required|string',
            'formas_pago.*.monto' => 'required|numeric|min:0.01',
        ]);

        if ($request->filled('formas_pago')
            && ReglasPago::algunaTarjeta(collect($request->formas_pago)->pluck('forma'))
            && $this->tieneDescuento($prefactura)) {
            return back()->withErrors(['error' => ReglasPago::MENSAJE_SIN_DESCUENTO . ' Esta prefactura tiene descuento.']);
        }

        try {
            $bodegaReservasId = $this->bodegaReservasId();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        try {
            $factura = DB::transaction(function () use ($request, $prefactura, $empresaId, $bodegaReservasId) {
                $numero = $this->secuencial->siguiente($empresaId, 'FAC');
                [$est, $pe, $sec] = explode('-', $numero);

                $cliente = Cliente::findOrFail($prefactura->cliente_id);

                $subtotal0  = 0;
                $subtotal15 = 0;
                $totalIva   = 0;

                foreach ($prefactura->detalles as $det) {
                    $precioUnitario = (float) $det->precio_unitario;
                    $cantidad       = (float) $det->cantidad;
                    $descuentoPct   = (float) ($det->descuento_pct ?? 0);
                    $descuentoValor = $precioUnitario * $cantidad * ($descuentoPct / 100);
                    $subtotal       = ($precioUnitario * $cantidad) - $descuentoValor;
                    $porcentajeIva  = (float) ($det->porcentaje_iva ?? 15);
                    $valorIva       = $subtotal * ($porcentajeIva / 100);

                    if ($porcentajeIva > 0) {
                        $subtotal15 += $subtotal;
                    } else {
                        $subtotal0 += $subtotal;
                    }
                    $totalIva += $valorIva;
                }

                $factura = Factura::create([
                    'empresa_id'          => $empresaId,
                    'centro_costo_id'     => $prefactura->centro_costo_id,
                    'cliente_id'          => $prefactura->cliente_id,
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
                    'total'               => $prefactura->total,
                    'observaciones'       => $prefactura->observaciones,
                    'tipo'                => 2,
                    'estado'              => 'activa',
                    'email_enviado'       => false,
                ]);

                foreach ($prefactura->detalles as $det) {
                    $precioUnitario  = (float) $det->precio_unitario;
                    $cantidad        = (float) $det->cantidad;
                    $descuentoPct    = (float) ($det->descuento_pct ?? 0);
                    $descuentoValor  = $precioUnitario * $cantidad * ($descuentoPct / 100);
                    $subtotal        = ($precioUnitario * $cantidad) - $descuentoValor;
                    $porcentajeIva   = (float) ($det->porcentaje_iva ?? 15);
                    $valorIva        = $subtotal * ($porcentajeIva / 100);

                    FacturaDetalle::create([
                        'factura_id'      => $factura->id,
                        'producto_id'     => $det->producto_id,
                        'descripcion'     => $det->descripcion,
                        'cantidad'        => $cantidad,
                        'precio_unitario' => $precioUnitario,
                        'descuento_pct'   => $descuentoPct,
                        'descuento_valor' => $descuentoValor,
                        'subtotal'        => $subtotal,
                        'porcentaje_iva'  => $porcentajeIva,
                        'valor_iva'       => $valorIva,
                        'total'           => $subtotal + $valorIva,
                    ]);
                    $this->inventario->confirmarSalida($det->producto_id, $bodegaReservasId, 'prefactura_detalle', $det->id);
                }

                if ($request->filled('formas_pago')) {
                    foreach ($request->formas_pago as $pago) {
                        FacturaPago::create([
                            'factura_id' => $factura->id,
                            'forma_pago' => $pago['forma'],
                            'valor'      => $pago['monto'],
                        ]);
                    }
                }

                $prefactura->update([
                    'factura_id' => $factura->id,
                    'estado'     => 'liquidada',
                ]);

                return $factura;
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        $this->auditoria->documento('convertir', 'ventas', 'prefacturas', $prefactura->id, "Prefactura {$prefactura->numero} convertida a factura {$factura->numero_completo}");

        return redirect()->route('ventas.facturas.show', $factura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Prefactura convertida a factura {$factura->numero_completo}."]);
    }

    private function tieneDescuento(Prefactura $prefactura): bool
    {
        return $prefactura->detalles()->where('descuento_pct', '>', 0)->exists();
    }

    /** PDF de la prefactura: en línea para el visor; con ?download=1 se descarga. */
    public function pdf(Request $request, Prefactura $prefactura, DocumentoVentaPdf $pdfs)
    {
        $pdf    = $pdfs->prefactura($prefactura);
        $nombre = 'Prefactura-' . $prefactura->numero . '.pdf';

        return $request->boolean('download') ? $pdf->download($nombre) : $pdf->stream($nombre);
    }

    /**
     * Devuelve a la Bodega Principal lo que la prefactura había apartado en la
     * Bodega Reservas (proceso inverso al de store()).
     */
    private function devolverStockReservado(Prefactura $prefactura): void
    {
        $bodegaPrincipalId = $this->bodegaPrincipalId();
        $bodegaReservasId  = $this->bodegaReservasId();

        foreach ($prefactura->detalles as $det) {
            $cantidad = (float) $det->cantidad;

            // Costo con el que ingresó a la bodega de reservas.
            $costo = (float) (DB::table('inventario_movimientos')
                ->where('doc_tipo', 'PREFACTURA_DETALLE')
                ->where('doc_id', $det->id)
                ->where('bodega_id', $bodegaReservasId)
                ->where('tipo', 'entrada')
                ->value('costo_unitario') ?? 0);

            $this->inventario->liberarReserva((int) $det->producto_id, $bodegaReservasId, $cantidad);
            $this->inventario->egresarStock((int) $det->producto_id, $bodegaReservasId, $cantidad, 'prefactura_anulada', $prefactura->id);
            $this->inventario->ingresarStock((int) $det->producto_id, $bodegaPrincipalId, $cantidad, $costo, 'prefactura_anulada', $prefactura->id);
        }
    }

    /** Motivo por el que no se puede anular/eliminar, o null si se puede. */
    private function motivoNoModificable(Prefactura $prefactura): ?string
    {
        if ($prefactura->factura_id || $prefactura->estado === 'liquidada') {
            return 'La prefactura ya fue liquidada o convertida en factura.';
        }
        if ($prefactura->abonos()->exists()) {
            return 'La prefactura tiene abonos registrados; devuélvalos antes de anularla o eliminarla.';
        }
        return null;
    }

    /** Anula la prefactura y libera el stock apartado. Requiere PIN de aprobación. */
    public function anular(Request $request, Prefactura $prefactura, AprobacionService $aprobaciones)
    {
        $request->validate(['aprobacion_especial_id' => 'required|integer']);

        if ($prefactura->estado === 'anulada') {
            return back()->withErrors(['error' => 'La prefactura ya está anulada.']);
        }
        if ($motivo = $this->motivoNoModificable($prefactura)) {
            return back()->withErrors(['error' => $motivo]);
        }

        $aprobacion = $aprobaciones->disponible((int) $request->aprobacion_especial_id, 'anulacion_factura');
        if (!$aprobacion) {
            return back()->withErrors(['error' => 'La aprobación especial no es válida o ya fue utilizada.']);
        }

        try {
            DB::transaction(function () use ($prefactura, $aprobaciones, $aprobacion) {
                $prefactura->load('detalles');
                $this->devolverStockReservado($prefactura);
                $prefactura->update(['estado' => 'anulada']);
                $aprobaciones->consumir($aprobacion->id, 'prefacturas', $prefactura->id);
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'No se pudo anular: ' . $e->getMessage()]);
        }

        $this->auditoria->documento('anular', 'ventas', 'prefacturas', $prefactura->id, "Prefactura {$prefactura->numero} anulada");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Prefactura {$prefactura->numero} anulada."]);
    }

    /** Elimina la prefactura con sus detalles (solo SuperAdmin; exige escribir el número). */
    public function destroy(Request $request, Prefactura $prefactura, AprobacionService $aprobaciones)
    {
        $data = $request->validate(['confirmacion' => 'required|string']);

        if (!$aprobaciones->esSuperAdmin()) {
            return back()->withErrors(['error' => 'Solo el SuperAdmin puede eliminar prefacturas.']);
        }
        if (trim($data['confirmacion']) !== $prefactura->numero) {
            return back()->withErrors(['error' => 'El número escrito no coincide con la prefactura.']);
        }
        if ($motivo = $this->motivoNoModificable($prefactura)) {
            return back()->withErrors(['error' => $motivo]);
        }

        $resumen = "Prefactura {$prefactura->numero} eliminada (total {$prefactura->total}, estado {$prefactura->estado})";

        try {
            DB::transaction(function () use ($prefactura) {
                $prefactura->load('detalles');
                // Si ya estaba anulada, el stock apartado ya se devolvió.
                if ($prefactura->estado !== 'anulada') {
                    $this->devolverStockReservado($prefactura);
                }
                PrefacturaDetalle::where('prefactura_id', $prefactura->id)->delete();
                $prefactura->delete();
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => 'No se pudo eliminar: ' . $e->getMessage()]);
        }

        $this->auditoria->documento('eliminar', 'ventas', 'prefacturas', $prefactura->id, $resumen);

        return redirect()->route('ventas.prefacturas.index')
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Prefactura {$prefactura->numero} eliminada."]);
    }

    public function saldoDisponible(Request $request): JsonResponse
    {
        $request->validate([
            'producto_id' => 'required|integer',
        ]);

        try {
            $bodegaId = $this->bodegaPrincipalId();
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
