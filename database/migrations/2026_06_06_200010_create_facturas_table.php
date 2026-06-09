<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('facturas')) {
            Schema::create('facturas', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('centro_costo_id')->nullable();
                $table->unsignedInteger('cliente_id');
                $table->unsignedInteger('usuario_id')->nullable();
                // Numeración SRI
                $table->string('establecimiento', 3)->default('001');
                $table->string('punto_emision', 3)->default('001');
                $table->string('secuencial', 9);
                $table->string('numero_completo', 17)->nullable();
                // Fechas
                $table->date('fecha_emision')->useCurrent();
                $table->time('hora_emision')->nullable();
                // SRI
                $table->string('clave_acceso', 49)->nullable();
                $table->string('autorizacion', 49)->nullable();
                $table->string('fecha_hora_aut', 30)->nullable();
                $table->string('estado_sri', 20)->default('pendiente');
                $table->text('observacion_sri')->nullable();
                $table->text('xml_doc')->nullable();
                // Cliente en el documento
                $table->string('tipo_identificacion', 5)->nullable();
                $table->string('identificacion', 20)->nullable();
                $table->string('razon_social', 200)->nullable();
                $table->string('email_cliente', 200)->nullable();
                $table->string('telefono_cliente', 20)->nullable();
                $table->string('direccion_cliente', 300)->nullable();
                // Totales
                $table->decimal('subtotal_0', 14, 4)->default(0);
                $table->decimal('subtotal_15', 14, 4)->default(0);
                $table->decimal('subtotal_exento', 14, 4)->default(0);
                $table->decimal('descuento_total', 14, 4)->default(0);
                $table->decimal('total_ice', 14, 4)->default(0);
                $table->decimal('total_iva', 14, 4)->default(0);
                $table->decimal('total', 14, 4)->default(0);
                // Control
                $table->unsignedInteger('asiento_id')->nullable();
                $table->string('guia_remision', 17)->nullable();
                $table->text('observaciones')->nullable();
                $table->boolean('email_enviado')->default(false);
                $table->smallInteger('tipo')->default(1);
                $table->string('estado', 20)->default('activa');
                $table->boolean('tiene_descuento_especial')->default(false);
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->foreign('cliente_id')->references('id')->on('clientes');
            });
        } elseif (!Schema::hasColumn('facturas', 'tiene_descuento_especial')) {
            Schema::table('facturas', function (Blueprint $table) {
                $table->boolean('tiene_descuento_especial')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
