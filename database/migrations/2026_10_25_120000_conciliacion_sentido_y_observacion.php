<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo C, conciliación (02-06 §2.4): cada partida guarda si es ingreso o egreso
 * (el estado de cuenta importado perdía el signo) y la conciliación guarda la
 * observación obligatoria cuando se cierra con diferencia. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partidas_transito') && !Schema::hasColumn('partidas_transito', 'sentido')) {
            Schema::table('partidas_transito', function (Blueprint $table) {
                $table->string('sentido', 10)->nullable(); // 'ingreso' | 'egreso'
            });
        }

        if (Schema::hasTable('conciliaciones_bancarias') && !Schema::hasColumn('conciliaciones_bancarias', 'observacion')) {
            Schema::table('conciliaciones_bancarias', function (Blueprint $table) {
                $table->text('observacion')->nullable();
            });
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
