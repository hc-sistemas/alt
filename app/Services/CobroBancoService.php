<?php

namespace App\Services;

use App\Models\BancoCaja;
use App\Models\Factura;
use App\Models\MovimientoBancario;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Lleva a Bancos el dinero de las ventas cobradas en el acto (facturas) y calcula lo que debería
 * haber en el cierre de caja.
 *
 * Todo es configurable por el usuario (Bancos → Configuración de cobros) y NO bloquea la venta:
 * si una forma de pago no tiene cuenta configurada, esa parte simplemente no se registra en Bancos
 * (la factura funciona igual que antes).
 *
 *  - Efectivo:      caja cuyo centro de costo coincide con el de la factura; si no hay, no se
 *                   registra en Bancos (no existe caja por defecto).
 *  - Transferencia / cheque: banco configurado para esa forma de pago.
 *  - Tarjeta:       banco configurado, o ninguno si el modo es "datafast" (entra por lote/liquidación).
 *  - Crédito:       nunca (genera cuenta por cobrar, no dinero).
 */
class CobroBancoService
{
    public const CLAVES = [
        // 'cobro_banco_transferencia' ya no existe: el banco de cada transferencia se elige en la factura (01 §5)
        'cobro_banco_tarjeta',
        'cobro_banco_cheque',
        'cobro_tarjeta_modo', // 'banco' | 'datafast'
    ];

    private const SUB_TIPO = [
        'efectivo'      => 'efectivo',
        'transferencia' => 'transferencia',
        'cheque'        => 'cheque',
        'tarjeta'       => 'deposito',
    ];

    /** @return array<string, string|null> clave => valor */
    public function config(int $empresaId): array
    {
        $filas = DB::table('configuraciones')
            ->where('empresa_id', $empresaId)
            ->whereIn('clave', self::CLAVES)
            ->pluck('valor', 'clave')
            ->all();

        return array_merge(array_fill_keys(self::CLAVES, null), $filas);
    }

    /** Cuenta (banco o caja) donde entra una forma de pago, o null si no está definida. */
    public function cuentaPara(int $empresaId, string $forma, ?int $centroCostoId, ?int $bancoElegidoId = null): ?BancoCaja
    {
        // Si al cobrar se eligió la cuenta bancaria (transferencia, cheque, tarjeta a banco), manda esa
        if ($bancoElegidoId && $forma !== 'efectivo') {
            $elegido = $this->buscarActiva($empresaId, $bancoElegidoId);
            if ($elegido) {
                return $elegido;
            }
        }

        $cfg = $this->config($empresaId);

        if ($forma === 'efectivo') {
            if ($centroCostoId) {
                $caja = BancoCaja::where('empresa_id', $empresaId)->activos()->cajas()
                    ->where('centro_costo_id', $centroCostoId)
                    ->orderBy('tipo')->orderBy('id')->first();
                if ($caja) {
                    return $caja;
                }
            }
            // Sin caja en el centro de costo no hay caja por defecto: el efectivo no se registra en Bancos (02-06 §2.6)
            return null;
        }

        if ($forma === 'tarjeta' && ($cfg['cobro_tarjeta_modo'] ?? null) === 'datafast') {
            return null;
        }

        $clave = match ($forma) {
            'tarjeta'       => 'cobro_banco_tarjeta',
            'cheque'        => 'cobro_banco_cheque',
            default         => null,
        };

        return $clave ? $this->buscarActiva($empresaId, $cfg[$clave]) : null;
    }

    private function buscarActiva(int $empresaId, $id): ?BancoCaja
    {
        if (!$id) {
            return null;
        }
        return BancoCaja::where('empresa_id', $empresaId)->activos()->find($id);
    }

