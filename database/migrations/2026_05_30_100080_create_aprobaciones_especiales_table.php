<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('aprobaciones_especiales')) {
                    Schema::create('aprobaciones_especiales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_aprobacion_id')->constrained('tipos_aprobacion');
            $table->foreignId('aprobado_por')->constrained('usuarios');
            $table->foreignId('solicitado_por')->constrained('usuarios');
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('tabla_referencia')->nullable();
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('valor_aprobado', 10, 2)->nullable();
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aprobaciones_especiales');
    }
};
