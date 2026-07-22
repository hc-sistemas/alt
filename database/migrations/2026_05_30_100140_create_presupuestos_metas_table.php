<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('presupuestos_metas')) {
                    Schema::create('presupuestos_metas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
            $table->unsignedTinyInteger('mes')->comment('1-12');
            $table->unsignedSmallInteger('anio');
            $table->decimal('meta_ventas', 14, 2)->default(0);
            $table->decimal('meta_cobros', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['empresa_id', 'centro_costo_id', 'mes', 'anio']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('presupuestos_metas');
    }
};
