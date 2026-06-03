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
                $table->string('codigo', 50);
                $table->string('nombre', 255);
                $table->text('descripcion')->nullable();
                $table->string('categoria', 100);
                $table->string('ubicacion', 255)->nullable();
                $table->date('fecha_adquisicion');
                $table->decimal('valor_adquisicion', 14, 2);
                $table->decimal('valor_residual', 14, 2)->default(0);
                $table->integer('vida_util_años');
                $table->string('metodo_depreciacion', 30)->default('lineal');
                $table->decimal('depreciacion_acumulada', 14, 2)->default(0);
                $table->decimal('valor_libro', 14, 2);
                $table->string('estado', 20)->default('activo');
                $table->unsignedBigInteger('cuenta_activo_id')->nullable();
                $table->unsignedBigInteger('cuenta_depreciacion_id')->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['empresa_id', 'codigo']);
            });

            // CHECK constraint para estado
            \DB::statement("ALTER TABLE activos_fijos ADD CONSTRAINT activos_fijos_estado_check CHECK (estado IN ('activo', 'dado_de_baja', 'vendido'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activos_fijos');
    }
};
