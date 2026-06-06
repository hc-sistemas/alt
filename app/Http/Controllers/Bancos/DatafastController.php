<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\DatafastLiquidacion;
use App\Models\DatafastLote;
use App\Models\ParametroContable;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DatafastController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');

        $lotes = DatafastLote::where('empresa_id', $empresaId)
            ->with(['bancoCaja', 'liquidacion'])
            ->orderByDesc('fecha')
            ->get()
            ->map(fn($l) => [
                'id'             => $l->id,
                'numero_lote'    => $l->numero_lote,
                'fecha'          => $l->fecha?->format('d/m/Y'),
                'banco'          => $l->bancoCaja?->nombre,
                'total_vouchers' => $l->total_vouchers,
                'estado'         => $l->estado,
                'liquidacion'    => $l->liquidacion ? [
                    'fecha_deposito'    => $l->liquidacion->fecha_deposito?->format('d/m/Y'),
                    'valor_bruto'       => $l->liquidacion->valor_bruto,
                    'comision_datafast' => $l->liquidacion->comision_datafast,
                    'valor_neto'        => $l->liquidacion->valor_neto,
                ] : null,
            ]);

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        return Inertia::render('Bancos/Datafast/Index', [
            'lotes'  => $lotes,
            'bancos' => $bancos,
            'stats'  => [
                'pendientes'     => $lotes->where('estado', 'pendiente')->count(),
                'liquidados'     => $lotes->where('estado', 'liquidado')->count(),
                'total_vouchers' => $lotes->sum('total_vouchers'),
            ],
        ]);
    }

    public function storeLote(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'banco_caja_id'  => 'required|exists:bancos_cajas,id',
            'numero_lote'    => 'required|string|max:50',
            'fecha'          => 'required|date',
            'total_vouchers' => 'required|numeric|min:0.01',
        ]);

        $existe = DatafastLote::where('empresa_id', $empresaId)
            ->where('numero_lote', $request->numero_lote)->exists();
        if ($existe) {
            return back()->with('error', "El lote {$request->numero_lote} ya existe.");
        }

        try {
            DB::transaction(function () use ($request, $empresaId) {
                $lote = DatafastLote::create([
                    'empresa_id'    => $empresaId,
                    'banco_caja_id' => $request->banco_caja_id,
                    'numero_lote'   => $request->numero_lote,
                    'fecha'         => $request->fecha,
                    'total_vouchers'=> $request->total_vouchers,
                    'estado'        => 'pendiente',
                    'created_by'    => Auth::id(),
                    'created_at'    => now(),
                ]);

                try {
                    $ctaVouchers = ParametroContable::getCuentaId('cta_vouchers', $empresaId);
                    $ctaVentas   = ParametroContable::getCuentaId('cta_ventas_locales', $empresaId);

                    if ($ctaVouchers && $ctaVentas) {
                        $asiento = $this->asientoService->crear(
                            empresaId:    $empresaId,
                            concepto:     "Lote Datafast {$request->numero_lote}",
                            partidas: [
                                ['cuenta_id' => $ctaVouchers, 'debe' => $request->total_vouchers, 'haber' => 0,
                                 'descripcion' => "Lote {$request->numero_lote}"],
                                ['cuenta_id' => $ctaVentas,   'debe' => 0, 'haber' => $request->total_vouchers,
                                 'descripcion' => "Ventas tarjeta lote {$request->numero_lote}"],
                            ],
                            documentoTipo:'BANCO',
                            documentoId:  $lote->id,
                            esAutomatico: true,
                        );
                        $lote->update(['asiento_id' => $asiento->id]);
                    }
                } catch (\Exception $e) {
                    // No bloquear si período cerrado
                }
            });

            return back()->with('success', "Lote {$request->numero_lote} registrado correctamente.");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function liquidar(Request $request, DatafastLote $lote): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($lote->estaLiquidado()) {
            return back()->with('error', 'Este lote ya está liquidado.');
        }

        $request->validate([
            'fecha_deposito'    => 'required|date',
            'valor_bruto'       => 'required|numeric|min:0',
            'comision_datafast' => 'required|numeric|min:0',
            'retencion_iva'     => 'numeric|min:0',
            'retencion_ir'      => 'numeric|min:0',
            'banco_destino_id'  => 'required|exists:bancos_cajas,id',
        ]);

        $valorNeto = $request->valor_bruto
            - $request->comision_datafast
            - ($request->retencion_iva ?? 0)
            - ($request->retencion_ir  ?? 0);

        try {
            DB::transaction(function () use ($request, $lote, $empresaId, $valorNeto) {
                $liquidacion = DatafastLiquidacion::create([
                    'lote_id'           => $lote->id,
                    'fecha_deposito'    => $request->fecha_deposito,
                    'valor_bruto'       => $request->valor_bruto,
                    'comision_datafast' => $request->comision_datafast,
                    'retencion_iva'     => $request->retencion_iva ?? 0,
                    'retencion_ir'      => $request->retencion_ir  ?? 0,
                    'valor_neto'        => $valorNeto,
                    'banco_destino_id'  => $request->banco_destino_id,
                    'created_by'        => Auth::id(),
                    'created_at'        => now(),
                ]);

                $lote->update(['estado' => 'liquidado']);

                $bancoDestino = BancoCaja::find($request->banco_destino_id);
                $bancoDestino?->actualizarSaldo($valorNeto, 'ingreso');

                try {
                    $ctaVouchers = ParametroContable::getCuentaId('cta_vouchers', $empresaId);
                    $ctaBancos   = $bancoDestino?->cuenta_id;
                    $ctaComision = ParametroContable::getCuentaId('cta_comisiones_bancarias', $empresaId);

                    if ($ctaVouchers && $ctaBancos && $ctaComision) {
                        $partidas = [
                            ['cuenta_id' => $ctaBancos,
                             'debe' => $valorNeto, 'haber' => 0,
                             'descripcion' => "Depósito lote {$lote->numero_lote}"],
                            ['cuenta_id' => $ctaComision,
                             'debe' => $request->comision_datafast, 'haber' => 0,
                             'descripcion' => "Comisión Datafast lote {$lote->numero_lote}"],
                        ];

                        if (($request->retencion_iva ?? 0) > 0) {
                            $ctaRetIVA = ParametroContable::getCuentaId('cta_retencion_iva_cobrada', $empresaId);
                            if ($ctaRetIVA) {
                                $partidas[] = [
                                    'cuenta_id'   => $ctaRetIVA,
                                    'debe'        => $request->retencion_iva,
                                    'haber'       => 0,
                                    'descripcion' => "Ret. IVA Datafast",
                                ];
                            }
                        }

                        $partidas[] = [
                            'cuenta_id'   => $ctaVouchers,
                            'debe'        => 0,
                            'haber'       => $request->valor_bruto,
                            'descripcion' => "Liquidación lote {$lote->numero_lote}",
                        ];

                        $asiento = $this->asientoService->crear(
                            empresaId:    $empresaId,
                            concepto:     "Liquidación Datafast lote {$lote->numero_lote}",
                            partidas:     $partidas,
                            documentoTipo:'BANCO',
                            documentoId:  $liquidacion->id,
                            esAutomatico: true,
                        );
                        $liquidacion->update(['asiento_id' => $asiento->id]);
                    }
                } catch (\Exception $e) {
                    // No bloquear si cuentas no configuradas
                }
            });

            return back()->with('success',
                "Lote {$lote->numero_lote} liquidado. Valor neto: \$" . number_format($valorNeto, 2));

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
