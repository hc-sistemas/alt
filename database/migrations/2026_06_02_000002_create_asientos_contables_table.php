<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('asientos_contables')) {
            Schema::create('asientos_contables', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('ejercicio_id')->nullable();
                $table->foreign('ejercicio_id')->references('id')->on('ejercicios_contables');
                $table->string('numero', 20);
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->string('concepto', 500);
                $table->string('documento_tipo', 20)->nullable();
                $table->integer('documento_id')->nullable();
                $table->string('documento_ref', 50)->nullable();
                $table->decimal('total_debe', 14, 4)->default(0);
                $table->decimal('total_haber', 14, 4)->default(0);
                $table->boolean('es_automatico')->default(true);
                $table->smallInteger('estado')->default(1);
                $table->unsignedBigInteger('creado_por')->nullable();
                $table->foreign('creado_por')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
                // SIN updated_at — registro contable inmutable
            });

            Schema::table('asientos_contables', function (Blueprint $table) {
                $table->index('empresa_id',   'idx_asientos_empresa');
                $table->index('fecha',        'idx_asientos_fecha');
                $table->index('ejercicio_id', 'idx_asientos_ejercicio');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asientos_contables');
    }
};
