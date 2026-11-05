<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo B (01 §7, DEC-012, DEC-030): la cuenta de venta, costo e inventario de cada producto sale de su SUBCATEGORÍA
 * (PC-CAT-01 a 20), no de parámetros genéricos; y ya no hay cuenta de gasto por defecto: quien registra un gasto sin
 * producto elige la cuenta. Se eliminan esos parámetros. Idempotente.
 */
return new class extends Migration
{
    private const PARAMETROS = [
        'cta_gasto_compras_default', 'cta_ventas_locales', 'cta_costo_ventas', 'cta_costo_ventas_importadas', 'cta_inventario_mercaderia',
    ];

    public function up(): void
    {
        if (Schema::hasTable('parametros_contables')) {
            DB::table('parametros_contables')->whereIn('codigo', self::PARAMETROS)->delete();
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
