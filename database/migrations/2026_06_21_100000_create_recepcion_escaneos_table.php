<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('recepcion_escaneos')) {
            Schema::create('recepcion_escaneos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('recepcion_id');
                $table->foreign('recepcion_id')->references('id')->on('recepciones_bodega')->onDelete('cascade');
                $table->unsignedBigInteger('recepcion_detalle_id');
                $table->foreign('recepcion_detalle_id')->references('id')->on('recepcion_detalles')->onDelete('cascade');
                $table->unsignedBigInteger('producto_id');
                $table->foreign('producto_id')->references('id')->on('productos');
                $table->string('codigo_escaneado', 100);
                $table->integer('correlativo');
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->foreign('usuario_id')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(['recepcion_id', 'codigo_escaneado']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('recepcion_escaneos');
    }
};
