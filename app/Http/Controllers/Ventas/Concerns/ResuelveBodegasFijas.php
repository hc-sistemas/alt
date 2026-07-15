<?php

namespace App\Http\Controllers\Ventas\Concerns;

use App\Models\Bodega;

trait ResuelveBodegasFijas
{
    /** Id de la Bodega Principal UIO resuelto por nombre y cacheado en el request. */
    private ?int $bodegaPrincipalId = null;

    /** Id de la Bodega Reservas resuelto por nombre y cacheado en el request. */
    private ?int $bodegaReservasId = null;

    /**
     * Resuelve el id de la "Bodega Principal UIO" por nombre en tiempo de
     * ejecución (no se hardcodea el id, que puede diferir entre entornos).
     * El resultado se cachea dentro del mismo request.
     */
    private function bodegaPrincipalId(): int
    {
        if ($this->bodegaPrincipalId !== null) {
            return $this->bodegaPrincipalId;
        }

        $bodega = Bodega::where('nombre', 'Bodega Principal UIO')
            ->where('estado', true)
            ->first();

        if (!$bodega) {
            throw new \RuntimeException('No se encontró la Bodega Principal UIO configurada.');
        }

        return $this->bodegaPrincipalId = (int) $bodega->id;
    }

    /**
     * Resuelve el id de la "Bodega Reservas" por nombre en tiempo de
     * ejecución. Mismo criterio que bodegaPrincipalId().
     */
    private function bodegaReservasId(): int
    {
        if ($this->bodegaReservasId !== null) {
            return $this->bodegaReservasId;
        }

        $bodega = Bodega::where('nombre', 'Bodega Reservas')
            ->where('estado', true)
            ->first();

        if (!$bodega) {
            throw new \RuntimeException('No se encontró la Bodega Reservas configurada.');
        }

        return $this->bodegaReservasId = (int) $bodega->id;
    }
}
