<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datafast_lotes')) {
            Schema::create('datafast_lotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('banco_caja_id');
                $table->foreign('banco_caja_id')->references('id')->on('bancos_cajas');
                $table->string('numero_lote', 50);
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->decimal('total_vouchers', 14, 4)->default(0);
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->foreign('asiento_id')->references('id')->on('asientos_contables');
                $table->string('estado', 20)->default('pendiente');
                // 'pendiente','liquidado'
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('datafast_lotes');
    }
};
