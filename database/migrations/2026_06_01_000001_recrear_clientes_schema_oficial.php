<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('clientes');
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('tipo_identificacion', 5)->default('05');
            // 04=RUC, 05=cedula, 06=pasaporte, 07=consumidor_final
            $table->string('identificacion', 20);
            $table->string('razon_social', 200);
            $table->string('nombre_comercial', 200)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('celular', 20)->nullable();
            $table->string('direccion', 300)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('provincia', 100)->nullable();
            $table->string('pais', 100)->default('ECUADOR');
            $table->boolean('tiene_credito')->default(false);
            $table->smallInteger('dias_credito')->default(0);
            $table->decimal('cupo_maximo', 12, 2)->default(0);
            $table->boolean('agente_retencion')->default(false);
            $table->boolean('es_cliente_nuevo')->default(false);
            $table->boolean('estado')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'identificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
