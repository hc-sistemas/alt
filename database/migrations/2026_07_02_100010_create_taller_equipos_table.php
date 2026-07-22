<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taller_equipos')) {
            Schema::create('taller_equipos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tipo_id')->nullable();
                $table->string('marca', 100)->nullable();
                $table->string('modelo', 100)->nullable();
                $table->string('numero_serie', 100)->nullable();
                $table->string('color', 50)->nullable();
                $table->string('medida', 50)->nullable();
                $table->string('adicional', 200)->nullable();
                $table->text('observaciones')->nullable();
                $table->smallInteger('estado')->default(1);
                $table->timestamps();

                $table->foreign('tipo_id')->references('id')->on('taller_tipos_equipo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_equipos');
    }
};
