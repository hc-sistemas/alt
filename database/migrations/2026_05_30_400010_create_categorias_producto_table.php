<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('categorias_producto')) {
            Schema::create('categorias_producto', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->nullable()->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('categorias_producto')->nullOnDelete();
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['empresa_id', 'parent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_producto');
    }
};
