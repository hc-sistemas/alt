<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Complementa 2026_10_12_120000: el descuento actual de productos.descuento_maximo
 * debe quedar tanto en PVP como en PVD, por empresa, dentro de listas_precio.
 *  1. Crea la fila PVD (precio = productos.pvd) donde no exista.
 *  2. Completa con el descuento del producto las filas PVP/PVD ya existentes
 *     que tengan descuento_max en 0 (valor por defecto, nunca editado).
 * Idempotente: no pisa descuentos ya configurados (> 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('listas_precio') || !Schema::hasColumn('productos', 'descuento_maximo')) {
            return;
        }

        DB::statement("
            INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
            SELECT e.id, p.id, 'PVD', p.pvd, p.descuento_maximo
            FROM empresas e
            CROSS JOIN productos p
            WHERE e.estado = true
              AND p.descuento_maximo > 0
              AND p.estado = true
              AND NOT EXISTS (
                  SELECT 1 FROM listas_precio lp
                  WHERE lp.empresa_id = e.id AND lp.producto_id = p.id AND lp.tipo = 'PVD'
              )
        ");

        DB::statement("
            UPDATE listas_precio lp
            SET descuento_max = p.descuento_maximo
            FROM productos p
            WHERE p.id = lp.producto_id
              AND lp.tipo IN ('PVP', 'PVD')
              AND lp.descuento_max = 0
              AND p.descuento_maximo > 0
        ");
    }

    public function down(): void
    {
        // Datos migrados: no se revierte.
    }
};
