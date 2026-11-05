<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\DatafastLiquidacion;
use App\Models\DatafastLote;
use App\Models\MovimientoBancario;
use App\Models\TarjetaProcesador;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Módulo Tarjetas (antes «Datafast»), 02-07. Controla el dinero cobrado con tarjeta que aún no
 * llega al banco: cada lote queda pendiente hasta que el procesador deposita el neto.
 * Se conservan el nombre de la clase, las rutas internas y las tablas para no romper permisos.
 */
class DatafastController extends Controller
{
    /** Quién registra la liquidación de un lote (02-07 §2.1). */

    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $procesadores = TarjetaProcesador::where('empresa_id', $empresaId)->orderBy('nombre')->get();
        $plazos = $procesadores->pluck('plazo_dias', 'id');

        $lotes = null;

        if ($request->boolean('buscado')) {
            $query = DatafastLote::where('empresa_id', $empresaId)
                ->with(['bancoCaja', 'liquidacion'])
                ->orderByDesc('fecha');

            if ($request->filled('banco_caja_id')) {
                $query->where('banco_caja_id', $request->banco_caja_id);
            }
            if ($request->filled('procesador_id')) {
                $query->where('procesador_id', $request->procesador_id);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('fecha_desde')) {
                $query->where('fecha', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->where('fecha', '<=', $request->fecha_hasta);
            }
            if ($request->filled('buscar')) {
                $query->where('numero_lote', 'ilike', '%' . $request->buscar . '%');
            }

            $nombres = $procesadores->pluck('nombre', 'id');
            $lotes = $query->get()->map(function ($l) use ($nombres, $plazos) {
                $dias   = $l->estado === 'pendiente' ? (int) floor($l->fecha->diffInDays(now(), true)) : 0;
                $plazo  = $l->procesador_id ? (int) ($plazos[$l->procesador_id] ?? 0) : 0;

                return [
                    'id'             => $l->id,
                    'numero_lote'    => $l->numero_lote,
                    'fecha'          => $l->fecha?->format('d/m/Y'),
                    'banco'          => $l->bancoCaja?->nombre,
                    'banco_caja_id'  => $l->banco_caja_id,
                    'procesador'     => $l->procesador_id ? ($nombres[$l->procesador_id] ?? null) : null,
                    'procesador_id'  => $l->procesador_id,
                    'tipo_tarjeta'   => $l->tipo_tarjeta,
                    'total_vouchers' => $l->total_vouchers,
                    'estado'         => $l->estado,
                    'dias'           => $dias,
                    // Lote atrasado: pendiente más días que el plazo del procesador
                    'atrasado'       => $l->estado === 'pendiente' && $plazo > 0 && $dias > $plazo,
                    'liquidacion'    => $l->liquidacion ? [
                        'fecha_deposito'    => $l->liquidacion->fecha_deposito?->format('d/m/Y'),
                        'valor_bruto'       => $l->liquidacion->valor_bruto,
                        'comision_datafast' => $l->liquidacion->comision_datafast,
                        'iva_comision'      => $l->liquidacion->iva_comision,
                        'valor_neto'        => $l->liquidacion->valor_neto,
                        'diferencia'        => $l->liquidacion->diferencia,
                        'requiere_revision' => (bool) $l->liquidacion->requiere_revision,
                    ] : null,
                ];
            });
        }

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        return Inertia::render('Bancos/Datafast/Index', [
            'pendientes'   => $this->ventasPendientes((int) $empresaId),
            'lotes'        => $lotes,
            'bancos'       => $bancos,
            'procesadores' => $procesadores,
            'puedeLiquidar' => $this->puedeLiquidar(),
            'filtros'      => $request->only(['banco_caja_id', 'procesador_id', 'estado', 'fecha_desde', 'fecha_hasta', 'buscar']),
        ]);
    }

