<?php
namespace App\Exports;

use App\Exports\Concerns\EstiloAcademico;
use App\Models\PlanCuenta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PlanCuentasExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    use EstiloAcademico;

    private int $totalRows = 0;

    private const BG_NIVEL1 = 'D4DCE6'; // azul muy suave — grupo principal
    private const BG_NIVEL2 = 'E8ECF2'; // gris-azul — subcuenta de grupo

    public function collection()
    {
        $cuentas = PlanCuenta::orderBy('codigo')->get();
        $this->totalRows = $cuentas->count();

        return $cuentas->map(fn($c) => [
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
            'Código', 'Nombre de Cuenta', 'Tipo',
            'Nivel', 'Permite Asientos', 'Estado', 'Total Asientos',
        ];
    }

    public function columnWidths(): array
    {
        return ['A'=>18,'B'=>55,'C'=>14,'D'=>8,'E'=>18,'F'=>12,'G'=>16];
    }

    public function title(): string { return 'Plan de Cuentas'; }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $this->totalRows + 3;

                $sheet->insertNewRowBefore(1, 2);

                // Título
                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', 'Altamira Light & Sound — Plan de Cuentas Contable');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::H_TEXTO]],
                ]);

                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   Total: ' . $this->totalRows . ' cuentas'
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => self::H_MUTED]],
                ]);

                // Encabezado fila 3
                $sheet->getStyle('A3:G3')->applyFromArray($this->estiloHeaderAcad());
                $sheet->getRowDimension(3)->setRowHeight(22);

                // Filas de datos
                for ($row = 4; $row <= $lastRow; $row++) {
                    $nivel = (int) $sheet->getCell("D{$row}")->getValue();

                    if ($nivel === 1) {
                        // Cuenta principal — fondo azul muy suave, bold
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::H_TEXTO]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BG_NIVEL1]],
                        ]);
                    } elseif ($nivel === 2) {
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => self::H_TEXTO]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::BG_NIVEL2]],
                        ]);
                    } else {
                        $bg = ($row % 2 === 0) ? self::H_PAR : self::H_IMP;
                        $sheet->getStyle("A{$row}:G{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($bg);
                    }

                    // Código — acento azul bold siempre
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_ACENTO]],
                    ]);

                    // Estado — texto plano
                    $estado = $sheet->getCell("F{$row}")->getValue();
                    $sheet->getStyle("F{$row}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => $estado === 'Activa' ? self::H_TEXTO : self::H_MUTED],
                        ],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Permite asientos — centrado
                    $sheet->getStyle("E{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Total asientos — centrado
                    $sheet->getStyle("G{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Nivel — centrado
                    $sheet->getStyle("D{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Borde inferior suave
                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_HAIR)
                        ->getColor()->setRGB(self::H_BORDE);

                    $sheet->getRowDimension($row)->setRowHeight(16);
                }

                // Fila total
                $totalRow = $lastRow + 1;
                $sheet->mergeCells("A{$totalRow}:F{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL DE CUENTAS');
                $sheet->setCellValue("G{$totalRow}", $this->totalRows);
                $sheet->getStyle("A{$totalRow}:G{$totalRow}")->applyFromArray(
                    $this->estiloTotalAcad()
                );
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("G{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // Borde exterior
                $sheet->getStyle("A3:G{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB(self::H_NAVY);

                $sheet->setAutoFilter("A3:G{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}
