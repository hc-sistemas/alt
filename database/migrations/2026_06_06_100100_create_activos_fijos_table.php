<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activos_fijos')) {
            Schema::create('activos_fijos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->unsignedBigInteger('cuenta_id')->nullable();
                $table->string('nombre', 200);
                $table->text('descripcion')->nullable();
                $table->string('codigo', 50)->unique()->nullable();
                $table->date('fecha_adquisicion');
                $table->decimal('costo_adquisicion', 14, 2)->default(0);
                $table->smallInteger('vida_util_anios')->default(5);
                $table->decimal('valor_residual', 14, 2)->default(0);
                $table->decimal('depreciacion_acumulada', 14, 2)->default(0);
                $table->decimal('valor_en_libros', 14, 2)->default(0);
                $table->string('estado', 20)->default('activo');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activos_fijos');
    }
};
