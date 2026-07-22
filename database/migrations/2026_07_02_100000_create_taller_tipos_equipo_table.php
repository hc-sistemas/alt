<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('taller_tipos_equipo')) {
            Schema::create('taller_tipos_equipo', function (Blueprint $table) {
                $table->id();
                $table->string('descripcion', 100);
                $table->boolean('estado')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('taller_tipos_equipo');
    }
};
