<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taller_ordenes_trabajo')) {
            Schema::create('taller_ordenes_trabajo', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedBigInteger('ingreso_id');
                $table->unsignedInteger('tecnico_id')->nullable();
                $table->string('numero', 20)->nullable();
                $table->date('fecha_inicio')->useCurrent();
                $table->time('hora_inicio')->useCurrent();
                $table->date('fecha_fin_estimada')->nullable();
                $table->date('fecha_fin_real')->nullable();
                $table->smallInteger('tipo_orden')->default(1);
                $table->text('descripcion_trabajo')->nullable();
                $table->decimal('costo_mano_obra', 12, 2)->default(0);
                $table->decimal('costo_repuestos', 12, 2)->default(0);
                $table->decimal('costo_total', 12, 2)->default(0);
                $table->boolean('es_garantia')->default(false);
                $table->unsignedInteger('factura_id')->nullable();
                $table->unsignedInteger('asiento_id')->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->text('observaciones')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->foreign('ingreso_id')->references('id')->on('taller_ingresos');
                $table->foreign('tecnico_id')->references('id')->on('usuarios');
                $table->foreign('factura_id')->references('id')->on('facturas');
                $table->foreign('asiento_id')->references('id')->on('asientos_contables');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_ordenes_trabajo');
    }
};
