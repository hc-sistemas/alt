<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('traslados')) {
            Schema::create('traslados', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('bodega_origen_id')->constrained('bodegas');
                $table->foreignId('bodega_destino_id')->constrained('bodegas');
                $table->string('estado', 20)->default('pendiente');
                $table->foreignId('usuario_origen_id')->constrained('usuarios');
                $table->foreignId('usuario_destino_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamp('fecha_traslado')->useCurrent();
                $table->timestamp('fecha_confirmacion')->nullable();
                $table->text('notas_origen')->nullable();
                $table->text('notas_destino')->nullable();
                $table->timestamps();

                $table->index(['empresa_id', 'estado']);
                $table->index('bodega_origen_id');
                $table->index('bodega_destino_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados');
    }
};
