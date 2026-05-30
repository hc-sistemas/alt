<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('limites_descuento')) {
                    Schema::create('limites_descuento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfiles')->cascadeOnDelete();
            $table->decimal('porcentaje_maximo', 5, 2)->default(0)->comment('% máximo sin necesitar aprobación');
            $table->boolean('puede_aprobar')->default(false);
            $table->decimal('porcentaje_aprobacion_max', 5, 2)->default(0)->comment('% máximo que puede aprobar para otros');
            $table->timestamps();

            $table->unique('perfil_id');
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('limites_descuento');
    }
};
