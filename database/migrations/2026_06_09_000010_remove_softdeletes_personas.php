<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop partial unique indexes created for soft-delete workaround
        DB::statement('DROP INDEX IF EXISTS clientes_empresa_identificacion_active_unique');
        DB::statement('DROP INDEX IF EXISTS proveedores_empresa_identificacion_active_unique');

        // Restore plain unique constraints
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS clientes_empresa_id_identificacion_unique
            ON clientes (empresa_id, identificacion)
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS proveedores_empresa_id_identificacion_unique
            ON proveedores (empresa_id, identificacion)
        ');

        // Remove deleted_at columns
        if (Schema::hasColumn('clientes', 'deleted_at')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }

        if (Schema::hasColumn('proveedores', 'deleted_at')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }

        if (Schema::hasColumn('transportistas', 'deleted_at')) {
            Schema::table('transportistas', function (Blueprint $table) {
                $table->dropColumn('deleted_at');
            });
        }
    }

    public function down(): void
    {
        // Restore soft-delete columns
        if (!Schema::hasColumn('clientes', 'deleted_at')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('proveedores', 'deleted_at')) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        if (!Schema::hasColumn('transportistas', 'deleted_at')) {
            Schema::table('transportistas', function (Blueprint $table) {
                $table->softDeletes();
            });
        }

        // Restore partial unique indexes
        DB::statement('DROP INDEX IF EXISTS clientes_empresa_id_identificacion_unique');
        DB::statement('DROP INDEX IF EXISTS proveedores_empresa_id_identificacion_unique');

        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS clientes_empresa_identificacion_active_unique
            ON clientes (empresa_id, identificacion)
            WHERE deleted_at IS NULL
        ');
        DB::statement('
            CREATE UNIQUE INDEX IF NOT EXISTS proveedores_empresa_identificacion_active_unique
            ON proveedores (empresa_id, identificacion)
            WHERE deleted_at IS NULL
        ');
    }
};
