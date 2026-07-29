<?php

namespace App\Jobs;

use App\Models\Compra;
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
 * Genera el PDF completo de Facturas de Compra en segundo plano — sin el
 * límite de CompraController::MAX_FILAS_PDF que existe solo para el camino
 * síncrono (descarga inmediata). Aquí puede tardar los segundos/minutos que
 * necesite: no bloquea al usuario ni al servidor web, corre en el worker de
 * colas (ver CompraController::pdfSegundoPlano() y CLAUDE.md para el
 * requisito de queue:work). Mismo patrón que ExportarAsientosJob.
 */
class ExportarComprasJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CARPETA = 'exportaciones-compras';

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $usuarioId,
        public readonly array $filtros,
    ) {}

    public function handle(): void
    {
        // Corre en el worker de colas, no en el ciclo request/response — puede
        // permitirse más memoria que el límite web por defecto (512M) sin
        // afectar al servidor de cara al usuario. DomPDF agota memory_limit
        // renderizando tablas grandes (confirmado: ~1200 filas ya revientan
        // los 512MB en Cellmap::resolve_border() — ver CompraController::pdf()
        // y el commit que agregó MAX_FILAS_PDF). El costo NO es lineal: quitar
        // los bordes por celda no lo mejora (se probó), y ni siquiera 1536M
        // alcanza para las ~2239 facturas reales de la empresa activa —
        // medido empíricamente que el pico real es ~1754MB (2239 filas, 67s).
        // 2560M da margen real sobre ese pico medido, no un valor arbitrario.
        ini_set('memory_limit', '2560M');

        $nombreArchivo = "{$this->usuarioId}_" . now()->format('YmdHis') . '_' . Str::random(8) . '.pdf';
        $ruta = self::CARPETA . "/{$nombreArchivo}";

        Storage::disk('local')->put($ruta, $this->generarPdf()->output());

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_compras',
            'titulo'     => 'Reporte de Facturas de Compra listo',
            'mensaje'    => 'Tu PDF de Facturas de Compra ya está listo para descargar (disponible por 48 horas).',
            'icono'      => 'download',
            'url'        => route('compras.facturas.exportacion.descargar', ['archivo' => $nombreArchivo]),
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_compras_error',
            'titulo'     => 'No se pudo generar tu reporte',
            'mensaje'    => 'Hubo un error generando el PDF de Facturas de Compra. Intenta con un filtro más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }

    // Misma lógica de filtrado y misma vista que CompraController::pdf(), sin
    // el candado MAX_FILAS_PDF (aquí ya no aplica: es el camino de segundo
    // plano precisamente para cuando se supera ese límite).
    private function generarPdf()
    {
        $query = Compra::with('proveedor')->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha_emision', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha_emision', '<=', $this->filtros['fecha_hasta']);
        }

        $compras = $query->orderByDesc('fecha_emision')->get();
        $empresa = Empresa::find($this->empresaId);

        return Pdf::loadView('pdf.compras', compact('compras', 'empresa'))->setPaper('a4', 'landscape');
    }
}
