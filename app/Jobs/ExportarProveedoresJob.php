<?php

namespace App\Jobs;

use App\Exports\ProveedoresExport;
use App\Models\Empresa;
use App\Models\Notificacion;
use App\Models\Proveedor;
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
 * Genera el Excel o PDF completo de Proveedores en segundo plano — sin el
 * límite de ProveedorController::MAX_FILAS_EXPORT que existe solo para el
 * camino síncrono (descarga inmediata). Mismo patrón que
 * ExportarAsientosJob / ExportarComprasJob.
 */
class ExportarProveedoresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CARPETA = 'exportaciones-proveedores';

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
        // Corre en el worker de colas, no en el ciclo request/response — ver
        // ExportarComprasJob para el hallazgo empírico (curl real, no
        // tinker) de que DomPDF necesita bastante más que 512M en tablas
        // grandes. El catálogo de Proveedores es mucho más chico que
        // Facturas de Compra (~231 filas reales vs. ~2239), pero se deja el
        // mismo margen defensivo por si crece.
        ini_set('memory_limit', '2560M');

        $extension = $this->formato === 'excel' ? 'xlsx' : 'pdf';
        $nombreArchivo = "{$this->usuarioId}_" . now()->format('YmdHis') . '_' . Str::random(8) . ".{$extension}";
        $ruta = self::CARPETA . "/{$nombreArchivo}";

        if ($this->formato === 'excel') {
            Excel::store(new ProveedoresExport($this->empresaId, $this->filtros), $ruta, 'local');
        } else {
            Storage::disk('local')->put($ruta, $this->generarPdf()->output());
        }

        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_proveedores',
            'titulo'     => 'Exportación de Proveedores lista',
            'mensaje'    => 'Tu ' . ($this->formato === 'excel' ? 'Excel' : 'PDF') .
                ' de Proveedores ya está listo para descargar (disponible por 48 horas).',
            'icono'      => 'download',
            'url'        => route('compras.proveedores.exportacion.descargar', ['archivo' => $nombreArchivo]),
            'leida'      => false,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Notificacion::create([
            'usuario_id' => $this->usuarioId,
            'tipo'       => 'exportacion_proveedores_error',
            'titulo'     => 'No se pudo generar tu exportación',
            'mensaje'    => 'Hubo un error generando el ' . ($this->formato === 'excel' ? 'Excel' : 'PDF') .
                ' de Proveedores. Intenta con un filtro más acotado.',
            'icono'      => 'alert-triangle',
            'url'        => null,
            'leida'      => false,
        ]);
    }

    // Misma lógica de filtrado que ProveedorController::queryFiltrada(), sin
    // el candado MAX_FILAS_EXPORT (aquí ya no aplica: es el camino de
    // segundo plano precisamente para cuando se supera ese límite).
    private function generarPdf()
    {
        $query = Proveedor::where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['tipo'])) {
            $query->where('tipo', $this->filtros['tipo']);
        }
        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado'] === 'activo');
        }
        if (!empty($this->filtros['credito'])) {
            $query->where('tiene_credito', $this->filtros['credito'] === 'con');
        }
        if (!empty($this->filtros['buscar'])) {
            $q = $this->filtros['buscar'];
            $query->where(fn($qb) =>
                $qb->where('razon_social', 'ilike', "%{$q}%")
                   ->orWhere('identificacion', 'ilike', "%{$q}%")
                   ->orWhere('nombre_comercial', 'ilike', "%{$q}%")
                   ->orWhere('email', 'ilike', "%{$q}%")
            );
        }

        $proveedores = $query->orderBy('razon_social')->get();
        $empresa     = Empresa::find($this->empresaId);

        return Pdf::loadView('pdf.proveedores', compact('proveedores', 'empresa'))->setPaper('a4', 'landscape');
    }
}
