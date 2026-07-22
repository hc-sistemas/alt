<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('guias_remision')) {
            Schema::create('guias_remision', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('factura_id')->nullable();
                $table->unsignedInteger('transportista_id')->nullable();
                $table->string('establecimiento', 3)->default('001');
                $table->string('punto_emision', 3)->default('001');
                $table->string('secuencial', 9);
                $table->string('numero_completo', 17)->nullable();
                $table->date('fecha_emision')->useCurrent();
                $table->date('fecha_inicio_transporte')->nullable();
                $table->date('fecha_fin_transporte')->nullable();
                $table->string('origen', 300)->nullable();
                $table->string('destino', 300)->nullable();
                $table->string('ruta', 300)->nullable();
                $table->string('motivo', 300)->nullable();
                $table->string('clave_acceso', 49)->nullable();
                $table->string('autorizacion', 49)->nullable();
                $table->string('estado_sri', 20)->default('pendiente');
                $table->text('xml_doc')->nullable();
                $table->string('estado', 20)->default('activa');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('empresa_id')->references('id')->on('empresas');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('guias_remision');
    }
};
