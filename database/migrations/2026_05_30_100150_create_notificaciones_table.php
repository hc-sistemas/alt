<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notificaciones')) {
                    Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('mensaje');
            $table->string('tipo', 20)->default('info')->comment('info, success, warning, danger');
            $table->string('icono', 50)->nullable();
            $table->string('url')->nullable()->comment('URL de acción al hacer clic');
            $table->boolean('leida')->default(false);
            $table->timestamp('leida_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
