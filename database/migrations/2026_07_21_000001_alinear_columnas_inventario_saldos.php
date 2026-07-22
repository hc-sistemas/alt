<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * inventario_saldos se creó con la columna `cantidad`, pero todo el código de
 * negocio (InventarioService, KardexController, TrasladoController, etc.) fue
 * escrito para una columna `stock_actual` (ver database/altamira_dump_dev2.sql,
 * el esquema con el que se desarrolló ese código). Se alinea la BD al código,
 * no al revés, porque el código ya es extenso y consistente en ese nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventario_saldos')
            && Schema::hasColumn('inventario_saldos', 'cantidad')
            && !Schema::hasColumn('inventario_saldos', 'stock_actual')
        ) {
            Schema::table('inventario_saldos', function ($table) {
                $table->renameColumn('cantidad', 'stock_actual');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('inventario_saldos')
            && Schema::hasColumn('inventario_saldos', 'stock_actual')
            && !Schema::hasColumn('inventario_saldos', 'cantidad')
        ) {
            Schema::table('inventario_saldos', function ($table) {
                $table->renameColumn('stock_actual', 'cantidad');
            });
        }
    }
};
