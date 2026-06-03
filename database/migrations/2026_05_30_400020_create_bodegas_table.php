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
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
                $table->string('nombre', 150);
                $table->string('tipo', 50)->default('general');
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->unique(['empresa_id', 'nombre']);
                $table->index('tipo');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bodegas');
    }
};
