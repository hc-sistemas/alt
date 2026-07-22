<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE proveedores DROP CONSTRAINT IF EXISTS proveedores_empresa_id_identificacion_unique');
        DB::statement('DROP INDEX IF EXISTS proveedores_empresa_identificacion_active_unique');

        $exists = DB::selectOne("
            SELECT 1 FROM pg_indexes
            WHERE tablename = 'proveedores'
              AND indexname = 'proveedores_empresa_identificacion_unique'
        ");

        if (!$exists) {
            Schema::table('proveedores', function (Blueprint $table) {
                $table->unique(['empresa_id', 'identificacion'], 'proveedores_empresa_identificacion_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropUnique('proveedores_empresa_identificacion_unique');
        });
    }
};
