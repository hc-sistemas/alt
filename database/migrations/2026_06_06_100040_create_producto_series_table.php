<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('producto_series')) {
            Schema::create('producto_series', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('producto_id')->constrained('productos');
                $table->foreignId('bodega_id')->constrained('bodegas');
                $table->string('numero_serie', 100);
                $table->string('estado', 20)->default('disponible');
                $table->unsignedBigInteger('factura_compra_id')->nullable();
                $table->unsignedBigInteger('factura_venta_id')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->unique('numero_serie');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_series');
    }
};