    /**
     * Ventas cobradas con tarjeta (en la factura) que todavía no están en ningún lote: son las que el contador
     * selecciona al crear el lote con lo que el procesador incluyó en el depósito (02-07 §2.2).
     */
    private function ventasPendientes(int $empresaId, ?array $ids = null)
    {
        return DB::table('factura_pagos as p')
            ->join('facturas as f', 'f.id', '=', 'p.factura_id')
            ->leftJoin('datafast_lote_pagos as lp', 'lp.factura_pago_id', '=', 'p.id')
            ->where('f.empresa_id', $empresaId)->where('p.forma_pago', 'tarjeta')
            ->where('f.estado', '!=', 'anulada')->whereNull('lp.id')
            ->when($ids !== null, fn ($q) => $q->whereIn('p.id', $ids))
            ->orderBy('f.fecha_emision')->orderBy('p.id')
            ->get(['p.id', 'p.valor', 'p.procesador_id', 'p.tipo_tarjeta', 'p.lote as lote_venta', 'p.autorizacion',
                   'f.numero_completo as factura', 'f.fecha_emision as fecha', 'f.razon_social as cliente'])
            ->map(fn ($r) => [
                'id' => (int) $r->id, 'valor' => round((float) $r->valor, 2), 'procesador_id' => $r->procesador_id ? (int) $r->procesador_id : null,
                'tipo_tarjeta' => $r->tipo_tarjeta, 'lote_venta' => $r->lote_venta, 'autorizacion' => $r->autorizacion,
                'factura' => $r->factura, 'fecha' => \Carbon\Carbon::parse($r->fecha)->format('d/m/Y'), 'cliente' => $r->cliente,
            ]);
    }

    // ── Configuración: procesadores (02-07 §2.3) ──────────────────────────────

    public function storeProcesador(Request $request): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        TarjetaProcesador::create($this->validarProcesador($request, $empresaId) + ['empresa_id' => $empresaId, 'estado' => true]);

