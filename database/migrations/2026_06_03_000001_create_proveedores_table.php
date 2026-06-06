<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('proveedores')) {
            Schema::create('proveedores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->string('tipo', 20)->default('nacional');
                $table->string('tipo_identificacion', 5)->default('04');
                $table->string('identificacion', 20);
                $table->string('razon_social', 200);
                $table->string('nombre_comercial', 200)->nullable();
                $table->string('email', 200)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('direccion', 300)->nullable();
                $table->string('ciudad', 100)->nullable();
                $table->string('pais', 100)->default('ECUADOR');
                $table->string('divisa', 10)->default('USD');
                $table->boolean('tiene_credito')->default(false);
                $table->smallInteger('dias_credito')->default(0);
                $table->boolean('estado')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
                $table->unique(['empresa_id', 'identificacion']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
