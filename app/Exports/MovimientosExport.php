<?php

namespace App\Exports;

use App\Exports\Concerns\EstiloAcademico;
use App\Models\MovimientoBancario;
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

class MovimientosExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    use EstiloAcademico;

    private int   $totalRows  = 0;
    private float $totalMonto = 0;

    public function __construct(
        private int   $empresaId,
        private array $filtros = []
    ) {}

    public function collection()
    {
        $query = MovimientoBancario::with(['bancoCaja'])
            ->where('empresa_id', $this->empresaId)
            ->where('anulado', false);

        if (!empty($this->filtros['banco_caja_id'])) {
            $query->where('banco_caja_id', $this->filtros['banco_caja_id']);
        }
        if (!empty($this->filtros['tipo'])) {
            $query->where('tipo', $this->filtros['tipo']);
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha', '<=', $this->filtros['fecha_hasta']);
        }
        if (!empty($this->filtros['buscar'])) {
            $q = $this->filtros['buscar'];
            $query->where(fn($qb) =>
                $qb->where('descripcion',    'ilike', "%{$q}%")
                   ->orWhere('beneficiario', 'ilike', "%{$q}%")
                   ->orWhere('num_documento','ilike', "%{$q}%")
            );
        }

        $data = $query->orderByDesc('fecha')->orderByDesc('id')->get();

        $this->totalRows  = $data->count();
        $this->totalMonto = (float) $data->sum('monto');

        $subTipo = [
            'transferencia' => 'Transferencia',
            'cheque'        => 'Cheque',
            'efectivo'      => 'Efectivo',
            'deposito'      => 'Depósito',
        ];

        return $data->map(fn($m) => [
            $m->fecha?->format('d/m/Y') ?? '',
            $m->bancoCaja?->nombre ?? '—',
            ucfirst($m->tipo),
            $subTipo[$m->sub_tipo] ?? ($m->sub_tipo ?? '—'),
            $m->descripcion ?? '—',
            $m->beneficiario ?? '—',
            $m->num_documento ?? '—',
            (float) $m->monto,
            $m->conciliado ? 'Conciliado' : 'Pendiente',
        ]);
    }

    public function headings(): array
    {
        return [
            'Fecha', 'Banco / Caja', 'Tipo', 'Sub-tipo',
            'Descripción', 'Beneficiario', 'N° Documento', 'Monto', 'Estado',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 28, 'C' => 12, 'D' => 16,
            'E' => 36, 'F' => 28, 'G' => 18, 'H' => 14, 'I' => 14,
        ];
    }

    public function title(): string { return 'Movimientos Bancarios'; }

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
                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', 'Altamira Light & Sound — Movimientos Bancarios');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::H_TEXTO]],
                ]);

                $sheet->mergeCells('A2:I2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   Total: ' . $this->totalRows . ' movimientos' .
                    '   |   Monto total: $' . number_format($this->totalMonto, 2)
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => self::H_MUTED]],
                ]);

                // Encabezado fila 3
                $sheet->getStyle('A3:I3')->applyFromArray($this->estiloHeaderAcad());
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Filas de datos
                $this->aplicarFilasAcad($sheet, 4, $lastRow, 9);

                for ($row = 4; $row <= $lastRow; $row++) {
                    // Fecha — mono
                    $sheet->getStyle("A{$row}")->applyFromArray([
                        'font' => ['color' => ['rgb' => self::H_MUTED]],
                    ]);

                    // Tipo — centrado
                    $sheet->getStyle("C{$row}")->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Monto — derecha, formato numérico
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'alignment'    => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                        'numberFormat' => ['formatCode' => '#,##0.00'],
                        'font'         => ['color' => ['rgb' => self::H_TEXTO]],
                    ]);

                    // Estado — centrado
                    $sheet->getStyle("I{$row}")->applyFromArray([
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                }

                // Fila total
                $sheet->mergeCells("A{$totalRow}:G{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'TOTAL MOVIMIENTOS');
                $sheet->setCellValue("H{$totalRow}", "=SUM(H4:H{$lastRow})");
                $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray(
                    $this->estiloTotalAcad()
                );
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle("H{$totalRow}")->getNumberFormat()
                    ->setFormatCode('#,##0.00');
                $sheet->getRowDimension($totalRow)->setRowHeight(20);

                // Borde exterior
                $sheet->getStyle("A3:I{$totalRow}")->getBorders()
                    ->getOutline()->setBorderStyle(Border::BORDER_MEDIUM)
                    ->getColor()->setRGB(self::H_NAVY);

                $sheet->setAutoFilter("A3:I{$lastRow}");
                $sheet->freezePane('A4');
            },
        ];
    }
}
