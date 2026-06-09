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
                ->firstOrFail();

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

    public function reservarStock(int $productoId, int $bodegaId, float $cantidad): void
    {
        InventarioMovimiento::create([
            'empresa_id'       => session('empresa_activa_id'),
            'producto_id'      => $productoId,
            'bodega_origen_id' => $bodegaId,
            'tipo_movimiento'  => 'reserva',
            'cantidad'         => $cantidad,
            'fecha'            => now()->toDateString(),
            'hora'             => now()->toTimeString(),
            'usuario_id'       => auth()->id(),
        ]);
    }

    public function liberarReserva(int $productoId, int $bodegaId, float $cantidad): void
    {
        InventarioMovimiento::where('producto_id', $productoId)
            ->where('bodega_origen_id', $bodegaId)
            ->where('tipo_movimiento', 'reserva')
            ->where('cantidad', $cantidad)
            ->latest('fecha')
            ->first()
            ?->delete();
    }

    public function getSaldoDisponible(int $productoId, int $bodegaId): float
    {
        return (float) (InventarioSaldo::where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->value('cantidad') ?? 0);
    }
}
