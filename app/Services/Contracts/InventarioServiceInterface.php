<?php

namespace App\Services\Contracts;

interface InventarioServiceInterface
{
    public function ingresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        float $costo,
        string $docTipo,
        int $docId
    ): void;

    public function egresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $docTipo,
        int $docId
    ): void;

    public function reservarStock(int $productoId, int $bodegaId, float $cantidad): void;

    public function liberarReserva(int $productoId, int $bodegaId, float $cantidad): void;

    public function getSaldoDisponible(int $productoId, int $bodegaId): float;
}
