<?php
namespace App\Exports;

use App\Models\CuentaPagar;
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

class CxPExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    private int   $totalRows  = 0;
    private float $totalSaldo = 0;

    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function collection()
    {
        $query = CuentaPagar::with(['proveedor', 'compra'])
            ->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado']);
        } else {
            $query->whereIn('estado', ['pendiente', 'parcial']);
        }

        if (!empty($this->filtros['proveedor_id'])) {
            $query->where('proveedor_id', $this->filtros['proveedor_id']);
        }

        $data = $query->orderBy('fecha_vencimiento')->get();
        $this->totalRows  = $data->count();
        $this->totalSaldo = (float) $data->sum('saldo');

        return $data->map(fn($c) => [
            $c->proveedor?->razon_social   ?? '—',
            $c->compra?->num_documento      ?? '—',
            number_format((float)$c->monto, 2),
            number_format((float)$c->saldo, 2),
            $c->fecha_emision?->format('d/m/Y')     ?? '—',
            $c->fecha_vencimiento?->format('d/m/Y')  ?? '—',
            $c->dias_vencimiento > 0
                ? "En {$c->dias_vencimiento} días"
                : ($c->dias_vencimiento === 0
                    ? 'Vence hoy'
                    : 'Vencida hace ' . abs($c->dias_vencimiento) . ' días'),
            ucfirst($c->urgencia),
            ucfirst($c->estado),
        ]);
    }

    public function headings(): array
    {
        return [
            'Proveedor', 'N° Documento', 'Monto ($)',
            'Saldo ($)', 'Emisión', 'Vencimiento',
            'Días', 'Urgencia', 'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A'=>38,'B'=>22,'C'=>14,'D'=>14,
            'E'=>14,'F'=>14,'G'=>22,'H'=>14,'I'=>12,
        ];
    }

    public function title(): string { return 'Cuentas por Pagar'; }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $this->totalRows + 3;
                $totalRow = $lastRow + 1;

                $sheet->insertNewRowBefore(1, 2);

                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', 'ERP Altamira — Cuentas por Pagar');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>13,'color'=>['rgb'=>'F59E0B']],
                ]);

                $sheet->mergeCells('A2:I2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    ' · Pendientes: ' . $this->totalRows .
                    ' · Saldo total: $' . number_format($this->totalSaldo, 2)
                );
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('9CA3AF');

                $sheet->getStyle('A3:I3')->applyFromArray([
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

                $bgUrgencia = [
                    'Vencida' => 'FEE2E2',
                    'Critica' => 'FFEDD5',
                    'Proxima' => 'FEF9C3',
                    'Normal'  => 'F0FDF4',
                ];
                $colorTexto = [
                    'Vencida' => 'DC2626',
                    'Critica' => 'EA580C',
                    'Proxima' => 'CA8A04',
                    'Normal'  => '16A34A',
                ];

                for ($row = 4; $row <= $lastRow; $row++) {
                    $urgencia = $sheet->getCell("H{$row}")->getValue();
                    $bg       = $bgUrgencia[$urgencia] ?? 'FFFFFF';

                    $sheet->getStyle("A{$row}:I{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);

                    $color = $colorTexto[$urgencia] ?? '374151';
                    $sheet->getStyle("H{$row}")->getFont()
                        ->setBold(true)->getColor()->setRGB($color);
                    $sheet->getStyle("G{$row}")->getFont()
                        ->getColor()->setRGB($color);

                    $sheet->getStyle("D{$row}")->getFont()
                        ->setBold(true)->getColor()->setRGB('F59E0B');

                    foreach (['C','D'] as $col) {
                        $sheet->getStyle("{$col}{$row}")->getAlignment()
                            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    }

                    $estado = $sheet->getCell("I{$row}")->getValue();
                    $sheet->getStyle("I{$row}")->getFont()
                        ->setBold(true)->getColor()->setRGB(
                            match($estado) {
                                'Pendiente' => 'EA580C',
                                'Parcial'   => 'CA8A04',
                                default     => '059669',
                            }
                        );

                    $sheet->getStyle("A{$row}:I{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');
                    $sheet->getRowDimension($row)->setRowHeight(18);
                }

                // Fila totales
                $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'SALDO TOTAL PENDIENTE');
                $sheet->setCellValue("D{$totalRow}",
                    '$' . number_format($this->totalSaldo, 2));
                $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F2937']],
                ]);
                $sheet->getStyle("D{$totalRow}")->getFont()
                    ->getColor()->setRGB('F59E0B');
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                $sheet->getStyle("A3:I{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('F59E0B');

                $sheet->setAutoFilter("A3:I{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}
