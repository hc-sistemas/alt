<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prefactura_detalles')) {
            Schema::create('prefactura_detalles', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('prefactura_id');
                $table->unsignedInteger('producto_id')->nullable();
                $table->string('descripcion', 500);
                $table->decimal('cantidad', 12, 4)->default(1);
                $table->decimal('precio_unitario', 14, 4)->default(0);
                $table->decimal('total', 14, 4)->default(0);

                $table->foreign('prefactura_id')->references('id')->on('prefacturas')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prefactura_detalles');
    }
};
