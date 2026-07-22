<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cuentas_cobrar')) {
            Schema::create('cuentas_cobrar', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('cliente_id');
                $table->unsignedInteger('factura_id')->nullable();
                $table->unsignedInteger('prefactura_id')->nullable();
                $table->decimal('monto', 14, 4);
                $table->decimal('saldo', 14, 4);
                $table->date('fecha_emision')->useCurrent();
                $table->date('fecha_vencimiento');
                $table->string('forma_cobro', 30)->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->unsignedInteger('asiento_cobro_id')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->foreign('cliente_id')->references('id')->on('clientes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_cobrar');
    }
};
