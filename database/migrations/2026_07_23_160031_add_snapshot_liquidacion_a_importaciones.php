<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * snapshot_liquidacion guarda el estado previo a liquidar una importación
     * (costos de producto, saldos de inventario, cruces de anticipo) para que
     * ImportacionController::revertir() pueda deshacer la liquidación sin
     * recalcular a ciegas.
     */
    public function up(): void
    {
        if (Schema::hasTable('importaciones') && !Schema::hasColumn('importaciones', 'snapshot_liquidacion')) {
            Schema::table('importaciones', function (Blueprint $table) {
                $table->jsonb('snapshot_liquidacion')->nullable()->after('metodo_prorrateo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('importaciones') && Schema::hasColumn('importaciones', 'snapshot_liquidacion')) {
            Schema::table('importaciones', function (Blueprint $table) {
                $table->dropColumn('snapshot_liquidacion');
            });
        }
    }
};
