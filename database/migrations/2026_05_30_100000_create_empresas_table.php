<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('empresas')) {
                    Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('nombre_comercial');
            $table->string('ruc', 13)->unique();
            $table->string('direccion_matriz')->nullable();
            $table->string('direccion_establecimiento')->nullable();
            $table->string('email_notificaciones')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('logo')->nullable();
            $table->string('slogan')->nullable();
            // Datos SRI
            $table->enum('ambiente_sri', ['1', '2'])->default('1')->comment('1=Pruebas, 2=Produccion');
            $table->string('codigo_establecimiento', 3)->default('001');
            $table->string('codigo_punto_emision', 3)->default('001');
            $table->boolean('obligado_contabilidad')->default(false);
            $table->boolean('contribuyente_especial')->default(false);
            $table->string('numero_resolucion_agente_retencion')->nullable();
            $table->binary('firma_electronica')->nullable();
            $table->string('clave_firma')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
