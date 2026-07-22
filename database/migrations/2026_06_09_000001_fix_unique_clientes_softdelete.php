<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE clientes DROP CONSTRAINT IF EXISTS clientes_empresa_id_identificacion_unique');
        DB::statement('DROP INDEX IF EXISTS clientes_empresa_identificacion_active_unique');

        $exists = DB::selectOne("
            SELECT 1 FROM pg_indexes
            WHERE tablename = 'clientes'
              AND indexname = 'clientes_empresa_identificacion_unique'
        ");

        if (!$exists) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unique(['empresa_id', 'identificacion'], 'clientes_empresa_identificacion_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_empresa_identificacion_unique');
        });
    }
};
