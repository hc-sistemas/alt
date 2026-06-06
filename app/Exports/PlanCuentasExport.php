<?php

namespace App\Exports;

use App\Models\PlanCuenta;
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

class PlanCuentasExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    private int $totalRows = 0;

    private array $coloresTipo = [
        'activo'     => ['header' => '1E40AF', 'bg' => 'DBEAFE', 'text' => '1E3A8A'],
        'pasivo'     => ['header' => '991B1B', 'bg' => 'FEE2E2', 'text' => '7F1D1D'],
        'patrimonio' => ['header' => '6B21A8', 'bg' => 'EDE9FE', 'text' => '4C1D95'],
        'ingreso'    => ['header' => '065F46', 'bg' => 'D1FAE5', 'text' => '064E3B'],
        'gasto'      => ['header' => '92400E', 'bg' => 'FEF3C7', 'text' => '78350F'],
    ];

    public function collection()
    {
        $cuentas = PlanCuenta::orderBy('codigo')->get();
        $this->totalRows = $cuentas->count();

        return $cuentas->map(fn ($c) => [
            $c->codigo,
            str_repeat('  ', $c->nivel - 1) . $c->nombre,
            ucfirst($c->tipo),
            $c->nivel,
            $c->permite_asientos ? 'Sí' : 'No',
            $c->estado ? 'Activa' : 'Inactiva',
            $c->total_asientos,
        ]);
    }

    public function headings(): array
    {
        return [
            'Código',
            'Nombre de Cuenta',
            'Tipo',
            'Nivel',
            'Permite Asientos',
            'Estado',
            'Total Asientos',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,
            'B' => 55,
            'C' => 14,
            'D' => 8,
            'E' => 18,
            'F' => 12,
            'G' => 16,
        ];
    }

    public function title(): string
    {
        return 'Plan de Cuentas';
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $this->totalRows + 3;

                // Insertar 2 filas arriba para título
                $sheet->insertNewRowBefore(1, 2);

                // Título principal
                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', 'ERP Altamira — Plan de Cuentas Contable');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 14,
                        'color' => ['rgb' => 'F59E0B'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                ]);

                // Subtítulo con fecha
                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2',
                    'Generado el ' . now()->format('d/m/Y H:i') .
                    ' · Total: ' . $this->totalRows . ' cuentas'
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 9, 'color' => ['rgb' => '9CA3AF']],
                ]);

                // Encabezado fila 3
                $sheet->getStyle('A3:G3')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size'  => 10,
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F59E0B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => 'D97706'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(24);

                // Estilos por fila de datos
                for ($row = 4; $row <= $lastRow; $row++) {
                    $tipo   = strtolower($sheet->getCell("C{$row}")->getValue());
                    $nivel  = (int) $sheet->getCell("D{$row}")->getValue();
                    $colors = $this->coloresTipo[$tipo] ?? [
                        'header' => '374151',
                        'bg'     => 'F9FAFB',
                        'text'   => '1F2937',
                    ];

                    if ($nivel === 1) {
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => [
                                'bold'  => true,
                                'size'  => 11,
                                'color' => ['rgb' => 'FFFFFF'],
                            ],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $colors['header']],
                            ],
                        ]);
                    } elseif ($nivel === 2) {
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => [
                                'bold'  => true,
                                'color' => ['rgb' => $colors['text']],
                            ],
                            'fill' => [
                                'fillType'   => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => $colors['bg']],
                            ],
                        ]);
                    } else {
                        $bg = ($row % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                        $sheet->getStyle("A{$row}:G{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($bg);
                        $sheet->getStyle("A{$row}")->getFont()
                            ->getColor()->setRGB('F59E0B');
                    }

                    $sheet->getStyle("A{$row}")->getFont()->setBold(true);

                    $sheet->getStyle("G{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    $estado = $sheet->getCell("F{$row}")->getValue();
                    $color  = $estado === 'Activa' ? '059669' : 'DC2626';
                    $sheet->getStyle("F{$row}")->getFont()
                        ->setBold(true)->getColor()->setRGB($color);

                    $permiteAsientos = $sheet->getCell("E{$row}")->getValue();
                    $sheet->getStyle("E{$row}")->getFont()
                        ->getColor()->setRGB($permiteAsientos === 'Sí' ? '059669' : '9CA3AF');

                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');

                    $sheet->getRowDimension($row)->setRowHeight(16);
                }

                // Fila de totales final
                $totalRow = $lastRow + 1;
                $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL DE CUENTAS');
                $sheet->setCellValue("G{$totalRow}", $this->totalRows);
                $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1F2937'],
                    ],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // Borde exterior de toda la tabla
                $sheet->getStyle("A3:G{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('F59E0B');

                // Autofilter y congelar encabezado
                $sheet->setAutoFilter("A3:G{$lastRow}");
                $sheet->freezePane('A4');

                // Columna D (Nivel) centrada
                $sheet->getStyle("D3:D{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
