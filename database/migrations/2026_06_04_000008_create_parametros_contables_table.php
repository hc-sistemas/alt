<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('parametros_contables')) {
            Schema::create('parametros_contables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->string('codigo', 60);
                $table->foreignId('cuenta_id')->constrained('plan_cuentas');
                $table->string('descripcion', 200)->nullable();

                $table->unique(['empresa_id', 'codigo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_contables');
    }
};
