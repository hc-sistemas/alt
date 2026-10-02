<?php

namespace App\Http\Controllers\Ventas\Concerns;

use App\Models\Bodega;

trait ResuelveBodegasFijas
{
    /** Id de la bodega Principal resuelto para la empresa activa y cacheado en el request. */
    private ?int $bodegaPrincipalId = null;

    /** Id de la bodega Reservas resuelto para la empresa activa y cacheado en el request. */
    private ?int $bodegaReservasId = null;

    /**
     * Resuelve la bodega principal de la empresa activa: la bodega activa con
     * tipo "general" (no se hardcodea el id ni un nombre fijo, que pueden
     * diferir entre empresas y entornos — ver CHECKLIST_ERRORES_COMPLICACIONES.md,
     * ítem A3). Como respaldo se acepta una bodega activa llamada "Principal"
     * o "Bodega Principal UIO" (nombre legado). Si una empresa tiene más de
     * una bodega tipo "general" (p. ej. Principal y Muestra), se toma la de
     * menor id para que el resultado sea estable.
     */
    private function bodegaPrincipalId(): int
    {
        if ($this->bodegaPrincipalId !== null) {
            return $this->bodegaPrincipalId;
        }

        $base = Bodega::where('empresa_id', session('empresa_activa_id'))
            ->where('estado', true)
            ->orderBy('id');

        $bodega = (clone $base)->where('tipo', 'general')->first()
            ?? (clone $base)->whereIn('nombre', ['Principal', 'Bodega Principal UIO'])->first();

        if (!$bodega) {
            throw new \RuntimeException(
                'No hay una bodega principal activa para esta empresa. Créela en Inventario → Configuración → Bodegas.'
            );
        }

        return $this->bodegaPrincipalId = (int) $bodega->id;
    }

    /**
     * Resuelve la bodega de Reservas de la empresa activa. Mismo criterio que
     * bodegaPrincipalId(): por tipo "reserva", con respaldo por nombre.
     */
    private function bodegaReservasId(): int
    {
        if ($this->bodegaReservasId !== null) {
            return $this->bodegaReservasId;
        }

        $base = Bodega::where('empresa_id', session('empresa_activa_id'))
            ->where('estado', true)
            ->orderBy('id');

        $bodega = (clone $base)->where('tipo', 'reserva')->first()
            ?? (clone $base)->whereIn('nombre', ['Reservas', 'Bodega Reservas'])->first();

        if (!$bodega) {
            throw new \RuntimeException(
                'No hay una bodega de Reservas activa para esta empresa. Créela en Inventario → Configuración → Bodegas.'
            );
        }

        return $this->bodegaReservasId = (int) $bodega->id;
    }
}
