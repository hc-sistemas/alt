<?php

namespace App\Jobs;

use App\Models\AsientoDetalle;
use App\Models\Empresa;
use App\Models\Notificacion;
use App\Models\PlanCuenta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Genera el PDF completo del Mayor Contable de una cuenta en segundo
 * plano — sin el límite de ReporteContableController::MAX_FILAS_MAYOR que
 * existe solo para el camino síncrono. Mismo patrón que
 * ExportarLibroDiarioJob.
 */
class ExportarMayorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Mismo margen que ExportarLibroDiarioJob: el Mayor de una cuenta muy
    // activa tampoco tiene un tope natural de filas (depende del rango de
    // fechas que pida el usuario), así que se le da el mismo timeout
    // holgado en vez del de 600s usado en los Jobs con volumen acotado
    // por su propio módulo (Compras/Proveedores/CxP).
    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $usuarioId,
        public readonly int $cuentaId,
        public readonly array $filtros,
        public readonly string $baseUrl,
    ) {}

    public function handle(): void
    {
        ini_set('memory_limit', '2560M');

        $cuenta  = PlanCuenta::findOrFail($this->cuentaId);
        $empresa = Empresa::find($this->empresaId);

        $query = AsientoDetalle::with(['asiento'])
            ->where('cuenta_id', $cuenta->id)
            ->whereHas('asiento', fn($q) =>
                $q->where('empresa_id', $this->empresaId)->where('estado', 1)
            );

        if (!empty($this->filtros['fecha_desde'])) {
            $query->whereHas('asiento', fn($q) => $q->where('fecha', '>=', $this->filtros['fecha_desde']));
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->whereHas('asiento', fn($q) => $q->where('fecha', '<=', $this->filtros['fecha_hasta']));
        }

        $detalles   = $query->orderBy('id')->get();
        $totalDebe  = $detalles->sum('debe');
        $totalHaber = $detalles->sum('haber');
        $saldo      = $totalDebe - $totalHaber;

        $pdf = Pdf::loadView('pdf.mayor-cuenta', compact('cuenta', 'detalles', 'totalDebe', 'totalHaber', 'saldo', 'empresa'))
            ->setPaper('a4', 'portrait');

        $nombreArchivo = "{$this->usuarioId}_mayor-{$cuenta->codigo}-" . now()->format('Y-m-d') . '_' . Str::random(8) . '.pdf';
        Storage::disk('local')->put(ExportarLibroDiarioJob::CARPETA . "/{$nombreArchivo}", $pdf->output());

        $rutaRelativa = route('contabilidad.reportes.exportacion.descargar', ['archivo' => $nombreArchivo], false);

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_reportes_contables',
            'titulo'     => 'Mayor Contable listo',
            'mensaje'    => "Tu Mayor Contable de la cuenta {$cuenta->codigo} ya está listo para descargar (disponible por 48 horas).",
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
            'titulo'     => 'No se pudo generar tu Mayor Contable',
            'mensaje'    => 'Hubo un error generando el Mayor Contable. Intenta con un rango de fechas más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }
}
