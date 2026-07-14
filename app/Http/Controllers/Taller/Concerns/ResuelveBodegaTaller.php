<?php

namespace App\Http\Controllers\Taller\Concerns;

use App\Models\Bodega;

trait ResuelveBodegaTaller
{
    /** Id de la Bodega Taller resuelto por nombre y cacheado en el request. */
    private ?int $bodegaTallerId = null;

    /**
     * Resuelve el id de la "Bodega Taller" por nombre en tiempo de ejecución
     * (no se hardcodea el id, que puede diferir entre entornos). El
     * resultado se cachea dentro del mismo request.
     */
    private function bodegaTallerId(): int
    {
        if ($this->bodegaTallerId !== null) {
            return $this->bodegaTallerId;
        }

        $bodega = Bodega::where('nombre', 'Bodega Taller')
            ->where('estado', true)
            ->first();

        if (!$bodega) {
            throw new \RuntimeException('No se encontró la Bodega Taller configurada.');
        }

        return $this->bodegaTallerId = (int) $bodega->id;
    }
}
