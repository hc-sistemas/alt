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
                $table->foreignId('producto_id')->constrained('productos');
                $table->foreignId('bodega_id')->constrained('bodegas');
                $table->decimal('cantidad', 12, 4)->default(0);
                $table->decimal('costo_promedio', 14, 4)->default(0);
                $table->timestamp('updated_at')->useCurrent();
                $table->unique(['producto_id', 'bodega_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_saldos');
    }
};
