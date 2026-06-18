<?php
namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

trait EstiloAcademico
{
    // ── Paleta académica ──────────────────────────────────────────
    protected const H_NAVY    = '1F2D3D'; // encabezado / total
    protected const H_PAR     = 'F5F7FA'; // fila par
    protected const H_IMP     = 'FFFFFF'; // fila impar
    protected const H_BORDE   = 'D8DCE6'; // bordes
    protected const H_TEXTO   = '1A1A2E'; // texto principal
    protected const H_MUTED   = '555770'; // texto secundario
    protected const H_ACENTO  = '2C5F8A'; // azul acento (códigos)
    protected const H_BLANCO  = 'FFFFFF';

    protected function estiloHeaderAcad(): array
    {
        return [
            'font' => [
                'bold'  => true,
                'size'  => 9,
                'color' => ['rgb' => self::H_BLANCO],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::H_NAVY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color'       => ['rgb' => self::H_NAVY],
                ],
            ],
        ];
    }

    protected function estiloTotalAcad(): array
    {
        return [
            'font' => [
                'bold'  => true,
                'size'  => 10,
                'color' => ['rgb' => self::H_BLANCO],
            ],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::H_NAVY],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_RIGHT,
            ],
            'numberFormat' => [
                'formatCode' => '#,##0.00',
            ],
        ];
    }

    protected function aplicarFilasAcad(Worksheet $sheet, int $desde, int $hasta, int $numCols): void
    {
        $ultima = Coordinate::stringFromColumnIndex($numCols);
        for ($row = $desde; $row <= $hasta; $row++) {
            $bg = ($row % 2 === 0) ? self::H_PAR : self::H_IMP;
            $sheet->getStyle("A{$row}:{$ultima}{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($bg);
            $sheet->getStyle("A{$row}:{$ultima}{$row}")
                ->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_HAIR)
                ->getColor()->setRGB(self::H_BORDE);
            $sheet->getRowDimension($row)->setRowHeight(16);
        }
    }
}
