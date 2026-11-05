<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo H, formas de pago (02-04 §2.2 y §2.3):
 *  - la transferencia guarda la CUENTA BANCARIA elegida (banco_caja_id) y su comprobante en un
 *    campo aparte (`referencia`): num_cheque queda solo para cheques;
 *  - el cobro con tarjeta registra procesador, tipo, lote y autorización;
 *  - dias_credito deja de ser obligatorio: solo importa en crédito (error SQLSTATE[23502]).
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('factura_pagos')) {
            return;
        }

        Schema::table('factura_pagos', function (Blueprint $table) {
            if (!Schema::hasColumn('factura_pagos', 'banco_caja_id')) {
                $table->unsignedBigInteger('banco_caja_id')->nullable();
            }
            if (!Schema::hasColumn('factura_pagos', 'referencia')) {
                $table->string('referencia', 60)->nullable();
            }
            if (!Schema::hasColumn('factura_pagos', 'procesador_id')) {
                $table->unsignedBigInteger('procesador_id')->nullable();
            }
            if (!Schema::hasColumn('factura_pagos', 'tipo_tarjeta')) {
                $table->string('tipo_tarjeta', 10)->nullable();
            }
            if (!Schema::hasColumn('factura_pagos', 'lote')) {
                $table->string('lote', 40)->nullable();
            }
            if (!Schema::hasColumn('factura_pagos', 'autorizacion')) {
                $table->string('autorizacion', 40)->nullable();
            }
        });

        // dias_credito opcional en la base de datos (02-04 §2.2)
        DB::statement('ALTER TABLE factura_pagos ALTER COLUMN dias_credito DROP NOT NULL');
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
