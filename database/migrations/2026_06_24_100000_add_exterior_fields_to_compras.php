<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            if (!Schema::hasColumn('compras', 'metodo_envio')) {
                $table->string('metodo_envio', 10)->nullable()->after('importacion_id');
            }
            if (!Schema::hasColumn('compras', 'divisa')) {
                $table->string('divisa', 10)->nullable()->default('USD')->after('metodo_envio');
            }
            if (!Schema::hasColumn('compras', 'tipo_cambio')) {
                $table->decimal('tipo_cambio', 10, 4)->nullable()->after('divisa');
            }
            if (!Schema::hasColumn('compras', 'num_orden_compra')) {
                $table->string('num_orden_compra', 50)->nullable()->after('tipo_cambio');
            }
            if (!Schema::hasColumn('compras', 'num_contrato')) {
                $table->string('num_contrato', 50)->nullable()->after('num_orden_compra');
            }
            if (!Schema::hasColumn('compras', 'vigencia_desde')) {
                $table->date('vigencia_desde')->nullable()->after('num_contrato');
            }
            if (!Schema::hasColumn('compras', 'vigencia_hasta')) {
                $table->date('vigencia_hasta')->nullable()->after('vigencia_desde');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            foreach (['metodo_envio', 'divisa', 'tipo_cambio', 'num_orden_compra',
                      'num_contrato', 'vigencia_desde', 'vigencia_hasta'] as $col) {
                if (Schema::hasColumn('compras', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
