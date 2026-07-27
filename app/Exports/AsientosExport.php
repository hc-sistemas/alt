<?php
namespace App\Exports;

use App\Exports\Concerns\EstiloAcademico;
use App\Models\AsientoContable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// ── Hoja 1: Resumen ────────────────────────────────────────────
class AsientosResumenSheet implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    use EstiloAcademico;

    private int   $totalRows  = 0;
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
        if (!empty($this->filtros['tipo'])) {
            $query->where('es_automatico',
                $this->filtros['tipo'] === 'automatico');
        }
        if (!empty($this->filtros['estado'])) {
            $query->where('estado',
                $this->filtros['estado'] === 'activo' ? 1 : 0);
        }

        $data = $query->orderByDesc('fecha')->get();
        $this->totalRows  = $data->count();
        $this->totalDebe  = $data->sum('total_debe');
        $this->totalHaber = $data->sum('total_haber');

        return $data->map(fn($a) => [
            $a->numero,
            $a->fecha?->format('d/m/Y') ?? '',
            $a->concepto,
            $a->documento_ref ?? '',
            $a->es_automatico ? 'Automático' : 'Manual',
            (float)$a->total_debe,
            (float)$a->total_haber,
            $a->estado === 1 ? 'Activo' : 'Anulado',
            $a->ejercicio?->periodo_label ?? '',
            $a->creadoPor?->email ?? '',
        ]);
    }

    public function headings(): array
    {
        return [
            'N° Asiento', 'Fecha', 'Concepto', 'Referencia',
            'Tipo', 'Debe ($)', 'Haber ($)', 'Estado',
            'Período', 'Creado por',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, 'B' => 13, 'C' => 52, 'D' => 18,
            'E' => 13, 'F' => 14, 'G' => 14, 'H' => 10,
            'I' => 16, 'J' => 26,
        ];
    }

    public function title(): string { return 'Asientos Contables'; }

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
                $sheet->setCellValue('A1',
                    'Altamira Light & Sound — Asientos Contables');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::H_TEXTO]],
                ]);

                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   Total: ' . $this->totalRows . ' asientos' .
                    '   |   Debe: $' . number_format($this->totalDebe, 2) .
                    '   |   Haber: $' . number_format($this->totalHaber, 2)
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => self::H_MUTED]],
                ]);

                // Encabezado fila 3
                $sheet->getStyle('A3:J3')->applyFromArray($this->estiloHeaderAcad());
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Filas de datos
                $this->aplicarFilasAcad($sheet, 4, $lastRow, 10);

                for ($row = 4; $row <= $lastRow; $row++) {
                    // N° Asiento — acento azul bold
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_ACENTO]],
                    ]);

                    // Debe y Haber — texto oscuro, alineados derecha
                    foreach (['F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['rgb' => self::H_TEXTO]],
                            'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                            'numberFormat' => ['formatCode' => '#,##0.00'],
                        ]);
                    }

                    // Estado — texto plano
                    $estado = $sheet->getCell("H{$row}")->getValue();
                    $color  = $estado === 'Activo' ? self::H_TEXTO : self::H_MUTED;
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $color]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Tipo — centrado
                    $sheet->getStyle("E{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Fila de totales
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTALES GENERALES');
                $sheet->setCellValue("F{$totalRow}", "=SUM(F4:F{$lastRow})");
                $sheet->setCellValue("G{$totalRow}", "=SUM(G4:G{$lastRow})");
                $sheet->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray(
                    $this->estiloTotalAcad()
                );
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // Borde exterior
                $sheet->getStyle("A3:J{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB(self::H_NAVY);

                $sheet->setAutoFilter("A3:J{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}

// ── Hoja 2: Detalle de partidas ────────────────────────────────
class AsientosDetalleSheet implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    use EstiloAcademico;

    private int $totalRows = 0;

    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function collection()
    {
        $query = AsientoContable::with(['detalles.cuenta'])
            ->where('empresa_id', $this->empresaId);

        if (!empty($this->filtros['estado'])) {
            $query->where('estado', $this->filtros['estado'] === 'activo' ? 1 : 0);
        } else {
            $query->where('estado', 1);
        }
        if (!empty($this->filtros['tipo'])) {
            $query->where('es_automatico', $this->filtros['tipo'] === 'automatico');
        }
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
                    $d->descripcion ?? '',
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
            'Cód. Cuenta', 'Cuenta Contable',
            'Descripción', 'Debe ($)', 'Haber ($)',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, 'B' => 13, 'C' => 14,
            'D' => 40, 'E' => 42, 'F' => 14, 'G' => 14,
        ];
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
                $sheet->setCellValue('A1',
                    'Altamira Light & Sound — Detalle de Partidas Contables');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => self::H_TEXTO]],
                ]);

                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   ' . $this->totalRows . ' líneas contables'
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => self::H_MUTED]],
                ]);

                // Encabezado
                $sheet->getStyle('A3:G3')->applyFromArray($this->estiloHeaderAcad());
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Filas agrupadas por asiento
                $asientoActual = '';
                for ($row = 4; $row <= $lastRow; $row++) {
                    $numero        = $sheet->getCell("A{$row}")->getValue();
                    $esNuevo       = $numero !== $asientoActual;
                    $asientoActual = $numero;

                    $bg = $esNuevo ? 'EEF1F5' : (($row % 2 === 0) ? self::H_PAR : self::H_IMP);
                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bg);

                    // N° Asiento — acento azul
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_ACENTO]],
                    ]);

                    // Código cuenta — texto principal bold
                    $sheet->getStyle("C{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_TEXTO]],
                    ]);

                    // Debe — oscuro o gris si cero
                    $debe = (float) $sheet->getCell("F{$row}")->getValue();
                    $sheet->getStyle("F{$row}")->applyFromArray([
                        'font'         => ['color' => ['rgb' => $debe > 0 ? self::H_TEXTO : 'AAAAAA']],
                        'alignment'    => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                        'numberFormat' => ['formatCode' => $debe > 0 ? '#,##0.00' : '"-"'],
                    ]);

                    // Haber — oscuro o gris si cero
                    $haber = (float) $sheet->getCell("G{$row}")->getValue();
                    $sheet->getStyle("G{$row}")->applyFromArray([
                        'font'         => ['color' => ['rgb' => $haber > 0 ? self::H_TEXTO : 'AAAAAA']],
                        'alignment'    => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                        'numberFormat' => ['formatCode' => $haber > 0 ? '#,##0.00' : '"-"'],
                    ]);

                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()
                        ->getBottom()->setBorderStyle(Border::BORDER_HAIR)
                        ->getColor()->setRGB(self::H_BORDE);

                    $sheet->getRowDimension($row)->setRowHeight(15);
                }

                // Borde exterior
                $sheet->getStyle("A3:G{$lastRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB(self::H_NAVY);

                $sheet->setAutoFilter("A3:G{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}

// ── Clase principal con múltiples hojas ────────────────────────
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
