<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('liquidaciones')) {
            Schema::create('liquidaciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('colaborador_id')->constrained('colaboradores');
                $table->date('fecha_salida');
                $table->string('motivo', 50)->nullable();
                $table->decimal('decimos_acumulados',  10, 2)->default(0);
                $table->decimal('vacaciones',          10, 2)->default(0);
                $table->decimal('fondos_reserva',      10, 2)->default(0);
                $table->decimal('anticipos_descontar', 10, 2)->default(0);
                $table->decimal('total_liquidacion',   10, 2)->default(0);
                $table->string('estado', 20)->default('borrador');
                $table->boolean('modificado_manualmente')->default(false);
                $table->foreignId('created_by')->nullable()->constrained('usuarios');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
