<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los archivos generados por ExportarCxPJob con más de 48 horas, para
 * no acumular basura indefinidamente en storage/app/private/. Mismo patrón
 * que LimpiarExportacionesAsientosJob/Compras/Proveedores.
 */
class LimpiarExportacionesCxPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $disk    = Storage::disk('local');
        $carpeta = ExportarCxPJob::CARPETA;

        if (!$disk->exists($carpeta)) {
            return;
        }

        $limite = now()->subHours(48)->timestamp;

        foreach ($disk->files($carpeta) as $archivo) {
            if ($disk->lastModified($archivo) < $limite) {
                $disk->delete($archivo);
            }
        }
    }
}
