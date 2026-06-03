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
                $table->id();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->foreignId('bodega_id')->constrained('bodegas')->cascadeOnDelete();
                $table->string('numero_serie', 100);
                $table->string('estado', 20)->default('disponible');
                $table->string('doc_entrada_tipo', 50)->nullable();
                $table->unsignedBigInteger('doc_entrada_id')->nullable();
                $table->string('doc_salida_tipo', 50)->nullable();
                $table->unsignedBigInteger('doc_salida_id')->nullable();
                $table->timestamps();

                $table->unique(['producto_id', 'numero_serie']);
                $table->index(['bodega_id', 'estado']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_series');
    }
};
