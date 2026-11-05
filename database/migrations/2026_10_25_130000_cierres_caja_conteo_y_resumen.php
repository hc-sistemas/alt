<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo D, Caja (02-05 §2.2 y §2.4): el conteo físico por denominación (apertura y
 * cierre), el saldo esperado y las facturas cobradas en efectivo quedan guardados
 * en el cierre. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cierres_caja')) {
            return;
        }

        Schema::table('cierres_caja', function (Blueprint $table) {
            if (!Schema::hasColumn('cierres_caja', 'conteo_apertura')) {
                $table->json('conteo_apertura')->nullable();
            }
            if (!Schema::hasColumn('cierres_caja', 'conteo_cierre')) {
                $table->json('conteo_cierre')->nullable();
            }
            if (!Schema::hasColumn('cierres_caja', 'saldo_esperado')) {
                $table->decimal('saldo_esperado', 14, 4)->default(0);
            }
            if (!Schema::hasColumn('cierres_caja', 'facturas_efectivo')) {
                $table->integer('facturas_efectivo')->default(0);
            }
        });
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
