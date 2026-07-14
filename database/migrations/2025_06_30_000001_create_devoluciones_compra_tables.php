<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devoluciones_compra')) {
            Schema::create('devoluciones_compra', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('compra_id')->nullable();
                $table->unsignedBigInteger('proveedor_id');
                $table->string('num_documento', 30)->nullable();
                $table->date('fecha');
                $table->string('motivo', 300);
                $table->string('estado', 20)->default('pendiente');
                $table->decimal('subtotal', 12, 4)->default(0);
                $table->decimal('iva',      12, 4)->default(0);
                $table->decimal('total',    12, 4)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')  ->references('id')->on('empresas');
                $table->foreign('proveedor_id')->references('id')->on('proveedores');
                $table->foreign('compra_id')  ->references('id')->on('compras')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('devoluciones_compra_detalles')) {
            Schema::create('devoluciones_compra_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('devolucion_id');
                $table->unsignedBigInteger('producto_id')->nullable();
                $table->string('descripcion', 200);
                $table->decimal('cantidad',        12, 4);
                $table->decimal('precio_unitario', 12, 4);
                $table->decimal('subtotal',        12, 4);

                $table->foreign('devolucion_id')->references('id')->on('devoluciones_compra')->cascadeOnDelete();
                $table->foreign('producto_id')  ->references('id')->on('productos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('devoluciones_compra_detalles');
        Schema::dropIfExists('devoluciones_compra');
    }
};