    /** Ingresos en Bancos por cada forma de pago (no crédito) de la factura. Idempotente. */
    public function ingresosFactura(Factura $factura, ?int $asientoId = null): int
    {
        $ya = MovimientoBancario::where('documento_tipo', 'FACTURA_VENTA')
            ->where('documento_id', $factura->id)->exists();
        if ($ya) {
            return 0;
        }

        $factura->loadMissing('pagos');
        $creados = 0;

        DB::transaction(function () use ($factura, $asientoId, &$creados) {
            foreach ($factura->pagos as $pago) {
                // Crédito no es dinero; el cheque recibido queda en cartera y solo entra a Bancos al depositarlo (02-04 §2.7)
                if (in_array($pago->forma_pago, ['credito', 'cheque'], true) || (float) $pago->valor <= 0) {
                    continue;
                }

                $cuenta = $this->cuentaPara((int) $factura->empresa_id, $pago->forma_pago, $factura->centro_costo_id, $pago->banco_caja_id ? (int) $pago->banco_caja_id : null);
                if (!$cuenta) {
                    continue;
                }

                MovimientoBancario::create([
                    'empresa_id'      => $factura->empresa_id,
                    'banco_caja_id'   => $cuenta->id,
                    'tipo'            => 'ingreso',
                    'sub_tipo'        => self::SUB_TIPO[$pago->forma_pago] ?? 'deposito',
                    'fecha'           => $factura->fecha_emision?->toDateString() ?? now()->toDateString(),
                    'monto'           => $pago->valor,
                    'persona_tipo'    => 'cliente',
                    'persona_id'      => $factura->cliente_id,
                    'beneficiario'    => $factura->razon_social,
                    'num_documento'   => $factura->numero_completo,
                    'num_cheque'      => $pago->num_cheque ?: null,
                    'descripcion'     => "Cobro factura {$factura->numero_completo} ({$pago->forma_pago})",
                    'documento_tipo'  => 'FACTURA_VENTA',
                    'documento_id'    => $factura->id,
                    'centro_costo_id' => $factura->centro_costo_id,
                    'asiento_id'      => $asientoId,
                    'anulado'         => false,
                    'conciliado'      => false,
                    'created_by'      => Auth::id(),
                ]);
                $cuenta->actualizarSaldo((float) $pago->valor, 'ingreso');
                $creados++;
            }
        });

        return $creados;
    }

    /** Revierte (movimiento de signo contrario, sin borrar nada) los ingresos de una factura. */
    public function revertirFactura(Factura $factura, string $motivo): void
    {
        // Los cheques recibidos de la factura que siguen en cartera se anulan (no hay movimiento bancario que revertir)
        \App\Models\ChequeRecibido::where('factura_id', $factura->id)->where('estado', 'en_cartera')->update(['estado' => 'anulado']);

        $movs = MovimientoBancario::where('documento_tipo', 'FACTURA_VENTA')
            ->where('documento_id', $factura->id)
            ->where('anulado', false)
            ->get();

        foreach ($movs as $mov) {
            $mov->update(['anulado' => true]);

            MovimientoBancario::create([
                'empresa_id'      => $mov->empresa_id,
                'banco_caja_id'   => $mov->banco_caja_id,
                'tipo'            => 'egreso',
                'sub_tipo'        => $mov->sub_tipo,
                'fecha'           => now()->toDateString(),
                'monto'           => $mov->monto,
                'persona_tipo'    => $mov->persona_tipo,
                'persona_id'      => $mov->persona_id,
                'beneficiario'    => $mov->beneficiario,
                'num_documento'   => $mov->num_documento,
                'descripcion'     => "Reversión cobro factura {$factura->numero_completo} — {$motivo}",
                'documento_tipo'  => 'ANULACION_FACTURA',
                'documento_id'    => $factura->id,
                'centro_costo_id' => $mov->centro_costo_id,
                'anulado'         => false,
                'conciliado'      => false,
                'created_by'      => Auth::id(),
            ]);

            BancoCaja::find($mov->banco_caja_id)?->actualizarSaldo((float) $mov->monto, 'egreso');
        }
    }

    /**
     * Lo que deberían haber cobrado las ventas del día de un centro de costo, por forma de pago
     * (sin crédito y sin facturas anuladas). Sirve de "total facturado" del cierre de caja.
     *
     * @return array{efectivo: float, tarjeta: float, cheque: float, transferencia: float, total: float}
     */
    public function ventasEsperadas(int $empresaId, ?int $centroCostoId, string $fecha): array
    {
        $vacio = ['efectivo' => 0.0, 'tarjeta' => 0.0, 'cheque' => 0.0, 'transferencia' => 0.0, 'total' => 0.0];
        if (!$centroCostoId) {
            return $vacio;
        }

        $filas = DB::table('factura_pagos as p')
            ->join('facturas as f', 'f.id', '=', 'p.factura_id')
            ->where('f.empresa_id', $empresaId)
            ->where('f.centro_costo_id', $centroCostoId)
            ->whereDate('f.fecha_emision', $fecha)
            ->where('f.estado', '!=', 'anulada')
            ->where('p.forma_pago', '!=', 'credito')
            ->groupBy('p.forma_pago')
            ->selectRaw('p.forma_pago, SUM(p.valor) as total')
            ->pluck('total', 'forma_pago');

        $res = $vacio;
        foreach ($filas as $forma => $total) {
            $clave = array_key_exists($forma, $res) ? $forma : 'transferencia';
            $res[$clave] += (float) $total;
        }
        $res['total'] = round($res['efectivo'] + $res['tarjeta'] + $res['cheque'] + $res['transferencia'], 2);

        return $res;
    }
}
