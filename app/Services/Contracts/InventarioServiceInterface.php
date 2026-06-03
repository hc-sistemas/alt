<?php

namespace App\Services\Contracts;

interface InventarioServiceInterface
{
    /**
     * Registra entrada de stock (compra, importación, devolución recibida).
     * Actualiza stock_actual y recalcula costo_promedio ponderado.
     */
    public function ingresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        float $costoUnitario,
        string $docTipo,
        int $docId,
        ?string $notas = null
    ): void;

    /**
     * Registra salida de stock (factura, nota de crédito enviada).
     * Lanza excepción si stock_disponible < cantidad.
     */
    public function egresarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad,
        string $docTipo,
        int $docId,
        ?string $notas = null
    ): void;

    /**
     * Aparta stock para una proforma/reserva.
     * Incrementa stock_reservado sin tocar stock_actual.
     * Lanza excepción si stock_disponible < cantidad.
     */
    public function reservarStock(
        int $productoId,
        int $bodegaId,
        float $cantidad
    ): void;

    /**
     * Libera una reserva previa (proforma vencida, anulada).
     * Decrementa stock_reservado.
     */
    public function liberarReserva(
        int $productoId,
        int $bodegaId,
        float $cantidad
    ): void;

    /**
     * Retorna el stock disponible actual (stock_actual - stock_reservado).
     * Retorna 0.0 si no existe registro en inventario_saldos.
     */
    public function getSaldoDisponible(int $productoId, int $bodegaId): float;
}
