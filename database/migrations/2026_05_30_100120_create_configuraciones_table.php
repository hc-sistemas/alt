<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('configuraciones')) {
                    Schema::create('configuraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('clave', 100);
            $table->text('valor')->nullable();
            $table->string('tipo', 20)->default('string')->comment('string, integer, boolean, json');
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'clave']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
