<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taller_ingresos')) {
            Schema::create('taller_ingresos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('cliente_id');
                $table->unsignedBigInteger('equipo_id');
                $table->unsignedInteger('usuario_id')->nullable();
                $table->date('fecha')->useCurrent();
                $table->time('hora')->useCurrent();
                $table->text('diagnostico_inicial')->nullable();
                $table->string('imagen', 500)->nullable();
                $table->string('video', 500)->nullable();
                $table->text('observaciones')->nullable();
                $table->smallInteger('estado')->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->foreign('cliente_id')->references('id')->on('clientes');
                $table->foreign('equipo_id')->references('id')->on('taller_equipos');
                $table->foreign('usuario_id')->references('id')->on('usuarios');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_ingresos');
    }
};
