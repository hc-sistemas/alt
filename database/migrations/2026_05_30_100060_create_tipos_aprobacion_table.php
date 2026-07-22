<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tipos_aprobacion')) {
                    Schema::create('tipos_aprobacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('clave', 50)->unique();
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_aprobacion');
    }
};
