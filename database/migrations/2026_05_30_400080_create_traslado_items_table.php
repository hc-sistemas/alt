<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('traslado_items')) {
            Schema::create('traslado_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('traslado_id')->constrained('traslados')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos');
                $table->decimal('cantidad_enviada', 12, 4);
                $table->decimal('cantidad_recibida', 12, 4)->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();

                $table->index('traslado_id');
                $table->index('producto_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traslado_items');
    }
};
