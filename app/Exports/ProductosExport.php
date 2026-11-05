<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Listado de productos tal como se filtró en pantalla (todas las filas, no solo la página). */
class ProductosExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(private Collection $filas) {}

    public function collection(): Collection
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return ['Código', 'Nombre', 'Marca comercial', 'Marca', 'Categoría', 'Subcategoría', 'Tipo', 'PVP', 'PVD', 'Costo', 'Estado'];
    }

    public function styles(Worksheet $hoja): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
