<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Revisión de componentes al ingresar un equipo (eqp_rev_componentes del sistema legacy).
        if (!Schema::hasTable('taller_rev_componentes')) {
            Schema::create('taller_rev_componentes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('ingreso_id');
                $table->string('nombre', 150);
                $table->boolean('funciona')->default(true);
                $table->smallInteger('accion')->default(0); // 0 = reparación, 1 = reemplazo
                $table->text('descripcion')->nullable();
                $table->decimal('costo', 12, 2)->default(0);
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('ingreso_id')->references('id')->on('taller_ingresos')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_rev_componentes');
    }
};
