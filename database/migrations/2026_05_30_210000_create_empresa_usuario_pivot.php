<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('empresa_usuario')) {
            Schema::create('empresa_usuario', function (Blueprint $table) {
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
                $table->primary(['empresa_id', 'usuario_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('empresa_usuario');
    }
};
