<?php

namespace App\Jobs;

use App\Models\AsientoContable;
use App\Models\Empresa;
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
 * Genera el PDF completo del Libro Diario en segundo plano — sin el
 * límite de ReporteContableController::MAX_FILAS_LIBRO_DIARIO que existe
 * solo para el camino síncrono. Mismo patrón que ExportarComprasJob /
 * ExportarProveedoresJob / ExportarCxPJob.
 */
class ExportarLibroDiarioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CARPETA = 'exportaciones-reportes-contables';

    // A diferencia de los otros Jobs de exportación (Compras/Proveedores/
    // CxP, con un tope natural de filas de su propio módulo), el Libro
    // Diario no tiene límite superior real de rango de fechas — un año
    // completo con datos de volumen (6,343 líneas) tardó ~28 minutos en
    // generarse aquí (medido real, no estimado). 1800s da margen sobre
    // ese peor caso medido sin dejarlo correr indefinidamente.
    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $usuarioId,
        public readonly array $filtros,
        public readonly string $baseUrl,
    ) {}

    public function handle(): void
    {
        // DomPDF renderizando la tabla del Libro Diario es lo que consume
        // memoria en rangos amplios (SQL es rapidísimo — ver el
        // diagnóstico en ReporteContableController), no el query. Mismo
        // margen defensivo que el resto de Jobs de exportación.
        ini_set('memory_limit', '2560M');

        $query = AsientoContable::with(['ejercicio','creadoPor','detalles.cuenta'])
            ->where('empresa_id', $this->empresaId)
            ->where('estado', 1);

        if (!empty($this->filtros['ejercicio_id'])) {
            $query->where('ejercicio_id', $this->filtros['ejercicio_id']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $this->filtros['fecha_hasta']);
        }

        $asientos   = $query->orderBy('fecha')->orderBy('id')->get();
        $empresa    = Empresa::find($this->empresaId);
        $totalDebe  = $asientos->sum('total_debe');
        $totalHaber = $asientos->sum('total_haber');

        $pdf = Pdf::loadView('pdf.libro-diario', compact('asientos', 'empresa', 'totalDebe', 'totalHaber'))
            ->setPaper('a4', 'landscape');

        $nombreArchivo = "{$this->usuarioId}_libro-diario-" . now()->format('Y-m-d') . '_' . Str::random(8) . '.pdf';
        Storage::disk('local')->put(ExportarLibroDiarioJob::CARPETA . "/{$nombreArchivo}", $pdf->output());

        $rutaRelativa = route('contabilidad.reportes.exportacion.descargar', ['archivo' => $nombreArchivo], false);

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_reportes_contables',
            'titulo'     => 'Libro Diario listo',
            'mensaje'    => 'Tu Libro Diario ya está listo para descargar (disponible por 48 horas).',
            'icono'      => 'download',
            'url'        => $this->baseUrl . $rutaRelativa,
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_reportes_contables_error',
            'titulo'     => 'No se pudo generar tu Libro Diario',
            'mensaje'    => 'Hubo un error generando el Libro Diario. Intenta con un período más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }
}
