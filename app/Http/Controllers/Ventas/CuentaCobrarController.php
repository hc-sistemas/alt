<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\CuentaCobrar;
use App\Models\CuentaCobrarCobro;
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
                // abs(): diffInDays() en Carbon 3 es firmado (negativo si la fecha de
                // vencimiento, ya pasada, es anterior a hoy) y se necesita el conteo positivo.
                ? (int) abs(now()->startOfDay()->diffInDays($c->fecha_vencimiento->startOfDay()))
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
        $cuentaCobrar->load(['cliente', 'factura', 'cobros.usuario']);

        $hoy         = now()->toDateString();
        $fv          = $cuentaCobrar->fecha_vencimiento?->toDateString();
        $diasVencido = ($fv && $fv < $hoy)
            // abs(): ver nota en index() sobre diffInDays() firmado en Carbon 3
            ? (int) abs(now()->startOfDay()->diffInDays($cuentaCobrar->fecha_vencimiento->startOfDay()))
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
                'cobros'                 => $cuentaCobrar->cobros->map(fn($c) => [
                    'id'              => $c->id,
                    'fecha'           => $c->fecha?->toDateString(),
                    'valor'           => (float) $c->valor,
                    'forma_pago'      => $c->forma_pago,
                    'observacion'     => $c->observacion,
                    'usuario_nombre'  => $c->usuario?->nombre ?? null,
                ]),
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

            CuentaCobrarCobro::create([
                'cuenta_cobrar_id' => $cuentaCobrar->id,
                'usuario_id'       => Auth::id(),
                'fecha'            => now()->toDateString(),
                'valor'            => $monto,
                'forma_pago'       => $request->forma_pago,
                'observacion'      => $request->observacion,
                'created_at'       => now(),
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
            'aprobacion_especial_id' => 'required|integer',
        ]);

        $usuario = Auth::user();

        $perfilNombre = DB::table('perfiles')
            ->join('usuarios', 'usuarios.perfil_id', '=', 'perfiles.id')
            ->where('usuarios.id', $usuario->id)
            ->value('perfiles.nombre');

        if ($perfilNombre !== 'super_admin') {
            return back()->withErrors(['error' => 'Solo el SuperAdmin puede castigar deudas.']);
        }

        $fv = $cuentaCobrar->fecha_vencimiento?->toDateString();
        $hoy = now()->toDateString();
        $diasVencido = ($fv && $fv < $hoy)
            // abs(): ver nota en index() sobre diffInDays() firmado en Carbon 3
            ? (int) abs(now()->startOfDay()->diffInDays($cuentaCobrar->fecha_vencimiento->startOfDay()))
            : 0;

        if ($diasVencido <= 360) {
            return back()->withErrors([
                'error' => "Solo se pueden castigar cuentas con más de 360 días de vencimiento (esta tiene {$diasVencido}).",
            ]);
        }

        // Mismo esquema de aprobación especial que Facturas/Proformas (ver
        // FacturaController::store(), caso 'descuento_excedido'): la aprobación
        // debe existir, haber sido pedida por este mismo usuario, ser del tipo
        // correcto, y no haberse usado todavía.
        $aprobacion = DB::table('aprobaciones_especiales')
            ->join('tipos_aprobacion', 'tipos_aprobacion.id', '=', 'aprobaciones_especiales.tipo_aprobacion_id')
            ->where('aprobaciones_especiales.id', $request->input('aprobacion_especial_id'))
            ->where('aprobaciones_especiales.solicitado_por', $usuario->id)
            ->where('tipos_aprobacion.clave', 'castigo_cartera')
            ->whereNull('aprobaciones_especiales.registro_id')
            ->select('aprobaciones_especiales.id')
            ->first();

        if (!$aprobacion) {
            return back()->withErrors(['aprobacion_especial' => 'La aprobación especial no es válida o ya fue utilizada.']);
        }

        $montoCastigado = (float) $cuentaCobrar->saldo;

        $ctaGastoIncobrables = DB::table('plan_cuentas')->where('codigo', '5.2.4.01')->value('id');
        $ctaProvisionIncobrables = DB::table('plan_cuentas')->where('codigo', '1.1.3.05')->value('id');

        if (!$ctaGastoIncobrables || !$ctaProvisionIncobrables) {
            return back()->withErrors(['error' => 'Faltan cuentas del plan de cuentas (5.2.4.01 / 1.1.3.05) para registrar el castigo.']);
        }

        DB::transaction(function () use ($cuentaCobrar, $aprobacion, $montoCastigado, $ctaGastoIncobrables, $ctaProvisionIncobrables) {
            $cuentaCobrar->update(['estado' => 'castigada', 'saldo' => 0]);

            // Marca la aprobación como consumida — mismo patrón que
            // FacturaController::store() para 'descuento_excedido'.
            DB::table('aprobaciones_especiales')
                ->where('id', $aprobacion->id)
                ->update([
                    'tabla_referencia' => 'cuentas_cobrar',
                    'registro_id'      => $cuentaCobrar->id,
                    'updated_at'       => now(),
                ]);

            $asiento = $this->asiento->crear(
                empresaId: $cuentaCobrar->empresa_id,
                concepto:  "Castigo de cartera CXC-{$cuentaCobrar->id}",
                partidas:  [
                    ['cuenta_id' => $ctaGastoIncobrables,     'debe' => $montoCastigado, 'haber' => 0, 'descripcion' => "Castigo de cartera CXC-{$cuentaCobrar->id}"],
                    ['cuenta_id' => $ctaProvisionIncobrables, 'debe' => 0, 'haber' => $montoCastigado, 'descripcion' => "Castigo de cartera CXC-{$cuentaCobrar->id}"],
                ],
                documentoTipo: 'CXC',
                documentoId:   $cuentaCobrar->id,
                documentoRef:  "CXC-{$cuentaCobrar->id}",
                esAutomatico:  true,
            );

            $cuentaCobrar->update(['asiento_cobro_id' => $asiento->id]);
        });

        $this->auditoria->documento('castigar', 'ventas', 'cuentas_cobrar', $cuentaCobrar->id, "Castigo de deuda CXC {$cuentaCobrar->id} por \${$montoCastigado}");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => "Deuda CXC-{$cuentaCobrar->id} castigada por \${$montoCastigado}."]);
    }
}
