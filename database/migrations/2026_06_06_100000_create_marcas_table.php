<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('marcas')) {
            Schema::create('marcas', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 100)->unique();
                $table->string('logo', 500)->nullable();
                $table->string('icono', 50)->default('fa-volume-high');
                $table->boolean('estado')->default(true);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marcas');
    }
};
