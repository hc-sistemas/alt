<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activos_depreciaciones')) {
            Schema::create('activos_depreciaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activo_id')->constrained('activos_fijos')->cascadeOnDelete();
                $table->integer('periodo_año');
                $table->integer('periodo_mes');
                $table->decimal('monto', 14, 2);
                $table->decimal('depreciacion_acumulada_al_periodo', 14, 2);
                $table->decimal('valor_libro_al_periodo', 14, 2);
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['activo_id', 'periodo_año', 'periodo_mes']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activos_depreciaciones');
    }
};
