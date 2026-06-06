<?php
namespace App\Exports;

use App\Models\Proveedor;
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

class ProveedoresExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    private int $totalRows = 0;

    public function __construct(private int $empresaId) {}

    public function collection()
    {
        $data = Proveedor::where('empresa_id', $this->empresaId)
            ->orderBy('razon_social')
            ->get();

        $this->totalRows = $data->count();

        return $data->map(fn($p) => [
            $p->identificacion,
            $p->razon_social,
            $p->nombre_comercial ?? '—',
            ucfirst($p->tipo),
            $p->email ?? '—',
            $p->telefono ?? '—',
            $p->ciudad ?? '—',
            $p->pais,
            $p->divisa,
            $p->tiene_credito ? 'Sí' : 'No',
            $p->dias_credito,
            $p->estado ? 'Activo' : 'Inactivo',
        ]);
    }

    public function headings(): array
    {
        return [
            'Identificación', 'Razón Social', 'Nombre Comercial',
            'Tipo', 'Email', 'Teléfono', 'Ciudad', 'País', 'Divisa',
            'Crédito', 'Días Crédito', 'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A'=>18,'B'=>40,'C'=>30,'D'=>14,'E'=>28,
            'F'=>14,'G'=>16,'H'=>14,'I'=>10,'J'=>10,
            'K'=>13,'L'=>12,
        ];
    }

    public function title(): string { return 'Proveedores'; }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $this->totalRows + 3;

                $sheet->insertNewRowBefore(1, 2);

                // Título
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', 'ERP Altamira — Catálogo de Proveedores');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold'=>true,'size'=>13,'color'=>['rgb'=>'F59E0B']],
                ]);
                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    ' · Total: ' . $this->totalRows . ' proveedores'
                );
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('9CA3AF');

                // Encabezado fila 3
                $sheet->getStyle('A3:L3')->applyFromArray([
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
                    $sheet->getStyle("A{$row}:L{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bg);

                    // Tipo en color
                    $tipo = $sheet->getCell("D{$row}")->getValue();
                    $sheet->getStyle("D{$row}")->getFont()
                        ->setBold(true)->getColor()
                        ->setRGB($tipo === 'Nacional' ? '059669' : '3B82F6');

                    // Estado en color
                    $estado = $sheet->getCell("L{$row}")->getValue();
                    $sheet->getStyle("L{$row}")->getFont()
                        ->setBold(true)->getColor()
                        ->setRGB($estado === 'Activo' ? '059669' : 'DC2626');

                    // Crédito
                    $credito = $sheet->getCell("J{$row}")->getValue();
                    $sheet->getStyle("J{$row}")->getFont()
                        ->getColor()
                        ->setRGB($credito === 'Sí' ? '059669' : '9CA3AF');

                    $sheet->getStyle("A{$row}:L{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');
                    $sheet->getRowDimension($row)->setRowHeight(17);
                }

                // Fila totales
                $totalRow = $lastRow + 1;
                $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL DE PROVEEDORES');
                $sheet->setCellValue("L{$totalRow}", $this->totalRows);
                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray([
                    'font' => ['bold'=>true,'size'=>11,'color'=>['rgb'=>'FFFFFF']],
                    'fill' => ['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'1F2937']],
                    'alignment' => ['horizontal'=>Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                $sheet->getStyle("A3:L{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('F59E0B');

                $sheet->setAutoFilter("A3:L{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}
