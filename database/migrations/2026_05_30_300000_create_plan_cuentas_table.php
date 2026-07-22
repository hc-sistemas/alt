<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('plan_cuentas')) {
            Schema::create('plan_cuentas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id')->nullable()->index();
                $table->string('codigo', 30)->unique();
                $table->string('nombre', 150);
                $table->string('descripcion', 300)->nullable();
                $table->enum('tipo', ['activo', 'pasivo', 'patrimonio', 'ingreso', 'gasto']);
                $table->unsignedBigInteger('padre_id')->nullable()->index();
                $table->tinyInteger('nivel')->default(1);
                $table->boolean('permite_asientos')->default(false);
                $table->boolean('estado')->default(true);

                $table->foreign('padre_id')->references('id')->on('plan_cuentas')->nullOnDelete();
                $table->foreign('empresa_id')->references('id')->on('empresas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_cuentas');
    }
};
