<?php

namespace App\Imports;

use App\Models\PlanCuenta;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class PlanCuentasImport implements ToCollection, WithHeadingRow
{
    public int $creadas   = 0;
    public int $omitidas  = 0;
    public array $errores = [];

    private const TIPOS_VALIDOS = ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto'];

    public function collection(Collection $rows): void
    {
        foreach ($rows as $i => $row) {
            $fila = $i + 2;

            $codigo = trim((string) ($row['codigo'] ?? $row['c_digo'] ?? ''));
            $nombre = trim((string) ($row['nombre'] ?? ''));
            $tipo   = strtolower(trim((string) ($row['tipo'] ?? '')));
            $permite = strtolower(trim((string) ($row['permite_asientos'] ?? $row['permite'] ?? 'si')));

            if (!$codigo || !$nombre) {
                $this->omitidas++;
                continue;
            }

            if (!in_array($tipo, self::TIPOS_VALIDOS)) {
                $this->errores[] = "Fila {$fila}: tipo '{$tipo}' inválido para cuenta {$codigo}.";
                $this->omitidas++;
                continue;
            }

            if (PlanCuenta::where('codigo', $codigo)->exists()) {
                $this->omitidas++;
                continue;
            }

            $nivel   = substr_count($codigo, '.') + 1;
            $padreId = null;

            if ($nivel > 1) {
                $codigoPadre = implode('.', array_slice(explode('.', $codigo), 0, -1));
                $padre = PlanCuenta::where('codigo', $codigoPadre)->first();
                $padreId = $padre?->id;
            }

            $permiteAsientos = in_array($permite, ['si', 's', '1', 'true', 'yes', 'x']);

            PlanCuenta::create([
                'codigo'           => $codigo,
                'nombre'           => $nombre,
                'tipo'             => $tipo,
                'nivel'            => $nivel,
                'padre_id'         => $padreId,
                'permite_asientos' => $permiteAsientos,
                'estado'           => true,
                'total_asientos'   => 0,
            ]);

            $this->creadas++;
        }
    }
}
