<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cierres_caja')) {
            Schema::create('cierres_caja', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('banco_caja_id');
                $table->foreign('banco_caja_id')->references('id')->on('bancos_cajas');
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->foreign('centro_costo_id')->references('id')->on('centros_costo');
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->unsignedBigInteger('usuario_apertura_id')->nullable();
                $table->foreign('usuario_apertura_id')->references('id')->on('usuarios');
                $table->unsignedBigInteger('usuario_cierre_id')->nullable();
                $table->foreign('usuario_cierre_id')->references('id')->on('usuarios');
                $table->decimal('monto_inicial',        14, 4)->default(0);
                $table->decimal('total_facturado',      14, 4)->default(0);
                $table->decimal('total_cobrado',        14, 4)->default(0);
                $table->decimal('total_efectivo',       14, 4)->default(0);
                $table->decimal('total_tarjeta',        14, 4)->default(0);
                $table->decimal('total_cheque',         14, 4)->default(0);
                $table->decimal('total_transferencia',  14, 4)->default(0);
                $table->decimal('total_notas_credito',  14, 4)->default(0);
                $table->decimal('diferencia',           14, 4)->default(0);
                $table->text('observaciones')->nullable();
                $table->string('estado', 20)->default('abierto');
                // 'abierto','cerrado'
                $table->timestamp('hora_apertura')->nullable();
                $table->timestamp('hora_cierre')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_caja');
    }
};
