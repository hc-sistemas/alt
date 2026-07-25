<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que un mismo perfil tenga permisos distintos por empresa
     * (Matriz/Import/Fix). Backfill no destructivo: cada permiso existente
     * se replica para todas las empresas antes de exigir empresa_id, así
     * ningún perfil pierde su configuración actual.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('permisos', 'empresa_id')) {
            Schema::table('permisos', function (Blueprint $table) {
                $table->foreignId('empresa_id')->nullable()->after('modulo_id')
                    ->constrained('empresas')->cascadeOnDelete();
            });

            Schema::table('permisos', function (Blueprint $table) {
                $table->dropUnique(['perfil_id', 'modulo_id']);
            });

            $empresaIds = DB::table('empresas')->pluck('id');
            $permisos = DB::table('permisos')->whereNull('empresa_id')->get();

            foreach ($empresaIds as $index => $empresaId) {
                foreach ($permisos as $permiso) {
                    if ($index === 0) {
                        // La primera empresa reutiliza la fila original en vez de duplicarla.
                        DB::table('permisos')->where('id', $permiso->id)->update(['empresa_id' => $empresaId]);
                    } else {
                        DB::table('permisos')->insert([
                            'perfil_id' => $permiso->perfil_id,
                            'modulo_id' => $permiso->modulo_id,
                            'empresa_id' => $empresaId,
                            'ver' => $permiso->ver,
                            'crear' => $permiso->crear,
                            'editar' => $permiso->editar,
                            'eliminar' => $permiso->eliminar,
                            'anular' => $permiso->anular,
                        ]);
                    }
                }
            }

            Schema::table('permisos', function (Blueprint $table) {
                $table->unique(['perfil_id', 'modulo_id', 'empresa_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('permisos', 'empresa_id')) {
            // No se puede restaurar el unique (perfil_id, modulo_id) mientras
            // existan varias filas por empresa para el mismo par — hay que
            // descartar primero las copias de todas las empresas menos la
            // primera (se pierde cualquier diferencia configurada por empresa,
            // que es el trade-off esperado de revertir este cambio).
            $primeraEmpresaId = DB::table('empresas')->min('id');
            if ($primeraEmpresaId !== null) {
                DB::table('permisos')->where('empresa_id', '!=', $primeraEmpresaId)->delete();
            }

            Schema::table('permisos', function (Blueprint $table) {
                $table->dropUnique(['perfil_id', 'modulo_id', 'empresa_id']);
                $table->dropConstrainedForeignId('empresa_id');
                $table->unique(['perfil_id', 'modulo_id']);
            });
        }
    }
};
