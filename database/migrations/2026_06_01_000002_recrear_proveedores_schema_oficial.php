<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la tabla ya tiene el schema correcto (tipo_identificacion existe), no recrear
        if (Schema::hasTable('proveedores') && Schema::hasColumn('proveedores', 'tipo_identificacion')) {
            return;
        }

        Schema::dropIfExists('proveedores');
        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->string('tipo', 20)->default('nacional');
            // 'nacional', 'internacional'
            $table->string('tipo_identificacion', 5)->default('04');
            // 04=RUC, 05=cedula, 06=pasaporte
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
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['empresa_id', 'identificacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
