<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recepciones_bodega')) {
            Schema::create('recepciones_bodega', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('compra_id');
                $table->foreign('compra_id')->references('id')->on('compras');
                $table->unsignedBigInteger('bodega_id');
                $table->foreign('bodega_id')->references('id')->on('bodegas');
                $table->string('estado', 20)->default('pendiente');
                $table->unsignedBigInteger('recibido_por')->nullable();
                $table->foreign('recibido_por')->references('id')->on('usuarios');
                $table->date('fecha_recepcion')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('recepcion_detalles')) {
            Schema::create('recepcion_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recepcion_id');
                $table->foreign('recepcion_id')->references('id')->on('recepciones_bodega')->onDelete('cascade');
                $table->unsignedBigInteger('compra_detalle_id');
                $table->foreign('compra_detalle_id')->references('id')->on('compra_detalles');
                $table->unsignedBigInteger('producto_id');
                $table->foreign('producto_id')->references('id')->on('productos');
                $table->decimal('cantidad_esperada', 12, 4);
                $table->decimal('cantidad_recibida', 12, 4)->default(0);
                $table->string('estado', 20)->default('pendiente');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_detalles');
        Schema::dropIfExists('recepciones_bodega');
    }
};
