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
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('tipo', 20)->default('nacional'); // 'nacional' | 'internacional'
                $table->string('ruc_cedula', 20)->nullable();
                $table->string('nombre', 255);
                $table->string('direccion', 255)->nullable();
                $table->string('telefono', 20)->nullable();
                $table->string('email', 255)->nullable();
                $table->string('ciudad', 100)->nullable();
                $table->string('pais', 100)->default('Ecuador');
                $table->string('divisa', 10)->nullable();
                $table->boolean('tiene_credito')->default(false);
                $table->integer('dias_credito')->nullable();
                $table->boolean('estado')->default(true);
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedores');
    }
};
