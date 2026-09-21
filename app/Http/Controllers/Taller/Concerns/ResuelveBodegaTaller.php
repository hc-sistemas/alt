<?php

namespace App\Http\Controllers\Taller\Concerns;

use App\Models\Bodega;

trait ResuelveBodegaTaller
{
    /** Id de la bodega de Taller resuelto en tiempo de ejecución y cacheado en el request. */
    private ?int $bodegaTallerId = null;

    /**
     * Resuelve la bodega de Taller de la empresa activa: la bodega activa con
     * tipo "taller" (se crea desde Inventario → Configuración → Bodegas). No se
     * hardcodea el id porque difiere entre empresas y entornos. Como respaldo
     * se acepta una bodega activa llamada "Taller" o "Bodega Taller".
     */
    private function bodegaTallerId(): int
    {
        if ($this->bodegaTallerId !== null) {
            return $this->bodegaTallerId;
        }

        $base = Bodega::where('empresa_id', session('empresa_activa_id'))
            ->where('estado', true)
            ->orderBy('id');

        $bodega = (clone $base)->where('tipo', 'taller')->first()
            ?? (clone $base)->whereIn('nombre', ['Taller', 'Bodega Taller'])->first();

        if (!$bodega) {
            throw new \RuntimeException(
                'No hay una bodega de tipo Taller activa para esta empresa. Créela en Inventario → Configuración → Bodegas.'
            );
        }

        return $this->bodegaTallerId = (int) $bodega->id;
    }
}
