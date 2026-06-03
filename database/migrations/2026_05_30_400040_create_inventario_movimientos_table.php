<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventario_movimientos')) {
            Schema::create('inventario_movimientos', function (Blueprint $table) {
                $table->id();

                // Sin FK constrained() — tabla productos no existe todavía
                $table->unsignedBigInteger('producto_id');

                $table->foreignId('bodega_id')->constrained('bodegas');
                $table->string('tipo', 30);
                $table->string('doc_tipo', 50)->nullable();
                $table->unsignedBigInteger('doc_id')->nullable();
                $table->decimal('cantidad', 12, 4);
                $table->decimal('costo_unitario', 14, 4)->nullable();
                $table->decimal('costo_total', 14, 4)->nullable();
                $table->decimal('stock_anterior', 12, 4);
                $table->decimal('stock_nuevo', 12, 4);
                $table->foreignId('usuario_id')->constrained('usuarios');
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->text('notas')->nullable();

                // Solo created_at — tabla append-only
                $table->timestamp('created_at')->useCurrent();

                $table->index(['producto_id', 'bodega_id']);
                $table->index(['doc_tipo', 'doc_id']);
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
