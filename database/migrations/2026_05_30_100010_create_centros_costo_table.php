<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('centros_costo')) {
                    Schema::create('centros_costo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo', 20)->unique();
            $table->enum('tipo', ['empresa', 'sucursal', 'centro_costo_interno'])->default('empresa');
            $table->boolean('es_taller')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('centros_costo');
    }
};
