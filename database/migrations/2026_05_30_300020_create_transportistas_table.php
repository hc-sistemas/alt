<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('transportistas')) {
            Schema::create('transportistas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('razon_social', 255);
                $table->string('ruc', 13);
                $table->string('placa', 10)->nullable();
                $table->string('contacto', 255)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transportistas');
    }
};
