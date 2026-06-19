<?php

namespace App\Services;

use App\Models\InventarioSaldo;
use App\Models\InventarioMovimiento;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Support\Facades\DB;

class InventarioService implements InventarioServiceInterface
{
    public function ingresarStock(int $productoId, int $bodegaId, float $cantidad, float $costo, string $docTipo, int $docId): void
    {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $costo, $docTipo, $docId) {
            $saldo = InventarioSaldo::firstOrNew([
                'producto_id' => $productoId,
                'bodega_id'   => $bodegaId,
            ]);

            $cantAnterior  = (float) ($saldo->cantidad ?? 0);
            $costoAnterior = (float) ($saldo->costo_promedio ?? 0);
            $nuevaCantidad = $cantAnterior + $cantidad;

            $saldo->costo_promedio = $nuevaCantidad > 0
                ? (($cantAnterior * $costoAnterior) + ($cantidad * $costo)) / $nuevaCantidad
                : $costo;
            $saldo->cantidad   = $nuevaCantidad;
            $saldo->updated_at = now();
            $saldo->save();

            InventarioMovimiento::create([
                'empresa_id'        => session('empresa_activa_id'),
                'producto_id'       => $productoId,
                'bodega_destino_id' => $bodegaId,
                'tipo_movimiento'   => 'entrada',
                'documento_tipo'    => $docTipo,
                'documento_id'      => $docId,
                'cantidad'          => $cantidad,
                'costo_unitario'    => $costo,
                'costo_total'       => $cantidad * $costo,
                'fecha'             => now()->toDateString(),
                'hora'              => now()->toTimeString(),
                'usuario_id'        => auth()->id(),
            ]);
        });
    }

    public function egresarStock(int $productoId, int $bodegaId, float $cantidad, string $docTipo, int $docId): void
    {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $docTipo, $docId) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->first();

            if (!$saldo) {
                throw new \RuntimeException('Este producto no tiene stock registrado en esta bodega.');
            }

            if ($saldo->cantidad < $cantidad) {
                throw new \RuntimeException("Stock insuficiente. Disponible: {$saldo->cantidad}, solicitado: {$cantidad}.");
            }

            $saldo->cantidad   = $saldo->cantidad - $cantidad;
            $saldo->updated_at = now();
            $saldo->save();

            InventarioMovimiento::create([
                'empresa_id'       => session('empresa_activa_id'),
                'producto_id'      => $productoId,
                'bodega_origen_id' => $bodegaId,
                'tipo_movimiento'  => 'salida',
                'documento_tipo'   => $docTipo,
                'documento_id'     => $docId,
                'cantidad'         => $cantidad,
                'costo_unitario'   => $saldo->costo_promedio,
                'costo_total'      => $cantidad * $saldo->costo_promedio,
                'fecha'            => now()->toDateString(),
                'hora'             => now()->toTimeString(),
                'usuario_id'       => auth()->id(),
            ]);
        });
    }

    public function reservarStock(int $productoId, int $bodegaId, float $cantidad, string $docTipo, int $docId): void
    {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $docTipo, $docId) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->first();

            $disponible = $saldo ? ($saldo->cantidad - $saldo->cantidad_reservada) : 0;

            if ($disponible < $cantidad) {
                throw new \RuntimeException("Stock disponible insuficiente para reservar. Disponible: {$disponible}, solicitado: {$cantidad}.");
            }

            $saldo->cantidad_reservada = $saldo->cantidad_reservada + $cantidad;
            $saldo->updated_at = now();
            $saldo->save();

            InventarioMovimiento::create([
                'empresa_id'       => session('empresa_activa_id'),
                'producto_id'      => $productoId,
                'bodega_origen_id' => $bodegaId,
                'tipo_movimiento'  => 'reserva',
                'documento_tipo'   => $docTipo,
                'documento_id'     => $docId,
                'cantidad'         => $cantidad,
                'fecha'            => now()->toDateString(),
                'hora'             => now()->toTimeString(),
                'usuario_id'       => auth()->id(),
            ]);
        });
    }

    public function liberarReserva(int $productoId, int $bodegaId, string $docTipo, int $docId): void
    {
        $movimiento = InventarioMovimiento::where('producto_id', $productoId)
            ->where('bodega_origen_id', $bodegaId)
            ->where('tipo_movimiento', 'reserva')
            ->where('documento_tipo', $docTipo)
            ->where('documento_id', $docId)
            ->whereNull('liberado_at')
            ->first();

        if (!$movimiento) {
            throw new \RuntimeException("No existe una reserva activa para el documento {$docTipo} #{$docId} del producto #{$productoId} en bodega #{$bodegaId}.");
        }

        DB::transaction(function () use ($productoId, $bodegaId, $movimiento, $docTipo, $docId) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->firstOrFail();

            $saldo->cantidad_reservada = $saldo->cantidad_reservada - $movimiento->cantidad;
            $saldo->updated_at = now();
            $saldo->save();

            $movimiento->update(['liberado_at' => now()]);

            InventarioMovimiento::create([
                'empresa_id'       => session('empresa_activa_id'),
                'producto_id'      => $productoId,
                'bodega_origen_id' => $bodegaId,
                'tipo_movimiento'  => 'reserva_liberada',
                'documento_tipo'   => $docTipo,
                'documento_id'     => $docId,
                'cantidad'         => $movimiento->cantidad,
                'fecha'            => now()->toDateString(),
                'hora'             => now()->toTimeString(),
                'usuario_id'       => auth()->id(),
                'observacion'      => "Libera reserva movimiento #{$movimiento->id}",
            ]);
        });
    }

    public function confirmarSalida(int $productoId, int $bodegaId, string $docTipo, int $docId): void
    {
        $movimiento = InventarioMovimiento::where('producto_id', $productoId)
            ->where('bodega_origen_id', $bodegaId)
            ->where('tipo_movimiento', 'reserva')
            ->where('documento_tipo', $docTipo)
            ->where('documento_id', $docId)
            ->whereNull('liberado_at')
            ->first();

        if (!$movimiento) {
            throw new \RuntimeException("No existe una reserva activa para confirmar salida del documento {$docTipo} #{$docId} del producto #{$productoId} en bodega #{$bodegaId}.");
        }

        DB::transaction(function () use ($productoId, $bodegaId, $movimiento, $docTipo, $docId) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($saldo->cantidad < $movimiento->cantidad) {
                throw new \RuntimeException(
                    "Inconsistencia de inventario: stock físico ({$saldo->cantidad}) es menor que la reserva ({$movimiento->cantidad}) "
                    . "para producto #{$productoId} en bodega #{$bodegaId}."
                );
            }

            $saldo->cantidad           = $saldo->cantidad - $movimiento->cantidad;
            $saldo->cantidad_reservada = $saldo->cantidad_reservada - $movimiento->cantidad;
            $saldo->updated_at         = now();
            $saldo->save();

            $movimiento->update(['liberado_at' => now()]);

            InventarioMovimiento::create([
                'empresa_id'       => session('empresa_activa_id'),
                'producto_id'      => $productoId,
                'bodega_origen_id' => $bodegaId,
                'tipo_movimiento'  => 'salida',
                'documento_tipo'   => $docTipo,
                'documento_id'     => $docId,
                'cantidad'         => $movimiento->cantidad,
                'costo_unitario'   => $saldo->costo_promedio,
                'costo_total'      => $movimiento->cantidad * $saldo->costo_promedio,
                'fecha'            => now()->toDateString(),
                'hora'             => now()->toTimeString(),
                'usuario_id'       => auth()->id(),
                'observacion'      => "Confirma salida de reserva movimiento #{$movimiento->id}",
            ]);
        });
    }

    public function getSaldoDisponible(int $productoId, int $bodegaId): float
    {
        $saldo = InventarioSaldo::where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        if (!$saldo) {
            return 0;
        }

        return (float) ($saldo->cantidad - $saldo->cantidad_reservada);
    }
}
