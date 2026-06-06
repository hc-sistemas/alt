<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('traslados_bodega')) {
            Schema::create('traslados_bodega', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas');
                $table->foreignId('bodega_origen_id')->constrained('bodegas');
                $table->foreignId('bodega_destino_id')->constrained('bodegas');
                $table->string('numero', 20)->nullable();
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->string('estado', 20)->default('pendiente');
                $table->foreignId('enviado_por')->nullable()->constrained('usuarios');
                $table->foreignId('recibido_por')->nullable()->constrained('usuarios');
                $table->timestamp('fecha_recepcion')->nullable();
                $table->string('observacion', 300)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados_bodega');
    }
};
