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
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('marca_id')->nullable()->constrained('marcas')->nullOnDelete();
                $table->foreignId('categoria_id')->nullable()->constrained('categorias_producto')->nullOnDelete();
                $table->foreignId('bodega_default_id')->nullable()->constrained('bodegas')->nullOnDelete();

                $table->string('codigo', 50);
                $table->string('nombre', 255);
                $table->text('descripcion')->nullable();
                $table->string('tipo', 30)->default('producto');
                $table->string('unidad', 20)->default('unidad');
                $table->boolean('requiere_serie')->default(false);

                $table->decimal('pvp', 14, 4)->default(0);
                $table->decimal('pvd', 14, 4)->default(0);
                $table->decimal('costo', 14, 4)->default(0);
                $table->decimal('descuento_maximo', 5, 2)->default(0);
                $table->decimal('iva_porcentaje', 5, 2)->default(15);
                $table->decimal('ice_porcentaje', 5, 2)->default(0);

                $table->decimal('stock_minimo', 12, 4)->default(0);
                $table->decimal('stock_maximo', 12, 4)->nullable();

                // FKs a plan_cuentas — tabla no existe aún
                $table->unsignedBigInteger('cuenta_inventario_id')->nullable();
                $table->unsignedBigInteger('cuenta_costo_id')->nullable();
                $table->unsignedBigInteger('cuenta_ventas_id')->nullable();

                $table->boolean('estado')->default(true);
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['empresa_id', 'codigo']);
                $table->index(['empresa_id', 'estado']);
                $table->index('marca_id');
                $table->index('categoria_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
