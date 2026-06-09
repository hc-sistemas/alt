<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('factura_detalles')) {
            Schema::create('factura_detalles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('factura_id');
                $table->unsignedInteger('producto_id')->nullable();
                $table->string('codigo_producto', 50)->nullable();
                $table->string('descripcion', 500);
                $table->string('unidad', 20)->nullable();
                $table->decimal('cantidad', 12, 4)->default(1);
                $table->decimal('precio_unitario', 14, 4)->default(0);
                $table->decimal('descuento_pct', 5, 2)->default(0);
                $table->decimal('descuento_valor', 14, 4)->default(0);
                $table->decimal('subtotal', 14, 4)->default(0);
                $table->decimal('porcentaje_iva', 5, 2)->default(15);
                $table->decimal('valor_iva', 14, 4)->default(0);
                $table->decimal('valor_ice', 14, 4)->default(0);
                $table->decimal('total', 14, 4)->default(0);
                $table->string('numero_serie', 100)->nullable();
                $table->decimal('costo_unitario', 14, 4)->default(0);

                $table->foreign('factura_id')->references('id')->on('facturas')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_detalles');
    }
};
