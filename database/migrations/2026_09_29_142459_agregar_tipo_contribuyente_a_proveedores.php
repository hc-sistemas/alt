<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Selector de tipo de contribuyente en Proveedor (CHECKLIST_ERRORES_COMPLICACIONES.md,
 * ítem E1): No obligado / Obligado a llevar contabilidad / Sociedad /
 * Contribuyente especial / Gran contribuyente especial. Decisión: los
 * proveedores que ya existen quedan en 'no_obligado' (el valor más
 * conservador); de ahí en adelante se revisa caso por caso al crear uno nuevo.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('proveedores')) {
            return;
        }

        if (!Schema::hasColumn('proveedores', 'tipo_contribuyente')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->string('tipo_contribuyente', 30)->nullable()->after('tipo_identificacion');
            });
        }

        DB::table('proveedores')->whereNull('tipo_contribuyente')->update(['tipo_contribuyente' => 'no_obligado']);
    }

    public function down(): void
    {
        if (Schema::hasTable('proveedores') && Schema::hasColumn('proveedores', 'tipo_contribuyente')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->dropColumn('tipo_contribuyente');
            });
        }
    }
};
