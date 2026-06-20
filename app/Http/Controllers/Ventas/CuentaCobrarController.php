<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\CuentaCobrar;
use App\Services\AsientoService;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CuentaCobrarController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
        private AsientoService   $asiento,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');
        $hoy       = now()->toDateString();

        $query = CuentaCobrar::with(['cliente', 'factura'])
            ->where('empresa_id', $empresaId)
            ->orderBy('fecha_vencimiento');

        if ($request->filled('cliente')) {
            $query->whereHas('cliente', fn($q) => $q
                ->where('razon_social', 'ilike', "%{$request->cliente}%")
                ->orWhere('identificacion', 'ilike', "%{$request->cliente}%"));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('vencimiento_desde')) {
            $query->where('fecha_vencimiento', '>=', $request->vencimiento_desde);
        }
        if ($request->filled('vencimiento_hasta')) {
            $query->where('fecha_vencimiento', '<=', $request->vencimiento_hasta);
        }

        $cuentas = $query->paginate(25)->withQueryString();

        $cuentas->getCollection()->transform(function (CuentaCobrar $c) use ($hoy) {
            $fv          = $c->fecha_vencimiento?->toDateString();
            $diasVencido = ($fv && $fv < $hoy)
                ? (int) now()->startOfDay()->diffInDays($c->fecha_vencimiento->startOfDay())
                : 0;

            return [
                'id'                => $c->id,
                'cliente_razon'     => $c->cliente?->razon_social ?? '—',
                'documento_tipo'    => $c->factura_id   ? 'Factura' : ($c->prefactura_id ? 'Prefactura' : 'Otro'),
                'documento_numero'  => $c->factura?->numero_completo ?? "CXC-{$c->id}",
                'fecha_emision'     => $c->fecha_emision?->toDateString(),
                'fecha_vencimiento' => $fv,
                'monto'             => (float) $c->monto,
                'saldo'             => (float) $c->saldo,
                'dias_vencido'      => $diasVencido,
                'estado'            => $c->estado,
            ];
        });

        $activos  = ['pendiente', 'parcial', 'vencida'];

        $metricas = [
            'total_cartera' => (float) CuentaCobrar::where('empresa_id', $empresaId)
                ->whereIn('estado', $activos)->sum('saldo'),
            'por_vencer'    => (float) CuentaCobrar::where('empresa_id', $empresaId)
                ->whereIn('estado', ['pendiente', 'parcial'])
                ->where('fecha_vencimiento', '>=', $hoy)
                ->sum('saldo'),
            'vencido_30'    => (float) CuentaCobrar::where('empresa_id', $empresaId)
                ->whereIn('estado', $activos)
                ->whereBetween('fecha_vencimiento', [now()->subDays(30)->toDateString(), now()->subDay()->toDateString()])
                ->sum('saldo'),
            'vencido_60'    => (float) CuentaCobrar::where('empresa_id', $empresaId)
                ->whereIn('estado', $activos)
                ->whereBetween('fecha_vencimiento', [now()->subDays(60)->toDateString(), now()->subDays(31)->toDateString()])
                ->sum('saldo'),
            'vencido_90'    => (float) CuentaCobrar::where('empresa_id', $empresaId)
                ->whereIn('estado', $activos)
                ->where('fecha_vencimiento', '<', now()->subDays(60)->toDateString())
                ->sum('saldo'),
        ];

        return Inertia::render('Ventas/CxC/Index', [
            'cuentas'  => $cuentas,
            'metricas' => $metricas,
            'filtros'  => $request->only(['cliente', 'estado', 'vencimiento_desde', 'vencimiento_hasta']),
        ]);
    }

    public function show(CuentaCobrar $cuentaCobrar)
    {
        $cuentaCobrar->load(['cliente', 'factura']);

        $hoy         = now()->toDateString();
        $fv          = $cuentaCobrar->fecha_vencimiento?->toDateString();
        $diasVencido = ($fv && $fv < $hoy)
            ? (int) now()->startOfDay()->diffInDays($cuentaCobrar->fecha_vencimiento->startOfDay())
            : 0;

        return Inertia::render('Ventas/CxC/Show', [
            'cuenta' => [
                'id'                     => $cuentaCobrar->id,
                'cliente_razon'          => $cuentaCobrar->cliente?->razon_social ?? '—',
                'cliente_identificacion' => $cuentaCobrar->cliente?->identificacion ?? '—',
                'documento_tipo'         => $cuentaCobrar->factura_id ? 'Factura' : ($cuentaCobrar->prefactura_id ? 'Prefactura' : 'Otro'),
                'documento_numero'       => $cuentaCobrar->factura?->numero_completo ?? "CXC-{$cuentaCobrar->id}",
                'fecha_emision'          => $cuentaCobrar->fecha_emision?->toDateString(),
                'fecha_vencimiento'      => $fv,
                'monto'                  => (float) $cuentaCobrar->monto,
                'saldo'                  => (float) $cuentaCobrar->saldo,
                'dias_vencido'           => $diasVencido,
                'estado'                 => $cuentaCobrar->estado,
                'cobros'                 => [],
            ],
        ]);
    }

    public function registrarCobro(Request $request, CuentaCobrar $cuentaCobrar)
    {
        $request->validate([
            'valor'       => 'required|numeric|min:0.01',
            'forma_pago'  => 'required|string',
            'observacion' => 'nullable|string|max:300',
        ]);

        $monto = (float)$request->valor;

        if ($monto > $cuentaCobrar->saldo) {
            return back()->withErrors(['valor' => 'El valor no puede superar el saldo pendiente.']);
        }

        DB::transaction(function () use ($request, $cuentaCobrar, $monto) {
            $nuevoSaldo  = $cuentaCobrar->saldo - $monto;
            $nuevoEstado = $nuevoSaldo <= 0 ? 'cobrada' : 'parcial';

            $cuentaCobrar->update([
                'saldo'  => max(0, $nuevoSaldo),
                'estado' => $nuevoEstado,
            ]);
        });

        try {
            $referencia = $cuentaCobrar->factura?->numero_completo ?? "CXC-{$cuentaCobrar->id}";
            $asiento = $this->asiento->cobro(
                empresaId:   $cuentaCobrar->empresa_id,
                documentoId: $cuentaCobrar->id,
                referencia:  $referencia,
                monto:       $monto,
                formaPago:   $request->forma_pago,
            );
            $cuentaCobrar->update(['asiento_cobro_id' => $asiento->id]);
        } catch (\Throwable) {
            // Asiento falla de forma silenciosa
        }

        $this->auditoria->documento('cobrar', 'ventas', 'cuentas_cobrar', $cuentaCobrar->id, "Cobro {$monto} a CxC {$cuentaCobrar->id}");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Cobro de \${$monto} registrado correctamente."]);
    }

    public function castigo(Request $request, CuentaCobrar $cuentaCobrar)
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
            return back()->withErrors(['error' => 'Solo el SuperAdmin puede castigar deudas.']);
        }

        DB::transaction(function () use ($request, $cuentaCobrar) {
            $cuentaCobrar->update(['estado' => 'castigada']);

            DB::table('aprobaciones_especiales')->insert([
                'empresa_id'   => $cuentaCobrar->empresa_id,
                'usuario_id'   => Auth::id(),
                'tipo'         => 'castigar_deuda',
                'documento_id' => $cuentaCobrar->id,
                'referencia'   => "CXC-{$cuentaCobrar->id}",
                'codigo'       => $request->codigo_aprobacion,
                'created_at'   => now(),
            ]);
        });

        try {
            $this->asiento->crear(
                empresaId: $cuentaCobrar->empresa_id,
                concepto:  "Castigo de cartera CXC-{$cuentaCobrar->id}",
                partidas:  [],
            );
        } catch (\Throwable) {
            // AsientoService no disponible o sin partidas — silencioso
        }

        $this->auditoria->documento('castigar', 'ventas', 'cuentas_cobrar', $cuentaCobrar->id, "Castigo de deuda CXC {$cuentaCobrar->id}");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Deuda CXC-{$cuentaCobrar->id} castigada."]);
    }
}
