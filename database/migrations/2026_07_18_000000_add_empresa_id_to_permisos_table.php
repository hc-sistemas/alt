<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

            // Backfill: cada permiso existente (global) se replica por empresa
            // para no perder la configuración actual de cada perfil.
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
            Schema::table('permisos', function (Blueprint $table) {
                $table->dropUnique(['perfil_id', 'modulo_id', 'empresa_id']);
                $table->dropConstrainedForeignId('empresa_id');
                $table->unique(['perfil_id', 'modulo_id']);
            });
        }
    }
};
