<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para columnas usadas frecuentemente en WHERE/JOIN/GROUP BY que
     * carecían de índice, detectado durante pruebas de volumen (FASE 4):
     * asiento_detalles.cuenta_id sin índice forzaba full table scan en cada
     * consulta de Balance de Comprobación / Estado de Resultados / Balance General.
     */
    private array $indices = [
        'asiento_detalles'       => ['cuenta_id', 'asiento_id'],
        'asientos_contables'     => ['estado'],
        'facturas'               => ['cliente_id', 'fecha_emision', 'estado'],
        'compras'                => ['proveedor_id', 'fecha_emision', 'estado'],
        'cuentas_cobrar'         => ['cliente_id', 'fecha_vencimiento', 'estado'],
        'cuentas_pagar'          => ['proveedor_id', 'fecha_vencimiento', 'estado'],
        'taller_ordenes_trabajo' => ['tecnico_id', 'fecha_inicio', 'estado'],
    ];

    public function up(): void
    {
        foreach ($this->indices as $tabla => $columnas) {
            if (!Schema::hasTable($tabla)) continue;

            foreach ($columnas as $columna) {
                if (!Schema::hasColumn($tabla, $columna)) continue;

                $nombreIndice = "idx_{$tabla}_{$columna}";
                if ($this->indiceExiste($tabla, $nombreIndice)) continue;

                Schema::table($tabla, function ($table) use ($columna, $nombreIndice) {
                    $table->index($columna, $nombreIndice);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indices as $tabla => $columnas) {
            if (!Schema::hasTable($tabla)) continue;

            foreach ($columnas as $columna) {
                $nombreIndice = "idx_{$tabla}_{$columna}";
                if (!$this->indiceExiste($tabla, $nombreIndice)) continue;

                Schema::table($tabla, function ($table) use ($nombreIndice) {
                    $table->dropIndex($nombreIndice);
                });
            }
        }
    }

    private function indiceExiste(string $tabla, string $nombreIndice): bool
    {
        $fila = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$tabla, $nombreIndice]
        );
        return $fila !== null;
    }
};
