<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('ruc_cedula', 13);
                $table->string('nombre', 255);
                $table->string('direccion', 255)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('ciudad', 100)->nullable();
                $table->string('pais', 100)->default('Ecuador');
                $table->boolean('tiene_credito')->default(false);
                $table->integer('dias_credito')->nullable();
                $table->decimal('cupo_credito', 12, 2)->nullable();
                $table->boolean('es_agente_retencion')->default(false);
                $table->boolean('estado')->default(true);
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['empresa_id', 'ruc_cedula']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
