<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * Borra los ZIP generados por ExportarNominaZipJob con más de 48 horas.
 * Mismo patrón que LimpiarExportacionesReportesContablesJob/Compras/
 * Proveedores/CxP.
 */
class LimpiarExportacionesNominaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $disk    = Storage::disk('local');
        $carpeta = ExportarNominaZipJob::CARPETA;

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
