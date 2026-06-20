<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conciliaciones_bancarias')) {
            Schema::create('conciliaciones_bancarias', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('banco_caja_id');
                $table->foreign('banco_caja_id')->references('id')->on('bancos_cajas');
                $table->date('fecha_corte');
                $table->decimal('saldo_banco',   14, 4)->default(0);
                $table->decimal('saldo_sistema', 14, 4)->default(0);
                $table->decimal('diferencia',    14, 4)->default(0);
                $table->string('descripcion', 300)->nullable();
                $table->string('archivo_csv', 500)->nullable();
                $table->string('estado', 20)->default('pendiente');
                // 'pendiente','conciliada'
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('partidas_transito')) {
            Schema::create('partidas_transito', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conciliacion_id');
                $table->foreign('conciliacion_id')
                      ->references('id')->on('conciliaciones_bancarias')
                      ->onDelete('cascade');
                $table->string('tipo', 20)->nullable();
                // 'sistema','banco'
                $table->date('fecha')->nullable();
                $table->string('descripcion', 300)->nullable();
                $table->decimal('monto', 14, 4)->nullable();
                $table->unsignedBigInteger('movimiento_id')->nullable();
                $table->foreign('movimiento_id')
                      ->references('id')->on('movimientos_bancarios');
                $table->boolean('conciliada')->default(false);
                $table->unsignedBigInteger('asiento_generado_id')->nullable();
                $table->foreign('asiento_generado_id')
                      ->references('id')->on('asientos_contables');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas_transito');
        Schema::dropIfExists('conciliaciones_bancarias');
    }
};
