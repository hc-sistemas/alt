<?php

namespace App\Jobs;

use App\Models\Empresa;
use App\Models\MovimientoBancario;
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
 * Genera el PDF completo de Movimientos Bancarios en segundo plano — sin el
 * límite de MovimientoBancarioController::MAX_FILAS_EXPORT que existe solo
 * para el camino síncrono (descarga/stream inmediato). Mismo patrón que
 * ExportarCxPJob / ExportarAsientosJob / ExportarComprasJob.
 */
class ExportarMovimientosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CARPETA = 'exportaciones-movimientos';

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $usuarioId,
        public readonly array $filtros,
        public readonly string $baseUrl, // host real de la request que disparó el export (ver MovimientoBancarioController::exportarSegundoPlano)
    ) {}

    public function handle(): void
    {
        // Corre en el worker de colas, no en el ciclo request/response — mismo
        // margen defensivo que ExportarCxPJob/ExportarComprasJob: DomPDF necesita
        // bastante más que 512M en tablas grandes.
        ini_set('memory_limit', '2560M');

        $nombreArchivo = "{$this->usuarioId}_" . now()->format('YmdHis') . '_' . Str::random(8) . '.pdf';
        $ruta = self::CARPETA . "/{$nombreArchivo}";

        Storage::disk('local')->put($ruta, $this->generarPdf()->output());

        // URL absoluta armada a mano con el host real capturado en el controller
        // (getSchemeAndHttpHost()), NO con route() a secas — dentro de un Job no
        // hay request activa, así que route() cae al host de config('app.url'),
        // que puede no coincidir con el que realmente sirvió la petición (mismo
        // bug que rompió antes la descarga de Excel de Asientos).
        $rutaRelativa = route('bancos.movimientos.exportacion.descargar', ['archivo' => $nombreArchivo], false);

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_movimientos',
            'titulo'     => 'Exportación de Movimientos Bancarios lista',
            'mensaje'    => 'Tu PDF de Movimientos Bancarios ya está listo para descargar (disponible por 48 horas).',
            'icono'      => 'download',
            'url'        => $this->baseUrl . $rutaRelativa,
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_movimientos_error',
            'titulo'     => 'No se pudo generar tu exportación',
            'mensaje'    => 'Hubo un error generando el PDF de Movimientos Bancarios. Intenta con un filtro más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }

    // Misma lógica de filtrado que MovimientoBancarioController::queryFiltrada(),
    // sin el candado MAX_FILAS_EXPORT (aquí ya no aplica: es el camino de segundo
    // plano precisamente para cuando se supera ese límite).
    private function generarPdf()
    {
        $query = MovimientoBancario::with(['bancoCaja', 'cuentaContrapartida'])
            ->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['banco_caja_id'])) {
            $query->where('banco_caja_id', $this->filtros['banco_caja_id']);
        }
        if (!empty($this->filtros['tipo'])) {
            $query->where('tipo', $this->filtros['tipo']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $this->filtros['fecha_hasta']);
        }
        if (!empty($this->filtros['centro_costo_id'])) {
            $query->where('centro_costo_id', $this->filtros['centro_costo_id']);
        }
        if (!empty($this->filtros['persona_id']) && !empty($this->filtros['persona_tipo'])) {
            $query->where('persona_tipo', $this->filtros['persona_tipo'])
                  ->where('persona_id', $this->filtros['persona_id']);
        }
        if (!empty($this->filtros['buscar'])) {
            $q = $this->filtros['buscar'];
            $query->where(fn($qb) =>
                $qb->where('descripcion',    'ilike', "%{$q}%")
                   ->orWhere('beneficiario', 'ilike', "%{$q}%")
                   ->orWhere('num_documento','ilike', "%{$q}%")
            );
        }

        $movimientos = (clone $query)->orderByDesc('fecha')->orderByDesc('id')->get();

        $totalIngresos = (clone $query)->where('tipo', 'ingreso')->where('anulado', false)->sum('monto');
        $totalEgresos  = (clone $query)->where('tipo', 'egreso')->where('anulado', false)->sum('monto');

        $empresa = Empresa::find($this->empresaId);

        return Pdf::loadView('pdf.bancos-movimientos', compact('movimientos', 'empresa', 'totalIngresos', 'totalEgresos'))
            ->setPaper('a4');
    }
}
