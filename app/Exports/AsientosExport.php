<?php
namespace App\Exports;

use App\Models\AsientoContable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ─── Hoja 1: Resumen de asientos ─────────────────────────────────────────────
class AsientosResumenSheet implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    private int   $totalRows = 0;
    private float $totalDebe  = 0;
    private float $totalHaber = 0;

    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function collection()
    {
        $query = AsientoContable::with(['ejercicio', 'creadoPor'])
            ->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['ejercicio_id'])) {
            $query->where('ejercicio_id', $this->filtros['ejercicio_id']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $this->filtros['fecha_hasta']);
        }

        $data = $query->orderByDesc('fecha')->get();
        $this->totalRows  = $data->count();
        $this->totalDebe  = $data->sum('total_debe');
        $this->totalHaber = $data->sum('total_haber');

        return $data->map(fn($a) => [
            $a->numero,
            $a->fecha?->format('d/m/Y') ?? '',
            $a->concepto,
            $a->documento_ref ?? '—',
            $a->es_automatico ? 'Automático' : 'Manual',
            (float)$a->total_debe,
            (float)$a->total_haber,
            $a->estado === 1 ? 'Activo' : 'Anulado',
            $a->ejercicio?->periodo_label ?? '—',
            $a->creadoPor?->email ?? '—',
        ]);
    }

    public function headings(): array
    {
        return [
            'N° Asiento', 'Fecha', 'Concepto', 'Referencia',
            'Tipo', 'DEBE ($)', 'HABER ($)', 'Estado',
            'Período', 'Creado por',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, 'B' => 13, 'C' => 50, 'D' => 20,
            'E' => 13, 'F' => 14, 'G' => 14, 'H' => 10,
            'I' => 18, 'J' => 28,
        ];
    }

    public function title(): string { return 'Resumen Asientos'; }

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
                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1', 'ERP Altamira — Reporte General de Asientos Contables');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'F59E0B']],
                ]);

                // Subtítulo
                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    ' · Total: ' . $this->totalRows . ' asientos' .
                    ' · DEBE Total: $' . number_format($this->totalDebe, 2) .
                    ' · HABER Total: $' . number_format($this->totalHaber, 2)
                );
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('9CA3AF');

                // Encabezado fila 3
                $sheet->getStyle('A3:J3')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F59E0B']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders'   => ['allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => 'D97706'],
                    ]],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(22);

                // Filas de datos
                for ($row = 4; $row <= $lastRow; $row++) {
                    $bg = ($row % 2 === 0) ? 'F9FAFB' : 'FFFFFF';
                    $sheet->getStyle("A{$row}:J{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);

                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('F59E0B');

                    $sheet->getStyle("F{$row}:G{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("F{$row}:G{$row}")->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                    $sheet->getStyle("F{$row}")->getFont()->getColor()->setRGB('059669');
                    $sheet->getStyle("G{$row}")->getFont()->getColor()->setRGB('DC2626');

                    $estado = $sheet->getCell("H{$row}")->getValue();
                    $sheet->getStyle("H{$row}")->getFont()->setBold(true)->getColor()
                        ->setRGB($estado === 'Activo' ? '059669' : 'DC2626');

                    $sheet->getStyle("A{$row}:J{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');
                    $sheet->getRowDimension($row)->setRowHeight(17);
                }

                // Fila totales
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTALES');
                $sheet->setCellValue("F{$totalRow}", "=SUM(F4:F{$lastRow})");
                $sheet->setCellValue("G{$totalRow}", "=SUM(G4:G{$lastRow})");
                $sheet->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'size' => 11, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'numberFormat' => ['formatCode' => '#,##0.00'],
                ]);
                $sheet->getStyle("F{$totalRow}")->getFont()->getColor()->setRGB('6EE7B7');
                $sheet->getStyle("G{$totalRow}")->getFont()->getColor()->setRGB('FCA5A5');
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // Borde exterior
                $sheet->getStyle("A3:J{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('F59E0B');

                $sheet->setAutoFilter("A3:J{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}

// ─── Hoja 2: Detalle de partidas ─────────────────────────────────────────────
class AsientosDetalleSheet implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    private int $totalRows = 0;

    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function collection()
    {
        $query = AsientoContable::with(['detalles.cuenta'])
            ->where('empresa_id', $this->empresaId)
            ->where('estado', 1);

        if (!empty($this->filtros['ejercicio_id'])) {
            $query->where('ejercicio_id', $this->filtros['ejercicio_id']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $this->filtros['fecha_hasta']);
        }

        $rows = collect();
        $query->orderByDesc('fecha')->each(function ($asiento) use (&$rows) {
            foreach ($asiento->detalles as $d) {
                $rows->push([
                    $asiento->numero,
                    $asiento->fecha?->format('d/m/Y') ?? '',
                    $d->cuenta?->codigo ?? '—',
                    $d->cuenta?->nombre ?? '—',
                    $d->descripcion ?? '—',
                    (float)$d->debe,
                    (float)$d->haber,
                ]);
            }
        });

        $this->totalRows = $rows->count();
        return $rows;
    }

    public function headings(): array
    {
        return [
            'N° Asiento', 'Fecha',
            'Cód. Cuenta', 'Nombre Cuenta',
            'Descripción', 'DEBE ($)', 'HABER ($)',
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 13, 'C' => 16, 'D' => 40, 'E' => 40, 'F' => 14, 'G' => 14];
    }

    public function title(): string { return 'Detalle Partidas'; }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastRow = $this->totalRows + 3;

                $sheet->insertNewRowBefore(1, 2);

                $sheet->mergeCells('A1:G1');
                $sheet->setCellValue('A1', 'ERP Altamira — Detalle de Partidas Contables');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 13, 'color' => ['rgb' => 'F59E0B']],
                ]);

                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    ' · ' . $this->totalRows . ' líneas contables'
                );
                $sheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('9CA3AF');

                // Encabezado
                $sheet->getStyle('A3:G3')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F2937']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(22);

                $asientoActual = '';
                for ($row = 4; $row <= $lastRow; $row++) {
                    $numero = $sheet->getCell("A{$row}")->getValue();
                    $esNuevo = $numero !== $asientoActual;
                    $asientoActual = $numero;

                    $bg = $esNuevo ? 'FFF8EE' : 'FFFFFF';
                    if (($row - 3) % 2 === 0 && !$esNuevo) $bg = 'F9FAFB';

                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);

                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->getColor()->setRGB('F59E0B');
                    $sheet->getStyle("C{$row}")->getFont()->setBold(true)->getColor()->setRGB('1E40AF');

                    $debe  = (float)$sheet->getCell("F{$row}")->getValue();
                    $haber = (float)$sheet->getCell("G{$row}")->getValue();
                    $sheet->getStyle("F{$row}")->getFont()->getColor()
                        ->setRGB($debe  > 0 ? '059669' : 'D1D5DB');
                    $sheet->getStyle("G{$row}")->getFont()->getColor()
                        ->setRGB($haber > 0 ? 'DC2626' : 'D1D5DB');

                    $sheet->getStyle("F{$row}:G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $sheet->getStyle("F{$row}:G{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_THIN)
                        ->getColor()->setRGB('E5E7EB');
                    $sheet->getRowDimension($row)->setRowHeight(16);
                }

                // Borde exterior
                $sheet->getStyle("A3:G{$lastRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('1F2937');

                $sheet->setAutoFilter("A3:G{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}

// ─── Clase principal con múltiples hojas ─────────────────────────────────────
class AsientosExport implements WithMultipleSheets
{
    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function sheets(): array
    {
        return [
            new AsientosResumenSheet($this->empresaId, $this->filtros),
            new AsientosDetalleSheet($this->empresaId, $this->filtros),
        ];
    }
}
