<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración contable compartida entre las dos empresas.
 *
 * El plan de cuentas ya es único (plan_cuentas.empresa_id es NULL). Los
 * parámetros (evento → cuenta) también pasan a serlo: una fila con
 * empresa_id NULL vale para todas las empresas, y una fila con empresa_id
 * actúa como excepción solo para esa empresa. El buscador mira primero la
 * excepción y luego la general (ParametroContable::getCuentaId).
 *
 * Idempotente: puede correr más de una vez sin error ni duplicados.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parametros_contables')) {
            return;
        }

        DB::transaction(function () {
            DB::statement('ALTER TABLE parametros_contables ALTER COLUMN empresa_id DROP NOT NULL');

            // El unique (empresa_id, codigo) no frena duplicados cuando empresa_id
            // es NULL; este índice parcial asegura una sola fila general por parámetro.
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS parametros_contables_codigo_general_unique '
                . 'ON parametros_contables (codigo) WHERE empresa_id IS NULL'
            );

            $this->consolidarEnGeneral();
        });
    }

    /**
     * Cada parámetro configurado por empresa pasa a ser general, tomando la
     * cuenta de la empresa con menor id (UIO). Las filas de otra empresa que
     * ya coinciden con la general se eliminan; las que difieren quedan como
     * excepción de esa empresa.
     */
    private function consolidarEnGeneral(): void
    {
        $porEmpresa = DB::table('parametros_contables')
            ->whereNotNull('empresa_id')
            ->orderBy('empresa_id')
            ->orderBy('id')
            ->get()
            ->groupBy('codigo');

        foreach ($porEmpresa as $codigo => $filas) {
            $general = DB::table('parametros_contables')
                ->whereNull('empresa_id')
                ->where('codigo', $codigo)
                ->first();

            if (!$general) {
                $base = $filas->first();
                DB::table('parametros_contables')->insert([
                    'empresa_id'  => null,
                    'codigo'      => $codigo,
                    'cuenta_id'   => $base->cuenta_id,
                    'descripcion' => $base->descripcion,
                ]);
                $cuentaGeneral = $base->cuenta_id;
            } else {
                $cuentaGeneral = $general->cuenta_id;
            }

            $iguales = $filas->where('cuenta_id', $cuentaGeneral)->pluck('id')->all();
            if ($iguales) {
                DB::table('parametros_contables')->whereIn('id', $iguales)->delete();
            }
        }
    }

    public function down(): void
    {
        // Sin reversión: los parámetros por empresa originales no se conservan.
    }
};
