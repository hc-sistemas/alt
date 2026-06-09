<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proformas')) {
            Schema::create('proformas', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('empresa_id');
                $table->unsignedInteger('centro_costo_id')->nullable();
                $table->unsignedInteger('cliente_id');
                $table->unsignedInteger('usuario_id')->nullable();
                $table->string('numero', 20)->nullable();
                $table->date('fecha_emision')->useCurrent();
                $table->date('fecha_vencimiento')->nullable();
                $table->decimal('subtotal', 14, 4)->default(0);
                $table->decimal('descuento_total', 14, 4)->default(0);
                $table->decimal('total_iva', 14, 4)->default(0);
                $table->decimal('total', 14, 4)->default(0);
                $table->text('observaciones')->nullable();
                $table->unsignedInteger('factura_id')->nullable();
                $table->string('estado', 20)->default('pendiente');
                $table->timestamps();

                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->foreign('cliente_id')->references('id')->on('clientes');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proformas');
    }
};
