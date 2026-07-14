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
use App\Services\AsientoService;
use App\Services\AuditoriaService;
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
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $query = Prefactura::with(['cliente', 'usuario'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', fn($q) => $q
                ->where('razon_social', 'ilike', "%{$request->cliente}%")
                ->orWhere('identificacion', 'ilike', "%{$request->cliente}%"));
        }

        $prefacturas = $query->paginate(25)->withQueryString();

        return Inertia::render('Ventas/Prefacturas/Index', [
            'prefacturas' => $prefacturas,
            'filtros'     => $request->only(['estado', 'cliente']),
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

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->select('id', 'codigo', 'nombre', 'pvp', 'pvd', 'costo', 'descuento_maximo as descuento_max', 'porcentaje_iva')
            ->orderBy('nombre')
            ->get();

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

        $total = 0;
        foreach ($request->detalles as $det) {
            $cantidad  = (float)$det['cantidad'];
            $precio    = (float)$det['precio'];
            $descPct   = (float)($det['descuento_pct'] ?? 0);
            $descuento = $precio * $cantidad * ($descPct / 100);
            $neto      = ($precio * $cantidad) - $descuento;
            $grabaIva  = (bool)($det['graba_iva'] ?? true);
            $total    += $neto + ($grabaIva ? $neto * 0.15 : 0);
        }

        try {
            $bodegaPrincipalId = $this->bodegaPrincipalId();
            $bodegaReservasId  = $this->bodegaReservasId();
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        try {
            $prefactura = DB::transaction(function () use ($request, $empresaId, $total, $bodegaPrincipalId, $bodegaReservasId) {
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
                    $precio    = (float)$det['precio'];
                    $descPct   = (float)($det['descuento_pct'] ?? 0);
                    $descuento = $precio * $cantidad * ($descPct / 100);
                    $neto      = ($precio * $cantidad) - $descuento;
                    $grabaIva  = (bool)($det['graba_iva'] ?? true);
                    $iva       = $grabaIva ? $neto * 0.15 : 0;

                    $detalle = PrefacturaDetalle::create([
                        'prefactura_id'  => $prefactura->id,
                        'producto_id'    => $det['producto_id'],
                        'descripcion'    => $det['descripcion'] ?? null,
                        'cantidad'       => $cantidad,
                        'precio_unitario'=> $precio,
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
        } catch (\Throwable) {
            // Asiento falla de forma silenciosa
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
