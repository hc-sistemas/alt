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

// ── Hoja 1: Resumen ────────────────────────────────────────────
class AsientosResumenSheet implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
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

                // ── Insertar 2 filas para cabecera ──────────────
                $sheet->insertNewRowBefore(1, 2);

                // Título sobrio
                $sheet->mergeCells('A1:J1');
                $sheet->setCellValue('A1',
                    'Altamira Light & Sound — Asientos Contables');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 12,
                        'color' => ['rgb' => '1A3A5C'],
                    ],
                ]);

                $sheet->mergeCells('A2:J2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   Total: ' . $this->totalRows . ' asientos' .
                    '   |   Debe: $' . number_format($this->totalDebe, 2) .
                    '   |   Haber: $' . number_format($this->totalHaber, 2)
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => [
                        'size'   => 8,
                        'color'  => ['rgb' => '666666'],
                        'italic' => true,
                    ],
                ]);

                // ── Encabezado fila 3 ───────────────────────────
                $sheet->getStyle('A3:J3')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 9,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2C3E50'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color'       => ['rgb' => '1A2B3C'],
                        ],
                    ],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(20);

                // ── Filas de datos ──────────────────────────────
                for ($row = 4; $row <= $lastRow; $row++) {
                    $bg = ($row % 2 === 0) ? 'F8F9FA' : 'FFFFFF';
                    $sheet->getStyle("A{$row}:J{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bg);

                    // Número asiento — azul marino bold
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => '1A3A5C'],
                        ],
                    ]);

                    // Debe y Haber — verde oscuro, alineados derecha
                    foreach (['F', 'G'] as $col) {
                        $sheet->getStyle("{$col}{$row}")->applyFromArray([
                            'font' => [
                                'color' => ['rgb' => '2D6A4F'],
                                'bold'  => true,
                            ],
                            'alignment' => [
                                'horizontal' => Alignment::HORIZONTAL_RIGHT,
                            ],
                            'numberFormat' => [
                                'formatCode' => '#,##0.00',
                            ],
                        ]);
                    }

                    // Estado — verde oscuro o rojo oscuro
                    $estado = $sheet->getCell("H{$row}")->getValue();
                    $colorEstado = $estado === 'Activo' ? '2D6A4F' : '7B2D2D';
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => $colorEstado],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);

                    // Tipo — centrado
                    $sheet->getStyle("E{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

                    // Borde inferior suave
                    $sheet->getStyle("A{$row}:J{$row}")->getBorders()
                        ->getBottom()
                        ->setBorderStyle(Border::BORDER_HAIR)
                        ->getColor()->setRGB('DDDDDD');

                    $sheet->getRowDimension($row)->setRowHeight(16);
                }

                // ── Fila de totales ─────────────────────────────
                $sheet->mergeCells("A{$totalRow}:E{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTALES GENERALES');
                $sheet->setCellValue("F{$totalRow}", "=SUM(F4:F{$lastRow})");
                $sheet->setCellValue("G{$totalRow}", "=SUM(G4:G{$lastRow})");

                $sheet->getStyle("A{$totalRow}:J{$totalRow}")->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 10,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2C3E50'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                    ],
                    'numberFormat' => [
                        'formatCode' => '#,##0.00',
                    ],
                ]);
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // ── Borde exterior de toda la tabla ────────────
                $sheet->getStyle("A3:J{$totalRow}")->getBorders()
                    ->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('2C3E50');

                // ── Autofilter y congelar ───────────────────────
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
                    'font' => [
                        'bold'  => true,
                        'size'  => 11,
                        'color' => ['rgb' => '1A3A5C'],
                    ],
                ]);

                $sheet->mergeCells('A2:G2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   ' . $this->totalRows . ' líneas contables'
                );
                $sheet->getStyle('A2')->getFont()
                    ->setSize(8)->setItalic(true)
                    ->getColor()->setRGB('666666');

                // Encabezado
                $sheet->getStyle('A3:G3')->applyFromArray([
                    'font' => [
                        'bold'  => true,
                        'size'  => 9,
                        'color' => ['rgb' => 'FFFFFF'],
                    ],
                    'fill' => [
                        'fillType'   => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '2C3E50'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical'   => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $sheet->getRowDimension(3)->setRowHeight(20);

                $asientoActual = '';
                for ($row = 4; $row <= $lastRow; $row++) {
                    $numero  = $sheet->getCell("A{$row}")->getValue();
                    $esNuevo = $numero !== $asientoActual;
                    $asientoActual = $numero;

                    // Primera fila de asiento gris suave, resto alternado
                    $bg = $esNuevo ? 'EEF2F7' :
                        (($row % 2 === 0) ? 'F8F9FA' : 'FFFFFF');

                    $sheet->getStyle("A{$row}:G{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bg);

                    // Número asiento — azul marino bold
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => '1A3A5C'],
                        ],
                    ]);

                    // Código cuenta — azul oscuro bold
                    $sheet->getStyle("C{$row}")->applyFromArray([
                        'font' => [
                            'bold'  => true,
                            'color' => ['rgb' => '2C3E50'],
                        ],
                    ]);

                    // Debe — verde oscuro si > 0
                    $debe = (float)$sheet->getCell("F{$row}")->getValue();
                    $sheet->getStyle("F{$row}")->applyFromArray([
                        'font' => [
                            'color' => ['rgb' => $debe > 0 ? '2D6A4F' : 'AAAAAA'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        ],
                        'numberFormat' => [
                            'formatCode' => $debe > 0 ? '#,##0.00' : '"-"',
                        ],
                    ]);

                    // Haber — rojo oscuro si > 0
                    $haber = (float)$sheet->getCell("G{$row}")->getValue();
                    $sheet->getStyle("G{$row}")->applyFromArray([
                        'font' => [
                            'color' => ['rgb' => $haber > 0 ? '7B2D2D' : 'AAAAAA'],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        ],
                        'numberFormat' => [
                            'formatCode' => $haber > 0 ? '#,##0.00' : '"-"',
                        ],
                    ]);

                    $sheet->getStyle("A{$row}:G{$row}")->getBorders()
                        ->getBottom()
                        ->setBorderStyle(Border::BORDER_HAIR)
                        ->getColor()->setRGB('DDDDDD');

                    $sheet->getRowDimension($row)->setRowHeight(15);
                }

                // Borde exterior
                $sheet->getStyle("A3:G{$lastRow}")->getBorders()
                    ->getOutline()
                    ->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('2C3E50');

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
