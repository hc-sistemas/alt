<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('retenciones')) {
            Schema::create('retenciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('factura_id')->nullable();
                $table->unsignedInteger('compra_id')->nullable();
                $table->unsignedInteger('cliente_id')->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->string('establecimiento', 3)->default('001');
                $table->string('punto_emision', 3)->default('001');
                $table->string('secuencial', 9);
                $table->string('numero_completo', 17)->nullable();
                $table->date('fecha_emision')->useCurrent();
                $table->string('identificacion', 20)->nullable();
                $table->string('razon_social', 200)->nullable();
                $table->string('num_comp_retenido', 17)->nullable();
                $table->decimal('total', 14, 4)->default(0);
                $table->string('clave_acceso', 49)->nullable();
                $table->string('autorizacion', 49)->nullable();
                $table->string('estado_sri', 20)->default('pendiente');
                $table->text('xml_doc')->nullable();
                $table->unsignedInteger('asiento_id')->nullable();
                $table->string('estado', 20)->default('activa');
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('empresa_id')->references('id')->on('empresas');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('retenciones');
    }
};
