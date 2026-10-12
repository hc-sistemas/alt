<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Todos los empleados deben poder ver /rrhh/asistencia (para marcar entrada,
 * salida y almuerzo). Da ver+crear sobre el módulo 'asistencia' a TODOS los
 * perfiles y empresas que aún no lo tengan; no toca filas existentes.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('modulos') || !Schema::hasTable('permisos')
            || !Schema::hasTable('perfiles') || !Schema::hasTable('empresas')) {
            return;
        }

        $moduloId = DB::table('modulos')->where('clave', 'asistencia')->value('id');
        if (!$moduloId) {
            return;
        }

        foreach (DB::table('perfiles')->pluck('id') as $perfilId) {
            foreach (DB::table('empresas')->pluck('id') as $empresaId) {
                $fila = DB::table('permisos')
                    ->where(['perfil_id' => $perfilId, 'modulo_id' => $moduloId, 'empresa_id' => $empresaId]);

                if ($fila->exists()) {
                    $fila->update(['ver' => true, 'crear' => true]);
                } else {
                    DB::table('permisos')->insert([
                        'perfil_id' => $perfilId, 'modulo_id' => $moduloId, 'empresa_id' => $empresaId,
                        'ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false, 'anular' => false,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
    }
};
