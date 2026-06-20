<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bodegas')) {
            Schema::create('bodegas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo');
                $table->string('nombre', 100);
                $table->string('tipo', 30)->default('general');
                $table->boolean('es_virtual')->default(false);
                $table->boolean('estado')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bodegas');
    }
};
