<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('permisos')) {
                    Schema::create('permisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perfil_id')->constrained('perfiles')->cascadeOnDelete();
            $table->foreignId('modulo_id')->constrained('modulos')->cascadeOnDelete();
            $table->boolean('ver')->default(false);
            $table->boolean('crear')->default(false);
            $table->boolean('editar')->default(false);
            $table->boolean('eliminar')->default(false);
            $table->boolean('anular')->default(false);
            $table->timestamps();

            $table->unique(['perfil_id', 'modulo_id']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos');
    }
};
