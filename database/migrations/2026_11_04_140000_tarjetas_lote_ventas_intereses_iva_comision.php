<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tarjetas (02-07): el lote se arma con las ventas que el procesador incluyó (tabla de enlace), la liquidación del
 * diferido registra sus intereses aparte de la comisión (PC-TAR-04) y el IVA de la comisión sale de un porcentaje
 * configurable por procesador en lugar de estar escrito en la pantalla. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('datafast_lote_pagos')) {
            Schema::create('datafast_lote_pagos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('lote_id')->index();
                $t->unsignedBigInteger('factura_pago_id')->unique(); // una venta con tarjeta pertenece a un solo lote
                $t->decimal('valor', 14, 2);
                $t->timestamp('created_at')->nullable();
            });
        }

        if (Schema::hasTable('datafast_liquidaciones') && !Schema::hasColumn('datafast_liquidaciones', 'intereses_diferido')) {
            Schema::table('datafast_liquidaciones', fn (Blueprint $t) => $t->decimal('intereses_diferido', 14, 2)->default(0));
        }

        if (Schema::hasTable('tarjeta_procesadores') && !Schema::hasColumn('tarjeta_procesadores', 'iva_comision_pct')) {
            Schema::table('tarjeta_procesadores', fn (Blueprint $t) => $t->decimal('iva_comision_pct', 5, 2)->default(15));
        }
    }

    public function down(): void
    {
        // Sin reversa: no se descartan datos de liquidaciones.
    }
};
