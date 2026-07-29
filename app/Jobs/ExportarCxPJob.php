<?php

namespace App\Jobs;

use App\Exports\CxPExport;
use App\Models\CuentaPagar;
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
 * Genera el Excel o PDF completo de Cuentas por Pagar en segundo plano — sin
 * el límite de CuentaPagarController::MAX_FILAS_EXPORT que existe solo para
 * el camino síncrono (descarga inmediata). Mismo patrón que
 * ExportarAsientosJob / ExportarComprasJob / ExportarProveedoresJob.
 */
class ExportarCxPJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CARPETA = 'exportaciones-cxp';

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(
        public readonly int $empresaId,
        public readonly int $usuarioId,
        public readonly array $filtros,
        public readonly string $formato, // 'excel' | 'pdf'
        public readonly string $baseUrl, // host real de la request que disparó el export (ver CuentaPagarController::exportarSegundoPlano)
    ) {}

    public function handle(): void
    {
        // Corre en el worker de colas, no en el ciclo request/response — ver
        // ExportarComprasJob para el hallazgo empírico (curl real, no
        // tinker) de que DomPDF necesita bastante más que 512M en tablas
        // grandes. Mismo margen defensivo que Compras/Proveedores.
        ini_set('memory_limit', '2560M');

        $extension = $this->formato === 'excel' ? 'xlsx' : 'pdf';
        $nombreArchivo = "{$this->usuarioId}_" . now()->format('YmdHis') . '_' . Str::random(8) . ".{$extension}";
        $ruta = self::CARPETA . "/{$nombreArchivo}";

        if ($this->formato === 'excel') {
            Excel::store(new CxPExport($this->empresaId, $this->filtros), $ruta, 'local');
        } else {
            Storage::disk('local')->put($ruta, $this->generarPdf()->output());
        }

        // Se construye la URL absoluta a mano con el host real capturado en
        // el controller (getSchemeAndHttpHost()), NO con route() a secas:
        // dentro de un Job no hay request activa, así que route() cae al
        // host de config('app.url'), que puede no coincidir con el host que
        // realmente sirvió la petición (por eso se rompió antes la descarga
        // de Excel de Asientos en un entorno con puerto distinto al .env).
        $rutaRelativa = route('compras.cxp.exportacion.descargar', ['archivo' => $nombreArchivo], false);

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_cxp',
            'titulo'     => 'Exportación de Cuentas por Pagar lista',
            'mensaje'    => 'Tu ' . ($this->formato === 'excel' ? 'Excel' : 'PDF') .
                ' de Cuentas por Pagar ya está listo para descargar (disponible por 48 horas).',
            'icono'      => 'download',
            'url'        => $this->baseUrl . $rutaRelativa,
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_cxp_error',
            'titulo'     => 'No se pudo generar tu exportación',
            'mensaje'    => 'Hubo un error generando el ' . ($this->formato === 'excel' ? 'Excel' : 'PDF') .
                ' de Cuentas por Pagar. Intenta con un filtro más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }

    // Misma lógica de filtrado que CuentaPagarController::queryFiltrada(),
    // sin el candado MAX_FILAS_EXPORT (aquí ya no aplica: es el camino de
    // segundo plano precisamente para cuando se supera ese límite).
    private function generarPdf()
    {
        $query = CuentaPagar::with(['proveedor', 'compra'])->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado']);
        } else {
            $query->whereIn('estado', ['pendiente', 'parcial']);
        }
        if (!empty($this->filtros['proveedor_id'])) {
            $query->where('proveedor_id', $this->filtros['proveedor_id']);
        }
        if (!empty($this->filtros['periodo'])) {
            $hoy = now();
            match ($this->filtros['periodo']) {
                'hoy'    => $query->whereDate('fecha_vencimiento', $hoy),
                'semana' => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfWeek(), $hoy->copy()->endOfWeek()
                ]),
                'mes'    => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()
                ]),
                'anio'   => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()
                ]),
                'vencidas' => $query->where('fecha_vencimiento', '<', $hoy),
                default  => null,
            };
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha_vencimiento', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha_vencimiento', '<=', $this->filtros['fecha_hasta']);
        }
        if (!empty($this->filtros['buscar'])) {
            $q = $this->filtros['buscar'];
            $query->where(fn($qb) =>
                $qb->whereHas('proveedor', fn($p) => $p->where('razon_social', 'ilike', "%{$q}%"))
                   ->orWhereHas('compra', fn($c) => $c->where('num_documento', 'ilike', "%{$q}%"))
            );
        }

        $cxp     = $query->orderBy('fecha_vencimiento')->get();
        $empresa = Empresa::find($this->empresaId);

        return Pdf::loadView('pdf.cxp', compact('cxp', 'empresa'))->setPaper('a4', 'landscape');
    }
}
