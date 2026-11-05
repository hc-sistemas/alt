<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo G, importaciones (02-03 §2.2): cada costo extra guarda cómo se calculó para poder
 * mostrar Base, IVA y Total: sin_iva | con_iva | impuesto | iva_importacion. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras') && !Schema::hasColumn('compras', 'costo_tipo')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->string('costo_tipo', 20)->nullable();
            });
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
