<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `observacion` quedó como columna huérfana tras alinear inventario_movimientos
 * a `notas` (ver 2026_07_21_000002). Ningún código la usa ya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_movimientos') && Schema::hasColumn('inventario_movimientos', 'observacion')) {
            Schema::table('inventario_movimientos', function (Blueprint $table) {
                $table->dropColumn('observacion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventario_movimientos') && !Schema::hasColumn('inventario_movimientos', 'observacion')) {
            Schema::table('inventario_movimientos', function (Blueprint $table) {
                $table->string('observacion', 300)->nullable();
            });
        }
    }
};
