<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cheques')) {
            Schema::create('cheques', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('banco_caja_id')->nullable();
                $table->foreign('banco_caja_id')->references('id')->on('bancos_cajas');
                $table->unsignedBigInteger('movimiento_id')->nullable();
                $table->foreign('movimiento_id')
                      ->references('id')->on('movimientos_bancarios');
                $table->string('numero', 20);
                $table->string('banco', 100)->nullable();
                $table->string('cuenta', 30)->nullable();
                $table->decimal('monto', 14, 4);
                $table->date('fecha_emision')->nullable();
                $table->date('fecha_cobro')->nullable();
                $table->string('beneficiario', 200)->nullable();
                $table->string('estado', 20)->default('emitido');
                // 'emitido','cobrado','protestado','anulado'
                $table->string('observacion', 300)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cheques');
    }
};
