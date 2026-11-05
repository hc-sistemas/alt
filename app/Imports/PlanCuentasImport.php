<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Importa el plan de cuentas desde Excel. Solo se lee la PRIMERA hoja (el archivo del cliente trae otras hojas,
 * como la tabla de límites de descuento, que no son cuentas). Las filas las procesa PlanCuentasHoja y los
 * contadores (creadas, omitidas, errores) se acumulan aquí.
 */
class PlanCuentasImport implements WithMultipleSheets
{
    public int $creadas   = 0;
    public int $omitidas  = 0;
    public array $errores = [];

    public function sheets(): array
    {
        return [0 => new PlanCuentasHoja($this)];
    }
}
