<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('guia_remision_detalles')) {
            Schema::create('guia_remision_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('guia_id');
                $table->unsignedInteger('producto_id')->nullable();
                $table->string('descripcion', 500);
                $table->decimal('cantidad', 12, 4)->default(1);
                $table->string('numero_serie', 100)->nullable();

                $table->foreign('guia_id')->references('id')->on('guias_remision')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guia_remision_detalles');
    }
};
