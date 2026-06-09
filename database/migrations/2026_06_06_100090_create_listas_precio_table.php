<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('listas_precio')) {
            Schema::create('listas_precio', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->foreignId('producto_id')->constrained('productos');
                $table->string('tipo', 10)->default('PVP');
                $table->decimal('precio', 14, 4)->default(0);
                $table->decimal('descuento_max', 5, 2)->default(0);
                $table->date('vigencia_desde')->nullable();
                $table->date('vigencia_hasta')->nullable();
                $table->unique(['empresa_id', 'producto_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listas_precio');
    }
};
