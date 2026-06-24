<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_saldos') && !Schema::hasColumn('inventario_saldos', 'cantidad_reservada')) {
            Schema::table('inventario_saldos', function (Blueprint $table) {
                $table->decimal('cantidad_reservada', 12, 4)->default(0)->after('cantidad');
            });
        }

        if (Schema::hasTable('inventario_movimientos') && !Schema::hasColumn('inventario_movimientos', 'liberado_at')) {
            Schema::table('inventario_movimientos', function (Blueprint $table) {
                $table->timestamp('liberado_at')->nullable()->default(null)->after('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('inventario_saldos', 'cantidad_reservada')) {
            Schema::table('inventario_saldos', function (Blueprint $table) {
                $table->dropColumn('cantidad_reservada');
            });
        }

        if (Schema::hasColumn('inventario_movimientos', 'liberado_at')) {
            Schema::table('inventario_movimientos', function (Blueprint $table) {
                $table->dropColumn('liberado_at');
            });
        }
    }
};
