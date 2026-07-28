<?php

namespace App\Jobs;

use App\Exports\AsientosExport;
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
use Maatwebsite\Excel\Facades\Excel;

/**
 * Genera el Excel o PDF completo de Asientos Contables en segundo plano —
 * sin el límite de AsientosDetalleSheet::MAX_FILAS_DETALLE que existe solo
 * para el camino síncrono (descarga inmediata). Aquí puede tardar los
 * segundos/minutos que necesite: no bloquea al usuario ni al servidor web,
 * corre en el worker de colas (ver AsientoContableController::
 * exportarSegundoPlano() y CLAUDE.md para el requisito de queue:work).
 */
class ExportarAsientosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CARPETA = 'exportaciones-asientos';

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $usuarioId,
        public readonly array $filtros,
        public readonly string $formato, // 'excel' | 'pdf'
    ) {}

    public function handle(): void
    {
        // Corre en el worker de colas, no en el ciclo request/response — puede
        // permitirse más memoria que el límite web por defecto (512M) sin
        // afectar al servidor de cara al usuario. Defensa adicional a la
        // eliminación del eager-load innecesario (creadoPor/detalles.cuenta)
        // que era la causa real del agotamiento de memoria en dompdf.
        ini_set('memory_limit', '1536M');

        $extension = $this->formato === 'excel' ? 'xlsx' : 'pdf';
        // Prefijo usuario_id_ para que descargarExportacion() pueda validar
        // dueño sin necesitar una tabla nueva de "exportaciones".
        $nombreArchivo = "{$this->usuarioId}_" . now()->format('YmdHis') . '_' . Str::random(8) . ".{$extension}";
        $ruta = self::CARPETA . "/{$nombreArchivo}";

        if ($this->formato === 'excel') {
            Excel::store(new AsientosExport($this->empresaId, $this->filtros), $ruta, 'local');
        } else {
            Storage::disk('local')->put($ruta, $this->generarPdf()->output());
        }

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_asientos',
            'titulo'     => 'Exportación de Asientos lista',
            'mensaje'    => 'Tu ' . ($this->formato === 'excel' ? 'Excel' : 'PDF') .
                ' de Asientos Contables ya está listo para descargar (disponible por 48 horas).',
            'icono'      => 'download',
            'url'        => route('contabilidad.asientos.exportacion.descargar', ['archivo' => $nombreArchivo]),
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_asientos_error',
            'titulo'     => 'No se pudo generar tu exportación',
            'mensaje'    => 'Hubo un error generando el ' . ($this->formato === 'excel' ? 'Excel' : 'PDF') .
                ' de Asientos Contables. Intenta con un filtro más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }

    // Misma lógica de filtrado que AsientoContableController::reportePdf(), sin
    // el candado de "al menos un filtro" (aquí ya no aplica: es el camino de
    // segundo plano precisamente para cuando no hay filtro o es muy amplio).
    private function generarPdf()
    {
        // Mismo motivo que en AsientoContableController::reportePdf(): la vista
        // solo usa $asiento->ejercicio — cargar creadoPor/detalles.cuenta aquí
        // agotó los 512MB de memory_limit de PHP con solo ~1,100 asientos
        // (confirmado con queue:work real), sin que la vista los use nunca.
        $query = AsientoContable::with(['ejercicio'])
            ->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['ejercicio_id'])) {
            $query->where('ejercicio_id', $this->filtros['ejercicio_id']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $this->filtros['fecha_hasta']);
        }
        if (!empty($this->filtros['tipo'])) {
            $query->where('es_automatico', $this->filtros['tipo'] === 'automatico');
        }
        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado'] === 'activo' ? 1 : 0);
        }

        $asientos = $query->orderByDesc('fecha')->get();
        $empresa  = Empresa::find($this->empresaId);

        $totalDebe  = $asientos->sum('total_debe');
        $totalHaber = $asientos->sum('total_haber');

        return Pdf::loadView(
            'pdf.asientos-reporte',
            compact('asientos', 'empresa', 'totalDebe', 'totalHaber')
        )->setPaper('a4', 'landscape');
    }
}
