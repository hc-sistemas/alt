<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('traslado_detalles')) {
            Schema::create('traslado_detalles', function (Blueprint $table) {
                $table->id();
                $table->foreignId('traslado_id')->constrained('traslados_bodega')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos');
                $table->string('numero_serie', 100)->nullable();
                $table->decimal('cantidad_enviada', 12, 4);
                $table->decimal('cantidad_recibida', 12, 4)->default(0);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traslado_detalles');
    }
};
