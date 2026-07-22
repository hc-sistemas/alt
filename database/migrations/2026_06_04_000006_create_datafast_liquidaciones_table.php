<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datafast_liquidaciones')) {
            Schema::create('datafast_liquidaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('lote_id');
                $table->foreign('lote_id')->references('id')->on('datafast_lotes');
                $table->date('fecha_deposito');
                $table->decimal('valor_bruto',       14, 4)->default(0);
                $table->decimal('comision_datafast', 14, 4)->default(0);
                $table->decimal('retencion_iva',     14, 4)->default(0);
                $table->decimal('retencion_ir',      14, 4)->default(0);
                $table->decimal('valor_neto',        14, 4)->default(0);
                $table->unsignedBigInteger('banco_destino_id')->nullable();
                $table->foreign('banco_destino_id')->references('id')->on('bancos_cajas');
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->foreign('asiento_id')->references('id')->on('asientos_contables');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('datafast_liquidaciones');
    }
};
