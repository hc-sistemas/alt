<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('modulos')) {
                    Schema::create('modulos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 50)->unique();
            $table->string('clave', 30)->unique()->comment('slug: ventas, compras, inventario...');
            $table->string('icono', 50)->nullable()->comment('Nombre del ícono Lucide');
            $table->integer('orden')->default(0);
            $table->foreignId('padre_id')->nullable()->constrained('modulos')->nullOnDelete();
            $table->boolean('estado')->default(true);
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('modulos');
    }
};
