<?php
namespace App\Exports;

use App\Exports\Concerns\EstiloAcademico;
use App\Models\CuentaPagar;
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

class CxPExport implements
    FromCollection, WithHeadings, WithStyles,
    WithColumnWidths, WithTitle, WithEvents
{
    use EstiloAcademico;

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

        if (!empty($this->filtros['periodo'])) {
            $hoy = now();
            match ($this->filtros['periodo']) {
                'hoy'    => $query->whereDate('fecha_vencimiento', $hoy),
                'semana' => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfWeek(), $hoy->copy()->endOfWeek()
                ]),
                'mes'    => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()
                ]),
                'anio'   => $query->whereBetween('fecha_vencimiento', [
                    $hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()
                ]),
                'vencidas' => $query->where('fecha_vencimiento', '<', $hoy),
                default  => null,
            };
        }
        if (!empty($this->filtros['fecha_desde'])) {
            $query->where('fecha_vencimiento', '>=', $this->filtros['fecha_desde']);
        }
        if (!empty($this->filtros['fecha_hasta'])) {
            $query->where('fecha_vencimiento', '<=', $this->filtros['fecha_hasta']);
        }
        if (!empty($this->filtros['buscar'])) {
            $q = $this->filtros['buscar'];
            $query->where(fn($qb) =>
                $qb->whereHas('proveedor', fn($p) => $p->where('razon_social', 'ilike', "%{$q}%"))
                   ->orWhereHas('compra', fn($c) => $c->where('num_documento', 'ilike', "%{$q}%"))
            );
        }

        $data = $query->orderBy('fecha_vencimiento')->get();
        $this->totalRows  = $data->count();
        $this->totalSaldo = (float) $data->sum('saldo');

        return $data->map(fn($c) => [
            $c->proveedor?->razon_social   ?? '—',
            $c->compra?->num_documento      ?? '—',
            number_format((float)$c->monto, 2),
            number_format((float)$c->saldo, 2),
            $c->fecha_emision?->format('d/m/Y')    ?? '—',
            $c->fecha_vencimiento?->format('d/m/Y') ?? '—',
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
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $lastRow  = $this->totalRows + 3;
                $totalRow = $lastRow + 1;

                $sheet->insertNewRowBefore(1, 2);

                // Título
                $sheet->mergeCells('A1:I1');
                $sheet->setCellValue('A1', 'Altamira Light & Sound — Cuentas por Pagar');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => self::H_TEXTO]],
                ]);

                $sheet->mergeCells('A2:I2');
                $sheet->setCellValue('A2',
                    'Generado: ' . now()->format('d/m/Y H:i') .
                    '   |   Pendientes: ' . $this->totalRows .
                    '   |   Saldo total: $' . number_format($this->totalSaldo, 2)
                );
                $sheet->getStyle('A2')->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => self::H_MUTED]],
                ]);

                // Encabezado fila 3
                $sheet->getStyle('A3:I3')->applyFromArray($this->estiloHeaderAcad());
                $sheet->getRowDimension(3)->setRowHeight(20);

                // Filas de datos — alternadas, sin color por urgencia
                $this->aplicarFilasAcad($sheet, 4, $lastRow, 9);

                for ($row = 4; $row <= $lastRow; $row++) {
                    // N° Documento — acento azul
                    $sheet->getStyle("B{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_ACENTO]],
                    ]);

                    // Saldo destacado (texto oscuro, bold)
                    $sheet->getStyle("D{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_TEXTO]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    ]);

                    // Monto alineado derecha
                    $sheet->getStyle("C{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                    // Urgencia — texto sin color de fondo
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => ['color' => ['rgb' => self::H_MUTED]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    // Estado — texto neutro
                    $sheet->getStyle("I{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => self::H_TEXTO]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);

                    $sheet->getRowDimension($row)->setRowHeight(17);
                }

                // Fila total
                $sheet->mergeCells("A{$totalRow}:C{$totalRow}");
                $sheet->setCellValue("A{$totalRow}", 'SALDO TOTAL PENDIENTE');
                $sheet->setCellValue("D{$totalRow}", '$' . number_format($this->totalSaldo, 2));
                $sheet->getStyle("A{$totalRow}:I{$totalRow}")->applyFromArray(
                    $this->estiloTotalAcad()
                );
                $sheet->getStyle("A{$totalRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
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
