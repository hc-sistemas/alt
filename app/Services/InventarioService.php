<?php

namespace App\Services;

use App\Models\InventarioMovimiento;
use App\Models\InventarioSaldo;
use App\Services\Contracts\InventarioServiceInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventarioService implements InventarioServiceInterface
{
    public function ingresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        float $costoUnitario,
        string $docTipo,
        int $docId,
        ?string $notas = null
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $costoUnitario, $docTipo, $docId, $notas) {
            $saldo = InventarioSaldo::firstOrCreate(
                ['producto_id' => $productoId, 'bodega_id' => $bodegaId],
                ['stock_actual' => 0, 'stock_reservado' => 0, 'costo_promedio' => 0]
            );

            $stockAnterior  = (float) $saldo->stock_actual;
            $totalAnterior  = $stockAnterior + $cantidad;

            // Costo promedio ponderado
            $nuevoPromedio = $totalAnterior > 0
                ? (($stockAnterior * (float) $saldo->costo_promedio) + ($cantidad * $costoUnitario)) / $totalAnterior
                : (float) $saldo->costo_promedio;

            $saldo->stock_actual    = $stockAnterior + $cantidad;
            $saldo->costo_promedio  = $nuevoPromedio;
            $saldo->updated_at      = now();
            $saldo->save();

            InventarioMovimiento::create([
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'entrada',
                'doc_tipo'       => $docTipo,
                'doc_id'         => $docId,
                'cantidad'       => $cantidad,
                'costo_unitario' => $costoUnitario,
                'costo_total'    => $cantidad * $costoUnitario,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => (float) $saldo->stock_actual,
                'usuario_id'     => Auth::id(),
                'empresa_id'     => session('empresa_activa_id'),
                'notas'          => $notas,
            ]);
        });
    }

    public function egresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $docTipo,
        int $docId,
        ?string $notas = null
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad, $docTipo, $docId, $notas) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->first();

            $disponible = $saldo ? $saldo->stockDisponible() : 0.0;

            if (!$saldo || $disponible < $cantidad) {
                throw new \Exception(
                    "Stock insuficiente: disponible {$disponible}, solicitado {$cantidad}"
                );
            }

            $stockAnterior       = (float) $saldo->stock_actual;
            $saldo->stock_actual = $stockAnterior - $cantidad;
            $saldo->updated_at   = now();
            $saldo->save();

            InventarioMovimiento::create([
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'salida',
                'doc_tipo'       => $docTipo,
                'doc_id'         => $docId,
                'cantidad'       => $cantidad,
                'costo_unitario' => (float) $saldo->costo_promedio,
                'costo_total'    => $cantidad * (float) $saldo->costo_promedio,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => (float) $saldo->stock_actual,
                'usuario_id'     => Auth::id(),
                'empresa_id'     => session('empresa_activa_id'),
                'notas'          => $notas,
            ]);
        });
    }

    public function reservarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->first();

            $disponible = $saldo ? $saldo->stockDisponible() : 0.0;

            if (!$saldo || $disponible < $cantidad) {
                throw new \Exception("Stock insuficiente para reservar");
            }

            $stockAnterior          = (float) $saldo->stock_actual;
            $saldo->stock_reservado = (float) $saldo->stock_reservado + $cantidad;
            $saldo->updated_at      = now();
            $saldo->save();

            InventarioMovimiento::create([
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'reserva',
                'doc_tipo'       => null,
                'doc_id'         => null,
                'cantidad'       => $cantidad,
                'costo_unitario' => null,
                'costo_total'    => null,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockAnterior,
                'usuario_id'     => Auth::id(),
                'empresa_id'     => session('empresa_activa_id'),
                'notas'          => null,
            ]);
        });
    }

    public function liberarReserva(
        int $productoId,
        int $bodegaId,
        float $cantidad
    ): void {
        DB::transaction(function () use ($productoId, $bodegaId, $cantidad) {
            $saldo = InventarioSaldo::where('producto_id', $productoId)
                ->where('bodega_id', $bodegaId)
                ->lockForUpdate()
                ->first();

            // Liberación silenciosa si no existe registro
            if (!$saldo) {
                return;
            }

            $stockAnterior          = (float) $saldo->stock_actual;
            $saldo->stock_reservado = max(0.0, (float) $saldo->stock_reservado - $cantidad);
            $saldo->updated_at      = now();
            $saldo->save();

            InventarioMovimiento::create([
                'producto_id'    => $productoId,
                'bodega_id'      => $bodegaId,
                'tipo'           => 'liberacion',
                'doc_tipo'       => null,
                'doc_id'         => null,
                'cantidad'       => $cantidad,
                'costo_unitario' => null,
                'costo_total'    => null,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockAnterior,
                'usuario_id'     => Auth::id(),
                'empresa_id'     => session('empresa_activa_id'),
                'notas'          => null,
            ]);
        });
    }

    public function getSaldoDisponible(int $productoId, int $bodegaId): float
    {
        $saldo = InventarioSaldo::where('producto_id', $productoId)
            ->where('bodega_id', $bodegaId)
            ->first();

        return $saldo ? $saldo->stockDisponible() : 0.0;
    }
}
