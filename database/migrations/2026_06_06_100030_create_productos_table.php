<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('productos')) {
            Schema::create('productos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->foreignId('marca_id')->nullable()->constrained('marcas');
                $table->foreignId('categoria_id')->nullable()->constrained('categorias_producto');
                $table->string('codigo', 50);
                $table->string('codigo_externo', 50)->nullable();
                $table->string('nombre', 300);
                $table->text('descripcion')->nullable();
                $table->string('unidad', 20)->default('unidad');
                $table->string('tipo', 30)->default('producto');
                $table->boolean('requiere_serie')->default(false);
                $table->decimal('costo', 14, 4)->default(0);
                $table->decimal('pvp', 14, 4)->default(0);
                $table->decimal('pvd', 14, 4)->default(0);
                $table->decimal('descuento_maximo', 5, 2)->default(0);
                $table->decimal('porcentaje_iva', 5, 2)->default(15);
                $table->boolean('tiene_ice')->default(false);
                $table->decimal('porcentaje_ice', 5, 2)->default(0);
                $table->decimal('stock_minimo', 10, 2)->default(0);
                $table->decimal('stock_maximo', 10, 2)->default(0);
                $table->string('cuenta_inventario', 20)->nullable();
                $table->string('cuenta_costo_ventas', 20)->nullable();
                $table->string('cuenta_ventas', 20)->nullable();
                $table->string('ref_importacion', 100)->nullable();
                $table->boolean('estado')->default(true);
                $table->timestamps();
                $table->unique(['empresa_id', 'codigo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
