<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('retencion_detalles')) {
            Schema::create('retencion_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('retencion_id');
                $table->unsignedInteger('impuesto_id')->nullable();
                $table->string('tipo', 10);
                $table->string('codigo', 10)->nullable();
                $table->decimal('porcentaje', 5, 2)->default(0);
                $table->decimal('base_imponible', 14, 4)->default(0);
                $table->decimal('valor_retenido', 14, 4)->default(0);

                $table->foreign('retencion_id')->references('id')->on('retenciones')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retencion_detalles');
    }
};
