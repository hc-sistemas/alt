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
                $table->string('nombre', 100);
                $table->foreignId('categoria_padre_id')->nullable()->constrained('categorias_producto');
                $table->boolean('estado')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categorias_producto');
    }
};
