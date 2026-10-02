<?php

namespace App\Exports;

use App\Models\Factura;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * F1 (CHECKLIST_ERRORES_COMPLICACIONES.md): resumen de ventas del período
 * elegido en el Dashboard (mensual/trimestral/anual), para descargar en Excel.
 */
class VentasResumenExport implements FromCollection, WithHeadings, WithColumnWidths, WithTitle
{
    public function __construct(
        private int $empresaId,
        private Carbon $desde,
        private Carbon $hasta,
    ) {}

    public function collection()
    {
        return Factura::with('cliente')
            ->where('empresa_id', $this->empresaId)
            ->where('estado', 'activa')
            ->whereBetween('fecha_emision', [$this->desde->toDateString(), $this->hasta->toDateString()])
            ->orderBy('fecha_emision')
            ->get()
            ->map(fn (Factura $f) => [
                $f->numero_completo,
                $f->fecha_emision?->format('d/m/Y'),
                $f->cliente?->razon_social ?? $f->razon_social ?? '—',
                $f->identificacion,
                (float) $f->subtotal_0 + (float) $f->subtotal_15,
                (float) $f->total_iva,
                (float) $f->total,
            ]);
    }

    public function headings(): array
    {
        return ['N° Factura', 'Fecha', 'Cliente', 'Identificación', 'Subtotal', 'IVA', 'Total'];
    }

    public function columnWidths(): array
    {
        return ['A' => 18, 'B' => 12, 'C' => 40, 'D' => 16, 'E' => 14, 'F' => 12, 'G' => 14];
    }

    public function title(): string
    {
        return 'Resumen de ventas';
    }
}
