<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `devoluciones_compra.compra_id` se creó nullable (migración
 * 2025_06_30_000001), pero una devolución de compra sin factura de
 * origen no permite ajustar la Cuenta por Pagar correspondiente ni
 * validar que no se devuelva más de lo comprado — el flujo de negocio
 * (ver DevolucionCompraController::store()) ahora exige siempre una
 * compra real. Se usa DB::statement (no Schema::table()->change(), que
 * requiere doctrine/dbal — no instalado en este proyecto).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('devoluciones_compra')) {
            return;
        }

        $esNullable = DB::selectOne(
            "SELECT is_nullable FROM information_schema.columns WHERE table_name = 'devoluciones_compra' AND column_name = 'compra_id'"
        )->is_nullable ?? 'NO';

        if ($esNullable === 'YES') {
            // Ya se confirmó (2026-07-29) que no hay filas existentes con
            // compra_id NULL — de haberlas, este ALTER fallaría de forma
            // segura en vez de dejar datos inconsistentes en silencio.
            DB::statement('ALTER TABLE devoluciones_compra ALTER COLUMN compra_id SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('devoluciones_compra')) {
            DB::statement('ALTER TABLE devoluciones_compra ALTER COLUMN compra_id DROP NOT NULL');
        }
    }
};
