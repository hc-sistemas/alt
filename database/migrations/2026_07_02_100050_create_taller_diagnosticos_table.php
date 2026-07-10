<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taller_diagnosticos')) {
            Schema::create('taller_diagnosticos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedInteger('tecnico_id')->nullable();
                $table->date('fecha')->useCurrent();
                $table->time('hora')->useCurrent();
                $table->text('diagnostico');
                $table->smallInteger('tiempo_estimado')->nullable();
                $table->string('tipo_tiempo', 20)->nullable();
                $table->boolean('cliente_aprueba')->nullable();
                $table->timestamp('fecha_aprobacion')->nullable();
                $table->text('observacion_aprobacion')->nullable();
                $table->unsignedInteger('usuario_aprobacion_id')->nullable();
                $table->string('estado', 20)->default('pendiente');

                $table->foreign('orden_id')->references('id')->on('taller_ordenes_trabajo');
                $table->foreign('tecnico_id')->references('id')->on('usuarios');
                $table->foreign('usuario_aprobacion_id')->references('id')->on('usuarios');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_diagnosticos');
    }
};
