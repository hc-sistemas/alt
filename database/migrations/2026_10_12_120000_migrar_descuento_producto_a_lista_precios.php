<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El descuento máximo deja de leerse de productos.descuento_maximo y pasa a
 * salir solo de listas_precio (por empresa y tipo PVP/PVD). Para no dejar a
 * ningún producto con descuento 0 de golpe, se copia el valor viejo a una fila
 * PVP de cada empresa que todavía no tenga una. La columna de productos no se
 * toca. Idempotente: nunca pisa filas existentes.
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
            SELECT e.id, p.id, 'PVP', p.pvp, p.descuento_maximo
            FROM empresas e
            CROSS JOIN productos p
            WHERE e.estado = true
              AND p.descuento_maximo > 0
              AND p.estado = true
              AND NOT EXISTS (
                  SELECT 1 FROM listas_precio lp
                  WHERE lp.empresa_id = e.id AND lp.producto_id = p.id AND lp.tipo = 'PVP'
              )
        ");
    }

    public function down(): void
    {
        // Datos migrados: no se revierte.
    }
};
