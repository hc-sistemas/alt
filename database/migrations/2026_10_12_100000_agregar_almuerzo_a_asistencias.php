<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asistencias')) {
            return;
        }

        Schema::table('asistencias', function (Blueprint $table) {
            if (!Schema::hasColumn('asistencias', 'almuerzo_inicio')) {
                $table->timestamp('almuerzo_inicio')->nullable();
            }
            if (!Schema::hasColumn('asistencias', 'almuerzo_fin')) {
                $table->timestamp('almuerzo_fin')->nullable();
            }
            // Minutos totales de almuerzo del día (se fija al finalizar el break).
            if (!Schema::hasColumn('asistencias', 'almuerzo_minutos')) {
                $table->integer('almuerzo_minutos')->default(0);
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('asistencias')) {
            return;
        }

        Schema::table('asistencias', function (Blueprint $table) {
            foreach (['almuerzo_inicio', 'almuerzo_fin', 'almuerzo_minutos'] as $col) {
                if (Schema::hasColumn('asistencias', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
