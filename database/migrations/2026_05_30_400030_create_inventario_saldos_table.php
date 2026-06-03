<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventario_saldos')) {
            Schema::create('inventario_saldos', function (Blueprint $table) {
                $table->id();

                // FK a productos sin constrained() — tabla no existe todavía
                $table->unsignedBigInteger('producto_id');

                $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();

                $table->decimal('stock_actual', 12, 4)->default(0);
                $table->decimal('stock_reservado', 12, 4)->default(0);
                $table->decimal('costo_promedio', 14, 4)->default(0);

                // Solo updated_at — sin created_at
                $table->timestamp('updated_at')->nullable()->useCurrent()->useCurrentOnUpdate();

                $table->unique(['producto_id', 'bodega_id']);
                $table->index('bodega_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_saldos');
    }
};
