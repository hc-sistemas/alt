<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventario_movimientos')) {
            Schema::create('inventario_movimientos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->foreignId('producto_id')->constrained('productos');
                $table->foreignId('bodega_origen_id')->nullable()->constrained('bodegas');
                $table->foreignId('bodega_destino_id')->nullable()->constrained('bodegas');
                $table->string('tipo_movimiento', 20);
                $table->string('documento_tipo', 20)->nullable();
                $table->unsignedBigInteger('documento_id')->nullable();
                $table->string('documento_numero', 50)->nullable();
                $table->decimal('cantidad', 12, 4);
                $table->decimal('costo_unitario', 14, 4)->default(0);
                $table->decimal('costo_total', 14, 4)->default(0);
                $table->string('numero_serie', 100)->nullable();
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->time('hora')->nullable();
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
                $table->string('observacion', 300)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
