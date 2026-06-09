<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proforma_detalles')) {
            Schema::create('proforma_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('proforma_id');
                $table->unsignedInteger('producto_id')->nullable();
                $table->string('descripcion', 500);
                $table->decimal('cantidad', 12, 4)->default(1);
                $table->decimal('precio_unitario', 14, 4)->default(0);
                $table->decimal('descuento_pct', 5, 2)->default(0);
                $table->decimal('subtotal', 14, 4)->default(0);
                $table->decimal('porcentaje_iva', 5, 2)->default(15);
                $table->decimal('total', 14, 4)->default(0);

                $table->foreign('proforma_id')->references('id')->on('proformas')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proforma_detalles');
    }
};
