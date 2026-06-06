<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('anticipos_proveedores')) {
            Schema::create('anticipos_proveedores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('proveedor_id');
                $table->foreign('proveedor_id')->references('id')->on('proveedores');
                $table->unsignedBigInteger('importacion_id')->nullable();
                $table->foreign('importacion_id')->references('id')->on('importaciones');
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->decimal('monto', 14, 4);
                $table->decimal('saldo', 14, 4);
                $table->unsignedBigInteger('banco_id')->nullable();
                // FK a bancos_cajas se agrega en módulo de bancos
                $table->string('num_transferencia', 50)->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->foreign('asiento_id')->references('id')->on('asientos_contables');
                $table->string('estado', 20)->default('pendiente');
                $table->timestamp('created_at')->useCurrent();
                // SIN updated_at — registro financiero inmutable
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('anticipos_proveedores');
    }
};
