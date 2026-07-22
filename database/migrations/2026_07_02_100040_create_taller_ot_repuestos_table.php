<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taller_ot_repuestos')) {
            Schema::create('taller_ot_repuestos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('orden_id');
                $table->unsignedInteger('producto_id');
                $table->string('numero_serie', 100)->nullable();
                $table->decimal('cantidad', 10, 4)->default(1);
                $table->decimal('costo_unitario', 14, 4)->default(0);
                $table->decimal('precio_venta', 14, 4)->default(0);
                $table->string('estado', 20)->default('reservado');

                $table->foreign('orden_id')->references('id')->on('taller_ordenes_trabajo')->onDelete('cascade');
                $table->foreign('producto_id')->references('id')->on('productos');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_ot_repuestos');
    }
};