        return back()->with('success', 'Procesador registrado.');
    }

    public function updateProcesador(Request $request, TarjetaProcesador $procesador): RedirectResponse
    {
        abort_unless((int) $procesador->empresa_id === (int) session('empresa_activa_id'), 404);
        $procesador->update($this->validarProcesador($request, (int) $procesador->empresa_id) + ['estado' => $request->boolean('estado', true)]);

        return back()->with('success', 'Procesador actualizado.');
    }

    private function validarProcesador(Request $request, int $empresaId): array
    {
        $v = $request->validate([
            'nombre'            => 'required|string|max:100',
            'banco_destino_id'  => ['nullable', \Illuminate\Validation\Rule::exists('bancos_cajas', 'id')->where('empresa_id', $empresaId)],
            'comision_credito'  => 'nullable|numeric|min:0|max:100',
            'comision_debito'   => 'nullable|numeric|min:0|max:100',
            'comision_diferido' => 'nullable|numeric|min:0|max:100',
            'retencion_iva_pct' => 'nullable|numeric|min:0|max:100',
            'retencion_ir_pct'  => 'nullable|numeric|min:0|max:100',
            'iva_comision_pct'  => 'nullable|numeric|min:0|max:100',
            'plazo_dias'        => 'nullable|integer|min:0|max:120',
        ]);

        // Los campos vacíos llegan como null: se completan con el valor por defecto de la tabla.
        return array_merge(
            ['comision_credito' => 0, 'comision_debito' => 0, 'comision_diferido' => 0,
             'retencion_iva_pct' => 0, 'retencion_ir_pct' => 0, 'iva_comision_pct' => 15, 'plazo_dias' => 3],
            array_filter($v, fn ($x) => $x !== null && $x !== '')
        );
    }

    // ── Lotes ─────────────────────────────────────────────────────────────────

    public function storeLote(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'banco_caja_id'  => 'required|exists_empresa:bancos_cajas',
            'procesador_id'  => ['nullable', \Illuminate\Validation\Rule::exists('tarjeta_procesadores', 'id')->where('empresa_id', $empresaId)],
            'tipo_tarjeta'   => 'nullable|in:credito,debito,diferido',
            'numero_lote'    => 'required|string|max:50',
            'fecha'          => 'required|date',
            'pagos'          => 'required|array|min:1',
            'pagos.*'        => 'integer',
            'bruto_reporte'  => 'required|numeric|decimal:0,2|min:0.01',
        ], [
            'pagos.required'         => 'Seleccione las ventas que el procesador incluyó en este lote.',
            'pagos.min'              => 'Seleccione al menos una venta para el lote.',
            'bruto_reporte.required' => 'Ingrese el bruto que indica el reporte del procesador.',
        ]);

        // Solo ventas con tarjeta de esta empresa, vigentes y que no estén ya en otro lote
        $ventas = $this->ventasPendientes((int) $empresaId, array_map('intval', $request->pagos));
        if ($ventas->count() !== count(array_unique($request->pagos))) {
            return back()->with('error', 'Alguna venta seleccionada ya no está disponible (anulada o incluida en otro lote). Actualice la lista.')->withInput();
        }
        $sumaVentas = round((float) $ventas->sum('valor'), 2);
        if (abs($sumaVentas - round((float) $request->bruto_reporte, 2)) > 0.01) {
            return back()->with('error', 'Las ventas seleccionadas suman $' . number_format($sumaVentas, 2)
                . ' y el reporte del procesador indica $' . number_format((float) $request->bruto_reporte, 2)
                . ': corrija la selección o el valor del reporte.')->withInput();
        }

        $existe = DatafastLote::where('empresa_id', $empresaId)
            ->where('numero_lote', $request->numero_lote)->exists();
        if ($existe) {
            return back()->with('error', "El lote {$request->numero_lote} ya existe.");
        }

        try {
            // Sin asiento aquí: cada factura pagada con tarjeta YA registró su venta + IVA al emitirse
            // (débito vouchers / crédito ventas + IVA). El lote solo marca que ese total está pendiente
            // de depositarse; la reclasificación real (vouchers → banco) ocurre al liquidar.
            DB::transaction(function () use ($empresaId, $request, $ventas, $sumaVentas) {
                $lote = DatafastLote::create([
                    'empresa_id'     => $empresaId,
                    'banco_caja_id'  => $request->banco_caja_id,
                    'procesador_id'  => $request->procesador_id,
                    'tipo_tarjeta'   => $request->tipo_tarjeta,
                    'numero_lote'    => $request->numero_lote,
                    'fecha'          => $request->fecha,
                    'total_vouchers' => $sumaVentas,
                    'estado'         => 'pendiente',
                    'created_by'     => Auth::id(),
                    'created_at'     => now(),
                ]);
                DB::table('datafast_lote_pagos')->insert($ventas->map(fn ($v) => [
                    'lote_id' => $lote->id, 'factura_pago_id' => $v['id'], 'valor' => $v['valor'], 'created_at' => now(),
                ])->all());
            });

            return back()->with('success', "Lote {$request->numero_lote} registrado correctamente.");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ── Liquidación (PC-TAR-01, PC-TAR-02) ────────────────────────────────────

    /**
     * Liquida un lote con los valores del reporte del procesador (el reporte siempre prevalece).
     * Asiento: Debe banco (neto depositado), comisión sin IVA, IVA de la comisión y retenciones;
     * Haber vouchers por liquidar (bruto del lote). Si el neto del reporte no coincide con el
     * esperado, la diferencia va a 6.01.14 marcada para revisión y exige observación (PC-TAR-02).
     * Si el asiento no se puede generar, el lote no se guarda.
     */
    public function liquidar(Request $request, DatafastLote $lote): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');

        if ((int) $lote->empresa_id !== $empresaId) {
            abort(404);
        }
        if ($lote->estaLiquidado()) {
            return back()->with('error', 'Este lote ya está liquidado.');
        }

        $request->validate([
            'fecha_deposito'    => 'required|date',
            'comision_datafast' => 'required|numeric|min:0',
            'iva_comision'      => 'nullable|numeric|min:0',
            'retencion_iva'     => 'nullable|numeric|min:0',
            'retencion_ir'      => 'nullable|numeric|min:0',
            'intereses_diferido' => 'nullable|numeric|min:0',
            'valor_neto'        => 'required|numeric|min:0',
            'banco_destino_id'  => ['required', \Illuminate\Validation\Rule::exists('bancos_cajas', 'id')->where('empresa_id', $empresaId)],
            'observacion'       => 'nullable|string|max:500',
        ], [
            'valor_neto.required'       => 'Ingrese el neto depositado que indica el reporte del procesador.',
            'banco_destino_id.required' => 'Seleccione el banco donde se depositó.',
        ]);

        $bruto      = round((float) $lote->total_vouchers, 2);
        $comision   = round((float) $request->comision_datafast, 2);
        $ivaCom     = round((float) ($request->iva_comision ?? 0), 2);
        $retIva     = round((float) ($request->retencion_iva ?? 0), 2);
        $retIr      = round((float) ($request->retencion_ir ?? 0), 2);
        $intereses  = round((float) ($request->intereses_diferido ?? 0), 2);
        $neto       = round((float) $request->valor_neto, 2);

        // Los intereses del diferido van a gasto financiero, aparte de la comisión (PC-TAR-04, DEC-024)
        if ($intereses > 0 && $lote->tipo_tarjeta !== 'diferido') {
            return back()->with('error', 'Los intereses solo aplican a lotes de tarjeta diferida.');
        }

        // Neto que debió depositarse con los valores del reporte; lo demás es diferencia.
        $esperado   = round($bruto - $comision - $ivaCom - $retIva - $retIr - $intereses, 2);
        $diferencia = round($neto - $esperado, 2);       // > 0 sobrante, < 0 faltante

        if (abs($diferencia) > 0.01 && strlen(trim((string) $request->observacion)) < 5) {
            return back()->withErrors(['observacion' =>
                'El neto depositado no coincide con el esperado (diferencia de $' . number_format(abs($diferencia), 2) .
                '): escriba la observación. Quedará marcado para revisión.']);
        }

        $banco = BancoCaja::where('empresa_id', $empresaId)->findOrFail($request->banco_destino_id);
        if (!$banco->cuenta_id) {
            return back()->with('error', "El banco {$banco->nombre} no tiene cuenta contable asignada.");
        }

        try {
            $codigos = ['cta_vouchers', 'cta_comisiones_bancarias'];
            if ($ivaCom > 0)            $codigos[] = 'cta_iva_comision_tarjeta';
            if ($retIva > 0)            $codigos[] = 'cta_retencion_iva_cobrada';
            if ($retIr > 0)             $codigos[] = 'cta_retencion_ir_cobrada';
            if (abs($diferencia) > 0.01) $codigos[] = 'cta_ajuste_conciliacion';
            $this->asientoService->validarConfiguracion($empresaId, $codigos, $request->fecha_deposito);

            DB::transaction(function () use ($request, $lote, $empresaId, $bruto, $comision, $ivaCom, $retIva, $retIr, $intereses, $neto, $diferencia, $banco) {
                $cc  = CentroCosto::where('empresa_id', $empresaId)->where('tipo', 'empresa')->value('id');
                $cta = fn (string $p) => $this->asientoService->cuentaId($p, $empresaId);
                $ref = "lote {$lote->numero_lote}";

                $partidas = [
                    ['cuenta_id' => $banco->cuenta_id, 'debe' => $neto, 'haber' => 0, 'descripcion' => "Depósito {$ref}"],
                    ['cuenta_id' => $cta('cta_comisiones_bancarias'), 'debe' => $comision, 'haber' => 0, 'centro_costo_id' => $cc, 'descripcion' => "Comisión tarjeta {$ref}"],
                ];
                if ($intereses > 0) $partidas[] = ['cuenta_id' => $cta('cta_comisiones_bancarias'), 'debe' => $intereses, 'haber' => 0, 'centro_costo_id' => $cc, 'descripcion' => "Intereses de tarjeta diferida {$ref}"];
                if ($ivaCom > 0) $partidas[] = ['cuenta_id' => $cta('cta_iva_comision_tarjeta'), 'debe' => $ivaCom, 'haber' => 0, 'descripcion' => "IVA de la comisión {$ref}"];
                if ($retIr > 0)  $partidas[] = ['cuenta_id' => $cta('cta_retencion_ir_cobrada'), 'debe' => $retIr, 'haber' => 0, 'descripcion' => "Ret. IR tarjeta {$ref}"];
                if ($retIva > 0) $partidas[] = ['cuenta_id' => $cta('cta_retencion_iva_cobrada'), 'debe' => $retIva, 'haber' => 0, 'descripcion' => "Ret. IVA tarjeta {$ref}"];

                // PC-TAR-02: faltante al debe, sobrante al haber, ambos en 6.01.14
                if ($diferencia < -0.01) {
                    $partidas[] = ['cuenta_id' => $cta('cta_ajuste_conciliacion'), 'debe' => abs($diferencia), 'haber' => 0, 'centro_costo_id' => $cc, 'descripcion' => "Faltante en liquidación {$ref}"];
                } elseif ($diferencia > 0.01) {
                    $partidas[] = ['cuenta_id' => $cta('cta_ajuste_conciliacion'), 'debe' => 0, 'haber' => $diferencia, 'centro_costo_id' => $cc, 'descripcion' => "Sobrante en liquidación {$ref}"];
                }

                $partidas[] = ['cuenta_id' => $cta('cta_vouchers'), 'debe' => 0, 'haber' => $bruto, 'descripcion' => "Liquidación {$ref}"];

                // Si no cuadra, no se guarda
                $debe  = round(array_sum(array_column($partidas, 'debe')), 2);
                $haber = round(array_sum(array_column($partidas, 'haber')), 2);
                if (abs($debe - $haber) > 0.01) {
                    throw new \DomainException("El asiento de liquidación no cuadra (debe {$debe} / haber {$haber}).");
                }

                $liquidacion = DatafastLiquidacion::create([
                    'lote_id'           => $lote->id,
                    'fecha_deposito'    => $request->fecha_deposito,
                    'valor_bruto'       => $bruto,
                    'comision_datafast' => $comision,
                    'iva_comision'      => $ivaCom,
                    'retencion_iva'     => $retIva,
                    'retencion_ir'      => $retIr,
                    'intereses_diferido' => $intereses,
                    'valor_neto'        => $neto,
                    'diferencia'        => $diferencia,
                    'observacion'       => $request->observacion,
                    'requiere_revision' => abs($diferencia) > 0.01,
                    'banco_destino_id'  => $banco->id,
                    'created_by'        => Auth::id(),
                    'created_at'        => now(),
                ]);

                $lote->update(['estado' => 'liquidado']);
                $banco->actualizarSaldo($neto, 'ingreso');

                // El depósito neto se ve en Movimientos y se concilia contra el estado de cuenta
                $mov = MovimientoBancario::create([
                    'empresa_id'     => $empresaId,
                    'banco_caja_id'  => $banco->id,
                    'tipo'           => 'ingreso',
                    'sub_tipo'       => 'deposito',
                    'fecha'          => $request->fecha_deposito,
                    'monto'          => $neto,
                    'beneficiario'   => 'Tarjetas',
                    'num_documento'  => $lote->numero_lote,
                    'descripcion'    => "Depósito tarjetas {$ref}",
                    'documento_tipo' => 'DATAFAST',
                    'documento_id'   => $liquidacion->id,
                    'anulado'        => false,
                    'conciliado'     => false,
                    'created_by'     => Auth::id(),
                ]);

                $asiento = $this->asientoService->crear(
                    empresaId:     $empresaId,
                    concepto:      "Liquidación de tarjetas {$ref}",
                    partidas:      $partidas,
                    documentoTipo: 'BANCO',
                    documentoId:   $liquidacion->id,
                    documentoRef:  "TAR-{$lote->numero_lote}",
                    esAutomatico:  true,
                    fecha:         $request->fecha_deposito,
                );
                $liquidacion->update(['asiento_id' => $asiento->id]);
                $mov->update(['asiento_id' => $asiento->id]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo liquidar el lote: ' . $e->getMessage());
        }

        $msg = "Lote {$lote->numero_lote} liquidado. Neto depositado: \$" . number_format($neto, 2);
        if (abs($diferencia) > 0.01) {
            $msg .= ' · ' . ($diferencia > 0 ? 'Sobrante' : 'Faltante') . ' de $' . number_format(abs($diferencia), 2) . ' marcado para revisión.';
        }

        return back()->with(abs($diferencia) > 0.01 ? 'warning' : 'success', $msg);
    }

    private function puedeLiquidar(): bool
    {
        // Lo decide la matriz de permisos (submódulo «Liquidar lotes de tarjeta»); solo para mostrar el botón.
        return (bool) Auth::user()?->puede('bancos_liquidar_tarjetas', 'editar');
    }
}
