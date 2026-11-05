<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventas y cobranza (02-04): cheques recibidos de clientes en cartera hasta su depósito (PC-VEN-13/14), retenciones que el
 * cliente hace en el cobro (PC-VEN-12) y marca de factura de evento (PC-VEN-16). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cheques_recibidos')) {
            Schema::create('cheques_recibidos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->index();
                $t->unsignedBigInteger('cliente_id')->nullable();
                $t->unsignedBigInteger('factura_id')->nullable()->index();
                $t->unsignedBigInteger('factura_pago_id')->nullable()->unique();
                $t->unsignedBigInteger('cuenta_cobrar_id')->nullable();
                $t->string('numero', 30);
                $t->string('banco_emisor', 100)->nullable();
                $t->decimal('monto', 14, 2);
                $t->date('fecha_recepcion');
                $t->date('fecha_cheque');                       // posterior a la recepción = post fechado
                $t->string('estado', 20)->default('en_cartera'); // en_cartera | depositado | anulado
                $t->date('fecha_deposito')->nullable();
                $t->unsignedBigInteger('banco_caja_id')->nullable();
                $t->unsignedBigInteger('movimiento_id')->nullable();
                $t->unsignedBigInteger('asiento_id')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('factura_pagos') && !Schema::hasColumn('factura_pagos', 'fecha_cheque')) {
            Schema::table('factura_pagos', fn (Blueprint $t) => $t->date('fecha_cheque')->nullable());
        }

        if (Schema::hasTable('cuentas_cobrar_cobros')) {
            Schema::table('cuentas_cobrar_cobros', function (Blueprint $t) {
                if (!Schema::hasColumn('cuentas_cobrar_cobros', 'retencion_iva')) {
                    $t->decimal('retencion_iva', 14, 2)->default(0);
                }
                if (!Schema::hasColumn('cuentas_cobrar_cobros', 'retencion_ir')) {
                    $t->decimal('retencion_ir', 14, 2)->default(0);
                }
                if (!Schema::hasColumn('cuentas_cobrar_cobros', 'num_retencion')) {
                    $t->string('num_retencion', 30)->nullable();
                }
            });
        }

        if (Schema::hasTable('facturas') && !Schema::hasColumn('facturas', 'es_evento')) {
            Schema::table('facturas', fn (Blueprint $t) => $t->boolean('es_evento')->default(false));
        }
    }

    public function down(): void
    {
        // Sin reversa: no se descartan cheques ni retenciones registrados.
    }
};
