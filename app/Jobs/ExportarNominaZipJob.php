<?php

namespace App\Jobs;

use App\Jobs\Concerns\ConstruyeUrlDescargaExportacion;
use App\Models\Nomina;
use App\Models\Notificacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genera el ZIP con el rol de pago (PDF) de cada colaborador de una nómina
 * en segundo plano — antes se generaba de forma síncrona dentro del propio
 * request HTTP (NominaController::pdfMasivo original), bloqueando la UI
 * mientras DomPDF renderizaba un PDF por colaborador uno por uno. Es el
 * único export que sigue en segundo plano (requerimiento explícito del
 * cliente) tras revertir el resto de exportaciones PDF/Excel a síncronas.
 */
class ExportarNominaZipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ConstruyeUrlDescargaExportacion;

    public const CARPETA = 'exportaciones-nomina';

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly int $nominaId,
        public readonly int $usuarioId,
        public readonly string $baseUrl,
    ) {}

    public function handle(): void
    {
        $nomina = Nomina::with(['empresa', 'detalles.colaborador'])->findOrFail($this->nominaId);

        $tmpDir = sys_get_temp_dir() . '/nomina_' . $nomina->id . '_' . Str::random(8);
        mkdir($tmpDir, 0755, true);

        foreach ($nomina->detalles as $det) {
            $pdf = Pdf::loadView('pdf.nomina-individual', [
                'nomina'  => $nomina->append('periodo_label'),
                'detalle' => $det,
                'empresa' => $nomina->empresa,
            ])->setPaper('a4', 'portrait');

            $nombre = str_replace(' ', '-', $det->colaborador->apellidos);
            file_put_contents("{$tmpDir}/{$nombre}-{$det->id}.pdf", $pdf->output());
        }

        $nombreArchivo = "{$this->usuarioId}_nomina-{$nomina->anio}-{$nomina->mes}_" . Str::random(8) . '.zip';
        $rutaFinal     = Storage::disk('local')->path(self::CARPETA . "/{$nombreArchivo}");
        Storage::disk('local')->makeDirectory(self::CARPETA);

        $zip = new \ZipArchive();
        $zip->open($rutaFinal, \ZipArchive::CREATE);
        foreach (glob("{$tmpDir}/*.pdf") as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        array_map('unlink', glob("{$tmpDir}/*.pdf"));
        rmdir($tmpDir);

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_nomina',
            'titulo'     => 'ZIP de roles de pago listo',
            'mensaje'    => "El ZIP con los roles de pago de {$nomina->detalles->count()} colaborador(es) ya está listo para descargar (disponible por 48 horas).",
            'icono'      => 'download',
            'url'        => $this->urlDescarga($this->baseUrl, 'rrhh.nomina.exportacion.descargar', ['archivo' => $nombreArchivo]),
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_nomina_error',
            'titulo'     => 'No se pudo generar el ZIP de roles de pago',
            'mensaje'    => 'Hubo un error generando el ZIP de la nómina. Intenta de nuevo.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }
}
