<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Cliente;
use App\Models\CuentaCobrar;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaPago;
use App\Models\LimiteDescuento;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\AsientoService;
use App\Services\AuditoriaService;
use App\Services\SecuencialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class FacturaController extends Controller
{
    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
        private AsientoService    $asiento,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $query = Factura::with(['cliente', 'usuario', 'pagos'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', function ($q) use ($request) {
                $q->where('razon_social', 'ilike', "%{$request->cliente}%")
                  ->orWhere('identificacion', 'ilike', "%{$request->cliente}%");
            });
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('estado_sri')) {
            $query->where('estado_sri', $request->estado_sri);
        }
        if ($request->filled('forma_pago')) {
            $query->whereHas('pagos', fn($q) => $q->where('forma_pago', $request->forma_pago));
        }
        if ($request->filled('centro_costo_id')) {
            $query->where('centro_costo_id', $request->centro_costo_id);
        }

        $facturas = $query->paginate(25)->withQueryString();

        return Inertia::render('Ventas/Facturas/Index', [
            'facturas' => $facturas,
            'filtros'  => $request->only(['fecha_desde', 'fecha_hasta', 'cliente', 'estado', 'estado_sri', 'forma_pago', 'centro_costo_id']),
        ]);
    }

    public function create()
    {
        $empresaId = session('empresa_activa_id');
        $usuario   = Auth::user();

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->select('id', 'identificacion', 'razon_social', 'tiene_credito', 'dias_credito',
                     'cupo_maximo', 'tipo_identificacion', 'email', 'telefono', 'direccion')
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

        // Peek del secuencial sin avanzarlo — leemos el valor actual
        $sec = DB::table('secuenciales')
            ->where('empresa_id', $empresaId)
            ->where('tipo_documento', 'FAC')
            ->first();

        $empresa = Empresa::findOrFail($empresaId);
        $est = $empresa->cod_establecimiento ?? '001';
        $pe  = $empresa->cod_punto_emision   ?? '001';

        $siguienteNumero = $sec
            ? sprintf('%s-%s-%09d', $est, $pe, (int)($sec->secuencial ?? $sec->siguiente ?? 1))
            : sprintf('%s-%s-%09d', $est, $pe, 1);

        $limite = LimiteDescuento::whereHas('perfil', fn($q) => $q->where('nombre', $perfilNombre))
            ->first();

        return Inertia::render('Ventas/Facturas/Form', [
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
        $usuario   = Auth::user();

        $request->validate([
            'cliente_id'              => 'required|integer|exists:clientes,id',
            'detalles'                => 'required|array|min:1',
            'detalles.*.producto_id'  => 'required|integer',
            'detalles.*.cantidad'     => 'required|numeric|min:0.01',
            'detalles.*.precio'       => 'required|numeric|min:0.01',
            'detalles.*.descuento_pct'=> 'nullable|numeric|min:0',
            'formas_pago'             => 'required|array|min:1',
            'formas_pago.*.forma'     => 'required|string',
            'formas_pago.*.monto'     => 'required|numeric|min:0.01',
        ]);

        // Calcular totales
        $subtotal0   = 0;
        $subtotal15  = 0;
        $descTotal   = 0;
        $tieneDescuentoEspecial = false;

        $perfilNombre = DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', $usuario->id)
            ->value('perfiles.nombre');

        $limiteDescuento = LimiteDescuento::whereHas('perfil', fn($q) => $q->where('nombre', $perfilNombre))
            ->first();

        $limiteMax = $limiteDescuento?->porcentaje_maximo ?? 0;

        foreach ($request->detalles as $detalle) {
            $descPct = (float)($detalle['descuento_pct'] ?? 0);

            if ($descPct > $limiteMax) {
                if (!$request->filled('aprobacion_especial')) {
                    return back()->withErrors(['aprobacion_especial' => 'Se requiere aprobación especial para el descuento aplicado.']);
                }
                $tieneDescuentoEspecial = true;
            }

            $precio     = (float)$detalle['precio'];
            $cantidad   = (float)$detalle['cantidad'];
            $descuento  = $precio * $cantidad * ($descPct / 100);
            $subtotalItem = ($precio * $cantidad) - $descuento;

            $descTotal += $descuento;

            // Determinar si el producto grava IVA — simplificación: usar pvp > 0 como proxy
            // En producción esto viene del campo graba_iva del producto
            $grabaIva = (bool)($detalle['graba_iva'] ?? true);
            if ($grabaIva) {
                $subtotal15 += $subtotalItem;
            } else {
                $subtotal0 += $subtotalItem;
            }
        }

        $totalIva = $subtotal15 * 0.15;
        $total    = $subtotal0 + $subtotal15 + $totalIva;

        // Validar que suma de formas_pago == total
        $sumaFormasPago = collect($request->formas_pago)->sum('monto');
        if (abs($sumaFormasPago - $total) > 0.01) {
            return back()->withErrors(['formas_pago' => "La suma de formas de pago ({$sumaFormasPago}) no coincide con el total ({$total})."]);
        }

        $factura = DB::transaction(function () use (
            $request, $empresaId, $usuario, $subtotal0, $subtotal15,
            $descTotal, $totalIva, $total, $tieneDescuentoEspecial
        ) {
            $empresa = Empresa::findOrFail($empresaId);
            $numero  = $this->secuencial->siguiente($empresaId, 'FAC');

            [$est, $pe, $sec] = explode('-', $numero);

            $cliente = Cliente::findOrFail($request->cliente_id);

            $factura = Factura::create([
                'empresa_id'              => $empresaId,
                'centro_costo_id'         => $request->centro_costo_id,
                'cliente_id'              => $request->cliente_id,
                'usuario_id'              => $usuario->id,
                'establecimiento'         => $est,
                'punto_emision'           => $pe,
                'secuencial'              => ltrim($sec, '0') ?: '1',
                'numero_completo'         => $numero,
                'fecha_emision'           => now()->toDateString(),
                'hora_emision'            => now()->toTimeString(),
                'estado_sri'              => 'pendiente',
                'tipo_identificacion'     => $cliente->tipo_identificacion,
                'identificacion'          => $cliente->identificacion,
                'razon_social'            => $cliente->razon_social,
                'email_cliente'           => $cliente->email,
                'telefono_cliente'        => $cliente->telefono,
                'direccion_cliente'       => $cliente->direccion,
                'subtotal_0'              => $subtotal0,
                'subtotal_15'             => $subtotal15,
                'descuento_total'         => $descTotal,
                'total_iva'               => $totalIva,
                'total'                   => $total,
                'observaciones'           => $request->observaciones,
                'tipo'                    => 1,
                'estado'                  => 'activa',
                'tiene_descuento_especial'=> $tieneDescuentoEspecial,
                'email_enviado'           => false,
            ]);

            foreach ($request->detalles as $det) {
                $descPct     = (float)($det['descuento_pct'] ?? 0);
                $precio      = (float)$det['precio'];
                $cantidad    = (float)$det['cantidad'];
                $descuento   = $precio * $cantidad * ($descPct / 100);
                $subtotalDet = ($precio * $cantidad) - $descuento;
                $grabaIva    = (bool)($det['graba_iva'] ?? true);
                $iva         = $grabaIva ? $subtotalDet * 0.15 : 0;

                FacturaDetalle::create([
                    'factura_id'    => $factura->id,
                    'producto_id'   => $det['producto_id'],
                    'codigo'        => $det['codigo']      ?? null,
                    'descripcion'   => $det['descripcion'] ?? null,
                    'cantidad'      => $cantidad,
                    'precio'        => $precio,
                    'descuento_pct' => $descPct,
                    'descuento'     => $descuento,
                    'subtotal'      => $subtotalDet,
                    'iva_pct'       => $grabaIva ? 15 : 0,
                    'iva'           => $iva,
                    'total'         => $subtotalDet + $iva,
                ]);
            }

            $incluyeCredito = false;
            $montoCredito   = 0;

            foreach ($request->formas_pago as $pago) {
                FacturaPago::create([
                    'factura_id' => $factura->id,
                    'forma_pago' => $pago['forma'],
                    'monto'      => $pago['monto'],
                    'plazo'      => $pago['plazo'] ?? null,
                    'unidad'     => $pago['unidad'] ?? null,
                ]);

                if ($pago['forma'] === 'credito') {
                    $incluyeCredito = true;
                    $montoCredito   += (float)$pago['monto'];
                }
            }

            if ($incluyeCredito) {
                $cliente = Cliente::findOrFail($request->cliente_id);
                CuentaCobrar::create([
                    'empresa_id'        => $empresaId,
                    'cliente_id'        => $request->cliente_id,
                    'factura_id'        => $factura->id,
                    'monto'             => $montoCredito,
                    'saldo'             => $montoCredito,
                    'fecha_emision'     => now()->toDateString(),
                    'fecha_vencimiento' => now()->addDays($cliente->dias_credito ?? 30)->toDateString(),
                    'forma_cobro'       => 'credito',
                    'estado'            => 'pendiente',
                ]);
            }

            if ($tieneDescuentoEspecial) {
                DB::table('aprobaciones_especiales')->insert([
                    'empresa_id'   => $empresaId,
                    'usuario_id'   => Auth::id(),
                    'tipo'         => 'descuento_especial',
                    'documento_id' => $factura->id,
                    'referencia'   => $factura->numero_completo,
                    'codigo'       => $request->aprobacion_especial,
                    'created_at'   => now(),
                ]);
            }

            return $factura;
        });

        try {
            $formaPrincipal = collect($request->formas_pago)->sortByDesc('monto')->first()['forma'] ?? 'efectivo';
            $this->asiento->facturaAutorizada(
                empresaId:      $empresaId,
                facturaId:      $factura->id,
                numeroFactura:  $factura->numero_completo,
                subtotal:       $subtotal0 + $subtotal15,
                iva:            $totalIva,
                total:          $total,
                formaPago:      $formaPrincipal,
            );
        } catch (\Throwable) {
            // Asiento contable falla de forma silenciosa para no romper la factura
        }

        $this->auditoria->documento('crear', 'ventas', 'facturas', $factura->id, "Factura {$factura->numero_completo} creada");

        return redirect()->route('ventas.facturas.show', $factura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Factura {$factura->numero_completo} creada correctamente."]);
    }

    public function show(Factura $factura)
    {
        $factura->load(['cliente', 'usuario', 'pagos', 'detalles.producto', 'empresa', 'asiento']);

        return Inertia::render('Ventas/Facturas/Show', [
            'factura' => $factura,
        ]);
    }

    public function anular(Request $request, Factura $factura)
    {
        $request->validate([
            'codigo_aprobacion' => 'required|string',
        ]);

        $usuario = Auth::user();

        $perfilNombre = DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', $usuario->id)
            ->value('perfiles.nombre');

        if ($perfilNombre !== 'superadmin') {
            return back()->withErrors(['error' => 'Solo el SuperAdmin puede anular facturas.']);
        }

        if ($factura->fecha_emision->toDateString() !== now()->toDateString()) {
            return back()->withErrors(['error' => 'Solo se pueden anular facturas del día actual.']);
        }

        DB::transaction(function () use ($request, $factura) {
            $factura->update([
                'estado'     => 'anulada',
                'estado_sri' => 'anulada',
            ]);

            DB::table('aprobaciones_especiales')->insert([
                'empresa_id'   => $factura->empresa_id,
                'usuario_id'   => Auth::id(),
                'tipo'         => 'anular_factura',
                'documento_id' => $factura->id,
                'referencia'   => $factura->numero_completo,
                'codigo'       => $request->codigo_aprobacion,
                'created_at'   => now(),
            ]);
        });

        $this->auditoria->documento('anular', 'ventas', 'facturas', $factura->id, "Factura {$factura->numero_completo} anulada");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Factura {$factura->numero_completo} anulada."]);
    }

    public function enviarSri(Factura $factura)
    {
        // TODO: implementar ciclo SRI (XML + firma + webservice)
        // Pendiente — commit separado
        return response()->json(['message' => 'Funcionalidad SRI pendiente']);
    }

    public function clienteGuardar(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'identificacion' => 'required|string|max:20',
            'razon_social'   => 'required|string|max:300',
            'tipo_identificacion' => 'required|string',
        ]);

        $cliente = Cliente::updateOrCreate(
            [
                'empresa_id'     => $empresaId,
                'identificacion' => $request->identificacion,
            ],
            [
                'razon_social'        => $request->razon_social,
                'tipo_identificacion' => $request->tipo_identificacion,
                'email'               => $request->email,
                'telefono'            => $request->telefono,
                'direccion'           => $request->direccion,
                'tiene_credito'       => $request->tiene_credito ?? false,
                'dias_credito'        => $request->dias_credito ?? 0,
                'cupo_maximo'         => $request->cupo_maximo ?? 0,
            ]
        );

        $accion = $cliente->wasRecentlyCreated ? 'crear' : 'actualizar';
        $this->auditoria->documento($accion, 'ventas', 'clientes', $cliente->id, "{$accion} cliente {$cliente->razon_social}");

        return response()->json(['cliente' => $cliente]);
    }
}
