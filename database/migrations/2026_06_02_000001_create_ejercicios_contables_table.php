<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ejercicios_contables')) {
            Schema::create('ejercicios_contables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->smallInteger('anio');
                $table->smallInteger('mes');
                $table->string('descripcion', 100)->nullable();
                $table->date('fecha_apertura')->nullable();
                $table->date('fecha_cierre')->nullable();
                $table->unsignedBigInteger('cerrado_por')->nullable();
                $table->foreign('cerrado_por')->references('id')->on('usuarios');
                $table->string('estado', 20)->default('abierto');
                $table->timestamp('created_at')->useCurrent();
                // SIN updated_at — según schema
                $table->unique(['empresa_id', 'anio', 'mes']);
            });

            DB::statement('ALTER TABLE ejercicios_contables
                ADD CONSTRAINT chk_mes_valido
                CHECK (mes BETWEEN 1 AND 12)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ejercicios_contables');
    }
};
