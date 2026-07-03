<?php

namespace App\Services;

use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventarioService implements InventarioServiceInterface
{
    // Columnas reales de inventario_saldos:
    //   stock_actual, cantidad_reservada, costo_promedio

    public function ingresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        float $costoUnitario,
        string $docTipo,
        int $docId,
        ?string $docNumero = null,
        ?string $observacion = null,
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $costoUnitario, $docTipo, $docId, $docNumero, $observacion) {
            $saldoActual = DB::table('inventario_saldos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->first();

            $cantActual    = $saldoActual ? (float) $saldoActual->stock_actual : 0;
            $costoActual   = $saldoActual ? (float) $saldoActual->costo_promedio  : 0;
            $nuevaCant     = $cantActual + $cantidad;
            $costoPromedio = $nuevaCant > 0
                ? (($cantActual * $costoActual) + ($cantidad * $costoUnitario)) / $nuevaCant
                : $costoUnitario;

            DB::table('inventario_saldos')->upsert(
                [
                    'producto_id'    => $productoId,
                    'bodega_id'      => $bodegaId,
                    'stock_actual'   => $nuevaCant,
                    'costo_promedio' => round($costoPromedio, 4),
                    'updated_at'     => now(),
                ],
                ['producto_id', 'bodega_id'],
                ['stock_actual', 'costo_promedio', 'updated_at']
            );

            $empresaId = DB::table('bodegas')->where('id', $bodegaId)->value('empresa_id');

            DB::table('inventario_movimientos')->insert([
                'empresa_id'     => $empresaId,
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'entrada',
                'doc_tipo'       => strtoupper($docTipo),
                'doc_id'         => $docId,
                'cantidad'       => $cantidad,
                'costo_unitario' => round($costoUnitario, 4),
                'costo_total'    => round($cantidad * $costoUnitario, 4),
                'stock_anterior' => $cantActual,
                'stock_nuevo'    => $nuevaCant,
                'usuario_id'     => Auth::id(),
                'notas'          => $observacion,
                'created_at'     => now(),
            ]);
        });
    }

    public function egresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $docTipo,
        int $docId,
        ?string $docNumero = null,
        ?string $observacion = null,
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $docTipo, $docId, $docNumero, $observacion) {
            $saldo = DB::table('inventario_saldos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->first();

            $cantActual    = $saldo ? (float) $saldo->stock_actual : 0;
            $costoPromedio = $saldo ? (float) $saldo->costo_promedio  : 0;
            $nuevaCant     = max(0, $cantActual - $cantidad);

            DB::table('inventario_saldos')->upsert(
                [
                    'producto_id'    => $productoId,
                    'bodega_id'      => $bodegaId,
                    'stock_actual'   => $nuevaCant,
                    'costo_promedio' => $costoPromedio,
                    'updated_at'     => now(),
                ],
                ['producto_id', 'bodega_id'],
                ['stock_actual', 'updated_at']
            );

            $empresaId = DB::table('bodegas')->where('id', $bodegaId)->value('empresa_id');

            DB::table('inventario_movimientos')->insert([
                'empresa_id'     => $empresaId,
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'salida',
                'doc_tipo'       => strtoupper($docTipo),
                'doc_id'         => $docId,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costoPromedio,
                'costo_total'    => round($cantidad * $costoPromedio, 4),
                'stock_anterior' => $cantActual,
                'stock_nuevo'    => $nuevaCant,
                'usuario_id'     => Auth::id(),
                'notas'          => $observacion,
                'created_at'     => now(),
            ]);
        });
    }

    public function reservarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $documentoTipo = '',
        int $documentoId = 0
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $documentoTipo, $documentoId) {
            $saldo = DB::table('inventario_saldos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->first();

            $stockActual   = $saldo ? (float) $saldo->stock_actual : 0;
            $reservado     = $saldo ? (float) ($saldo->cantidad_reservada ?? 0) : 0;
            $costoPromedio = $saldo ? (float) $saldo->costo_promedio : 0;
            $disponible    = max(0, $stockActual - $reservado);

            if ($cantidad > $disponible) {
                throw new \RuntimeException(
                    "Stock insuficiente en bodega seleccionada. Disponible: {$disponible}, solicitado: {$cantidad}."
                );
            }

            DB::table('inventario_saldos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->increment('cantidad_reservada', $cantidad, ['updated_at' => now()]);

            if ($documentoTipo !== '') {
                $empresaId = DB::table('bodegas')->where('id', $bodegaId)->value('empresa_id');

                DB::table('inventario_movimientos')->insert([
                    'empresa_id'     => $empresaId,
                    'producto_id'    => $productoId,
                    'bodega_id'      => $bodegaId,
                    'tipo'           => 'reserva',
                    'doc_tipo'       => strtoupper($documentoTipo),
                    'doc_id'         => $documentoId,
                    'cantidad'       => $cantidad,
                    'costo_unitario' => $costoPromedio,
                    'costo_total'    => round($cantidad * $costoPromedio, 4),
                    'stock_anterior' => $stockActual,
                    'stock_nuevo'    => $stockActual,
                    'usuario_id'     => Auth::id(),
                    'notas'          => null,
                    'created_at'     => now(),
                ]);
            }
        });
    }

    public function liberarReserva(int $productoId, int $bodegaId, float $cantidad): void
    {
        DB::table('inventario_saldos')
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->decrement('cantidad_reservada', $cantidad, ['updated_at' => now()]);
    }

    public function confirmarSalida(int $productoId, int $bodegaId, string $docTipo, int $docId): void
    {
        DB::transaction(function () use ($productoId, $bodegaId, $docTipo, $docId) {
            $reserva = DB::table('inventario_movimientos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->where('tipo', 'reserva')
                ->where('doc_tipo', strtoupper($docTipo))
                ->where('doc_id', $docId)
                ->first();

            if (!$reserva) {
                throw new \RuntimeException(
                    "No se encontró la reserva de inventario para el documento {$docTipo} #{$docId}."
                );
            }

            $cantidad = (float) $reserva->cantidad;

            $saldo = DB::table('inventario_saldos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->first();

            $cantActual        = $saldo ? (float) $saldo->stock_actual : 0;
            $costoPromedio     = $saldo ? (float) $saldo->costo_promedio : 0;
            $cantidadReservada = $saldo ? (float) ($saldo->cantidad_reservada ?? 0) : 0;
            $nuevaCant         = max(0, $cantActual - $cantidad);
            $nuevaReservada    = max(0, $cantidadReservada - $cantidad);

            DB::table('inventario_saldos')
                ->where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->update([
                    'stock_actual'       => $nuevaCant,
                    'cantidad_reservada' => $nuevaReservada,
                    'updated_at'         => now(),
                ]);

            $empresaId = DB::table('bodegas')->where('id', $bodegaId)->value('empresa_id');

            DB::table('inventario_movimientos')->insert([
                'empresa_id'     => $empresaId,
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'salida',
                'doc_tipo'       => strtoupper($docTipo),
                'doc_id'         => $docId,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costoPromedio,
                'costo_total'    => round($cantidad * $costoPromedio, 4),
                'stock_anterior' => $cantActual,
                'stock_nuevo'    => $nuevaCant,
                'usuario_id'     => Auth::id(),
                'notas'          => 'Confirmación de salida de reserva',
                'created_at'     => now(),
            ]);
        });
    }

    public function getSaldoDisponible(int $productoId, int $bodegaId): float
    {
        $saldo = DB::table('inventario_saldos')
            ->where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        if (!$saldo) return 0;

        $reservado = isset($saldo->cantidad_reservada) ? (float) $saldo->cantidad_reservada : 0;
        return max(0, (float) $saldo->stock_actual - $reservado);
    }
}
