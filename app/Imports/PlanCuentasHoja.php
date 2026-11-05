<?php

namespace App\Imports;

use App\Models\PlanCuenta;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/** Filas de la primera hoja del plan de cuentas; acumula sus contadores en PlanCuentasImport. */
class PlanCuentasHoja implements ToCollection, WithHeadingRow
{
    public function __construct(private PlanCuentasImport $resumen)
    {
    }

    private const TIPOS_VALIDOS = ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto'];

    public function collection(Collection $rows): void
    {
        // El Excel debe traer las columnas Código y Nombre (el plan V4 usa «Nombre de Cuenta»): si no se reconocen,
        // se avisa en vez de omitir todas las filas en silencio.
        $primera = $rows->first();
        if ($primera !== null) {
            $tieneCodigo = isset($primera['codigo']) || isset($primera['c_digo']) || $primera->has('codigo') || $primera->has('c_digo');
            $tieneNombre = $primera->has('nombre') || $primera->has('nombre_de_cuenta') || $primera->has('nombre_cuenta');
            if (!$tieneCodigo || !$tieneNombre) {
                $this->resumen->errores[] = 'No se reconocen las columnas del archivo. Se esperan: Código, Nombre de Cuenta (o Nombre), Tipo y Permite_Asientos.';
                $this->resumen->omitidas += $rows->count();

                return;
            }
        }

        foreach ($rows as $i => $row) {
            $fila = $i + 2;

            $codigo = trim((string) ($row['codigo'] ?? $row['c_digo'] ?? ''));
            $nombre = trim(preg_replace('/\s+/u', ' ', (string) ($row['nombre'] ?? $row['nombre_de_cuenta'] ?? $row['nombre_cuenta'] ?? '')));
            $tipo   = strtolower(trim((string) ($row['tipo'] ?? '')));
            $permite = strtolower(trim((string) ($row['permite_asientos'] ?? $row['permite'] ?? 'si')));

            if (!$codigo || !$nombre) {
                $this->resumen->omitidas++;
                continue;
            }

            if (!in_array($tipo, self::TIPOS_VALIDOS)) {
                $this->resumen->errores[] = "Fila {$fila}: tipo '{$tipo}' inválido para cuenta {$codigo}.";
                $this->resumen->omitidas++;
                continue;
            }

            if (PlanCuenta::where('codigo', $codigo)->exists()) {
                $this->resumen->omitidas++;
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

            $this->resumen->creadas++;
        }
    }
}
