<?php
namespace App\Exports;

use App\Models\Compra;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ComprasExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    private int   $totalRows  = 0;
    private float $totalMonto = 0;

    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function collection()
    {
        $query = Compra::with('proveedor')
            ->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha_emision', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha_emision', '<=', $this->filtros['fecha_hasta']);
        }

        $data = $query->orderByDesc('fecha_emision')->get();
        $this->totalRows  = $data->count();
        $this->totalMonto = (float) $data->sum('total');

        return $data->map(fn($c) => [
            $c->num_documento,
            $c->fecha_emision?->format('d/m/Y') ?? '',
            $c->proveedor?->razon_social ?? '—',
            $c->tipo_documento,
            (float)$c->subtotal_0,
            (float)$c->subtotal_iva,
            (float)$c->total_iva,
            (float)$c->total,
            $c->dias_credito > 0 ? 'Crédito '.$c->dias_credito.'d' : 'Contado',
            $c->fecha_vencimiento?->format('d/m/Y') ?? '—',
            ucfirst($c->estado),
        ]);
    }

    public function headings(): array
    {
        return [
            'N° Documento', 'Fecha Emisión', 'Proveedor',
            'Tipo', 'Subtotal 0%', 'Subtotal Gravado',
            'IVA', 'Total', 'Forma Pago', 'Vencimiento', 'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A'=>20,'B'=>14,'C'=>40,'D'=>8,
            'E'=>14,'F'=>16,'G'=>12,'H'=>14,
            'I'=>14,'J'=>14,'K'=>12,
        ];
    }

    public function title(): string { return 'Facturas de Compra'; }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $this->totalRows + 3;
                $totalRow = $lastRow + 1;

                $sheet->insertNewRowBefore(1, 2);

                $sheet->mergeCells('A1:K1');
                $sheet->setCellValue('A1', 'ERP Altamira — Facturas de Compra');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>13,'color'=>['rgb'=>'F59E0B']],
                ]);
                $sheet->mergeCells('A2:K2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    ' · Total: ' . $this->totalRows . ' facturas' .
                    ' · Monto total: $' . number_format($this->totalMonto, 2)
                );
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('9CA3AF');

                $sheet->getStyle('A3:K3')->applyFromArray([
                    'font' => ['bold'=>true,'color'=>['rgb'=>'FFFFFF'],'size'=>10],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'F59E0B']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER,
                                    'vertical'=>Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders'=>[
                        'borderStyle'=>Border::BORDER_THIN,
                        'color'=>['rgb'=>'D97706'],
                    ]],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(22);

                for ($row = 4; $row <= $lastRow; $row++) {
                    $bg = ($row % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                    $sheet->getStyle("A{$row}:K{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bg);

                    $sheet->getStyle("A{$row}")->getFont()
                        ->setBold(true)->getColor()->setRGB('F59E0B');

                    // Montos alineados a la derecha con formato numérico
                    foreach (['E','F','G','H'] as $col) {
                        $sheet->getStyle("{$col}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        $sheet->getStyle("{$col}{$row}")->getNumberFormat()
                            ->setFormatCode('#,##0.00');
                    }
                    $sheet->getStyle("H{$row}")->getFont()
                        ->setBold(true)->getColor()->setRGB('F59E0B');

                    // Estado en color
                    $estado = $sheet->getCell("K{$row}")->getValue();
                    $sheet->getStyle("K{$row}")->getFont()
                        ->setBold(true)->getColor()
                        ->setRGB($estado === 'Activa' ? '059669' : 'DC2626');

                    $sheet->getStyle("A{$row}:K{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');
                    $sheet->getRowDimension($row)->setRowHeight(17);
                }

                // Fila totales
                $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL FACTURAS');
                $sheet->setCellValue("H{$totalRow}", "=SUM(H4:H{$lastRow})");
                $sheet->getStyle("A{$totalRow}:K{$totalRow}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F2937']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_RIGHT],
                ]);
                $sheet->getStyle("H{$totalRow}")->getFont()->getColor()->setRGB('F59E0B');
                $sheet->getStyle("H{$totalRow}")->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                $sheet->getStyle("A3:K{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('F59E0B');

                $sheet->setAutoFilter("A3:K{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}
