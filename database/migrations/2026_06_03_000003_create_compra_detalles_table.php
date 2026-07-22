<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compra_detalles')) {
            Schema::create('compra_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('compra_id');
                $table->foreign('compra_id')
                      ->references('id')->on('compras')
                      ->onDelete('cascade');
                $table->unsignedBigInteger('producto_id')->nullable();
                // FK a productos se agrega cuando se cree el módulo de inventario
                $table->unsignedBigInteger('cuenta_id')->nullable();
                $table->foreign('cuenta_id')->references('id')->on('plan_cuentas');
                $table->string('descripcion', 500);
                $table->decimal('cantidad',         12, 4)->default(0);
                $table->decimal('precio_unitario',  14, 4)->default(0);
                $table->decimal('descuento',        14, 4)->default(0);
                $table->decimal('subtotal',         14, 4)->default(0);
                $table->decimal('porcentaje_iva',    5, 2)->default(15);
                $table->decimal('valor_iva',        14, 4)->default(0);
                $table->decimal('total',            14, 4)->default(0);
                $table->boolean('es_activo_fijo')->default(false);
                $table->unsignedBigInteger('activo_fijo_id')->nullable();
                // FK activos_fijos se agrega en módulo de activos fijos
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_detalles');
    }
};
