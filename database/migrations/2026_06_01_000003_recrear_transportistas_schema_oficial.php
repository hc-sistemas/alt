<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('transportistas');
        Schema::create('transportistas', function (Blueprint $table) {
            $table->id();
            $table->string('identificacion', 20)->nullable();
            $table->string('razon_social', 200);
            $table->string('placa', 20)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transportistas');
    }
};
