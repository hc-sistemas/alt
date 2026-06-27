<?php

namespace App\Services\Contracts;

interface InventarioServiceInterface
{
    public function ingresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        float $costoUnitario,
        string $docTipo,
        int $docId,
        ?string $docNumero = null,
        ?string $observacion = null,
    ): void;

    public function egresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $docTipo,
        int $docId,
        ?string $docNumero = null,
        ?string $observacion = null,
    ): void;

    public function reservarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $documentoTipo = '',
        int $documentoId = 0
    ): void;

    public function liberarReserva(
        int $productoId,
        int $bodegaId,
        float $cantidad
    ): void;

    public function getSaldoDisponible(int $productoId, int $bodegaId): float;
}
