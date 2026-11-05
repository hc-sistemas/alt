<?php

namespace App\Services;

use App\Models\CierreCaja;
use Illuminate\Support\Facades\DB;

/**
 * Reglas del efectivo de la caja (02-05): conteo por denominación y resumen del día.
 * La caja solo cuenta efectivo; tarjetas, cheques y transferencias no entran aquí.
 */
class CajaService
{
    /** Denominaciones (igual en apertura y cierre): clave => valor en dólares. */
    public const DENOMINACIONES = [
        'm_0.01' => 0.01, 'm_0.05' => 0.05, 'm_0.10' => 0.10, 'm_0.25' => 0.25, 'm_0.50' => 0.50, 'm_1.00' => 1.00,
        'b_1' => 1, 'b_2' => 2, 'b_5' => 5, 'b_10' => 10, 'b_20' => 20, 'b_50' => 50, 'b_100' => 100,
    ];

    /** Movimientos que ya se cuentan por la factura y no deben sumarse dos veces. */
    private const ORIGEN_FACTURA = ['FACTURA_VENTA', 'ANULACION_FACTURA'];

    /** Total de un conteo { clave => cantidad } y los subtotales de monedas y billetes. */
    public static function totalConteo(array $conteo): array
    {
        $monedas = $billetes = 0.0;
        foreach (self::DENOMINACIONES as $clave => $valor) {
            $subtotal = round((int) ($conteo[$clave] ?? 0) * $valor, 2);
            str_starts_with($clave, 'm_') ? $monedas += $subtotal : $billetes += $subtotal;
        }

        return ['monedas' => round($monedas, 2), 'billetes' => round($billetes, 2), 'total' => round($monedas + $billetes, 2)];
    }

    /** Conteo limpio (solo claves válidas, enteros no negativos). */
    public static function limpiarConteo(array $conteo): array
    {
        $out = [];
        foreach (array_keys(self::DENOMINACIONES) as $clave) {
            $out[$clave] = max(0, (int) ($conteo[$clave] ?? 0));
        }

        return $out;
    }

    /**
     * Resumen del día de una caja: facturas cobradas en efectivo, total facturado y cobrado
     * en efectivo, otros movimientos de efectivo (pagos, gastos, aportes, depósitos) y el
     * saldo esperado = saldo inicial + cobrado + otros movimientos.
     */
    public function resumen(CierreCaja $cierre): array
    {
        $centroId = $cierre->centro_costo_id ?? $cierre->bancoCaja?->centro_costo_id;
        $fecha    = $cierre->fecha->toDateString();

        $facturas = $cobrado = $facturado = 0;
        if ($centroId) {
            $base = DB::table('factura_pagos as p')
                ->join('facturas as f', 'f.id', '=', 'p.factura_id')
                ->where('f.empresa_id', $cierre->empresa_id)
                ->where('f.centro_costo_id', $centroId)
                ->whereDate('f.fecha_emision', $fecha)
                ->where('f.estado', '!=', 'anulada')
                ->where('p.forma_pago', 'efectivo');

            $facturas = (int) (clone $base)->distinct()->count('f.id');
            $cobrado  = (float) (clone $base)->sum('p.valor');
            $ids      = (clone $base)->distinct()->pluck('f.id');
            $facturado = (float) DB::table('facturas')->whereIn('id', $ids)->sum('total');
        }

        $otros = (float) DB::table('movimientos_bancarios')
            ->where('banco_caja_id', $cierre->banco_caja_id)
            ->whereDate('fecha', $fecha)
            ->where('anulado', false)
            ->where(fn ($q) => $q->whereNull('documento_tipo')->orWhereNotIn('documento_tipo', self::ORIGEN_FACTURA))
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) as neto")
            ->value('neto');

        $inicial  = (float) $cierre->monto_inicial;
        $esperado = round($inicial + $cobrado + $otros, 2);

        return [
            'facturas'  => $facturas,
            'facturado' => round($facturado, 2),
            'cobrado'   => round($cobrado, 2),
            'otros'     => round($otros, 2),
            'inicial'   => round($inicial, 2),
            'esperado'  => $esperado,
        ];
    }

    /**
     * Cómo debe abrirse una caja hoy: el primer día lleva conteo; desde el segundo, el
     * saldo inicial es el saldo final del día anterior y no se escribe.
     */
    public function estadoApertura(int $empresaId, int $bancoCajaId, string $hoy): array
    {
        $anterior = CierreCaja::where('empresa_id', $empresaId)->where('banco_caja_id', $bancoCajaId)
            ->where('fecha', '<', $hoy)->orderByDesc('fecha')->orderByDesc('id')->first();

        return [
            'primer_dia'       => $anterior === null,
            'saldo_inicial'    => $anterior && $anterior->estaCerrada() ? round((float) $anterior->total_efectivo, 2) : null,
            'anterior_abierto' => $anterior !== null && !$anterior->estaCerrada(),
            'fecha_anterior'   => $anterior?->fecha?->format('d/m/Y'),
        ];
    }
}
