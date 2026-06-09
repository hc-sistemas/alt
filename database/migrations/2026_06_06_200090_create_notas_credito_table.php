<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notas_credito')) {
            Schema::create('notas_credito', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('factura_id');
                $table->unsignedInteger('cliente_id');
                $table->unsignedInteger('usuario_id')->nullable();
                $table->string('establecimiento', 3)->default('001');
                $table->string('punto_emision', 3)->default('001');
                $table->string('secuencial', 9);
                $table->string('numero_completo', 17)->nullable();
                $table->date('fecha_emision')->useCurrent();
                $table->string('motivo', 300);
                $table->string('tipo', 20)->default('total');
                $table->decimal('subtotal', 14, 4)->default(0);
                $table->decimal('total_iva', 14, 4)->default(0);
                $table->decimal('total', 14, 4)->default(0);
                $table->string('clave_acceso', 49)->nullable();
                $table->string('autorizacion', 49)->nullable();
                $table->string('estado_sri', 20)->default('pendiente');
                $table->text('xml_doc')->nullable();
                $table->unsignedInteger('asiento_id')->nullable();
                $table->boolean('genera_saldo_favor')->default(false);
                $table->decimal('saldo_favor', 14, 4)->default(0);
                $table->string('estado', 20)->default('activa');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->foreign('factura_id')->references('id')->on('facturas');
                $table->foreign('cliente_id')->references('id')->on('clientes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_credito');
    }
};
