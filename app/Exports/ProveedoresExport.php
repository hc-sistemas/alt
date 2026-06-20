<?php
namespace App\Exports;

use App\Exports\Concerns\EstiloAcademico;
use App\Models\Proveedor;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProveedoresExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    use EstiloAcademico;

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
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $this->totalRows + 3;
                $totalRow = $lastRow + 1;

                $sheet->insertNewRowBefore(1, 2);

                // Título
                $sheet->mergeCells('A1:L1');
                $sheet->setCellValue('A1', 'Altamira Light & Sound — Catálogo de Proveedores');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::H_TEXTO]],
                ]);

                $sheet->mergeCells('A2:L2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   Total: ' . $this->totalRows . ' proveedores'
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => self::H_MUTED]],
                ]);

                // Encabezado fila 3
                $sheet->getStyle('A3:L3')->applyFromArray($this->estiloHeaderAcad());
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Filas de datos
                $this->aplicarFilasAcad($sheet, 4, $lastRow, 12);

                for ($row = 4; $row <= $lastRow; $row++) {
                    // Tipo — texto plano sin color
                    $sheet->getStyle("D{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_TEXTO]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Estado — texto plano
                    $estado = $sheet->getCell("L{$row}")->getValue();
                    $color  = $estado === 'Activo' ? self::H_TEXTO : self::H_MUTED;
                    $sheet->getStyle("L{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $color]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Días crédito — centrado
                    $sheet->getStyle("K{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Fila total
                $sheet->mergeCells("A{$totalRow}:K{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL DE PROVEEDORES');
                $sheet->setCellValue("L{$totalRow}", $this->totalRows);
                $sheet->getStyle("A{$totalRow}:L{$totalRow}")->applyFromArray(
                    array_merge($this->estiloTotalAcad(), [
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    ])
                );
                $sheet->getStyle("L{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // Borde exterior
                $sheet->getStyle("A3:L{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB(self::H_NAVY);

                $sheet->setAutoFilter("A3:L{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}
