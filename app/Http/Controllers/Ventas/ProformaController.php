<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
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
use App\Services\AuditoriaService;
use App\Services\SecuencialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProformaController extends Controller
{
    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $query = Proforma::with(['cliente', 'usuario'])
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
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $proformas = $query->paginate(25)->withQueryString();

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
            'cliente_id'             => 'required|integer|exists:clientes,id',
            'detalles'               => 'required|array|min:1',
            'detalles.*.producto_id' => 'required|integer',
            'detalles.*.cantidad'    => 'required|numeric|min:0.01',
            'detalles.*.precio'      => 'required|numeric|min:0.01',
        ]);

        $subtotal  = 0;
        $descTotal = 0;
        $totalIva  = 0;

        foreach ($request->detalles as $det) {
            $cantidad  = (float)$det['cantidad'];
            $precio    = (float)$det['precio'];
            $descPct   = (float)($det['descuento_pct'] ?? 0);
            $descuento = $precio * $cantidad * ($descPct / 100);
            $neto      = ($precio * $cantidad) - $descuento;
            $grabaIva  = (bool)($det['graba_iva'] ?? true);

            $subtotal  += $neto;
            $descTotal += $descuento;
            $totalIva  += $grabaIva ? $neto * 0.15 : 0;
        }

        $total = $subtotal + $totalIva;

        $proforma = DB::transaction(function () use ($request, $empresaId, $subtotal, $descTotal, $totalIva, $total) {
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
                $precio    = (float)$det['precio'];
                $descPct   = (float)($det['descuento_pct'] ?? 0);
                $descuento = $precio * $cantidad * ($descPct / 100);
                $neto      = ($precio * $cantidad) - $descuento;
                $grabaIva  = (bool)($det['graba_iva'] ?? true);
                $iva       = $grabaIva ? $neto * 0.15 : 0;

                ProformaDetalle::create([
                    'proforma_id'   => $proforma->id,
                    'producto_id'   => $det['producto_id'],
                    'descripcion'   => $det['descripcion'] ?? null,
                    'cantidad'      => $cantidad,
                    'precio'        => $precio,
                    'descuento_pct' => $descPct,
                    'descuento'     => $descuento,
                    'subtotal'      => $neto,
                    'iva'           => $iva,
                    'total'         => $neto + $iva,
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

    public function destroy(Proforma $proforma)
    {
        if ($proforma->estado !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden anular proformas en estado pendiente.']);
        }

        $proforma->update(['estado' => 'anulada']);

        $this->auditoria->documento('anular', 'ventas', 'proformas', $proforma->id, "Proforma {$proforma->numero} anulada");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Proforma {$proforma->numero} anulada."]);
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

            foreach ($proforma->detalles as $det) {
                FacturaDetalle::create([
                    'factura_id'    => $factura->id,
                    'producto_id'   => $det->producto_id,
                    'descripcion'   => $det->descripcion,
                    'cantidad'      => $det->cantidad,
                    'precio'        => $det->precio,
                    'descuento_pct' => $det->descuento_pct,
                    'descuento'     => $det->descuento,
                    'subtotal'      => $det->subtotal,
                    'iva_pct'       => 15,
                    'iva'           => $det->iva,
                    'total'         => $det->total,
                ]);
            }

            foreach ($request->formas_pago as $pago) {
                FacturaPago::create([
                    'factura_id' => $factura->id,
                    'forma_pago' => $pago['forma'],
                    'monto'      => $pago['monto'],
                ]);
            }

            $proforma->update(['estado' => 'facturada', 'factura_id' => $factura->id]);

            return $factura;
        });

        $this->auditoria->documento('convertir', 'ventas', 'proformas', $proforma->id, "Proforma {$proforma->numero} convertida a factura {$factura->numero_completo}");

        return redirect()->route('ventas.facturas.show', $factura->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Proforma convertida a factura {$factura->numero_completo}."]);
    }
}
