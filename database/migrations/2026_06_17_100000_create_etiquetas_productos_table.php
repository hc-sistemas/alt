<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('etiquetas_productos')) return;

        Schema::create('etiquetas_productos', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('empresa_id');
            $table->unsignedInteger('compra_id');
            $table->unsignedInteger('compra_detalle_id')->nullable();
            $table->unsignedInteger('producto_id');
            $table->string('codigo_producto', 50);
            $table->unsignedInteger('correlativo_desde');
            $table->unsignedInteger('correlativo_hasta');
            $table->unsignedInteger('cantidad');
            $table->unsignedInteger('generado_por')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('empresa_id')->references('id')->on('empresas');
            $table->foreign('compra_id')->references('id')->on('compras');
            $table->foreign('producto_id')->references('id')->on('productos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiquetas_productos');
    }
};
