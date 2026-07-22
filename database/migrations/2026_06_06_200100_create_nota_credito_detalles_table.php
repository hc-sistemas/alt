<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nota_credito_detalles')) {
            Schema::create('nota_credito_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('nota_credito_id');
                $table->unsignedInteger('producto_id')->nullable();
                $table->string('descripcion', 500);
                $table->decimal('cantidad', 12, 4)->default(1);
                $table->decimal('precio_unitario', 14, 4)->default(0);
                $table->decimal('total', 14, 4)->default(0);
                $table->unsignedInteger('bodega_destino_id')->nullable();
                $table->string('numero_serie', 100)->nullable();

                $table->foreign('nota_credito_id')->references('id')->on('notas_credito')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_credito_detalles');
    }
};
