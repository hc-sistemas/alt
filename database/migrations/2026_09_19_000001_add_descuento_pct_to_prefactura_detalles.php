<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * prefactura_detalles nunca guardó el % de descuento: al convertir la
     * prefactura en factura se leía descuento_pct inexistente (siempre 0) y el
     * descuento otorgado se perdía.
     */
    public function up(): void
    {
        if (Schema::hasTable('prefactura_detalles') && !Schema::hasColumn('prefactura_detalles', 'descuento_pct')) {
            Schema::table('prefactura_detalles', function (Blueprint $table) {
                $table->decimal('descuento_pct', 6, 2)->default(0)->after('precio_unitario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('prefactura_detalles', 'descuento_pct')) {
            Schema::table('prefactura_detalles', function (Blueprint $table) {
                $table->dropColumn('descuento_pct');
            });
        }
    }
};
