<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compra_detalles') && !Schema::hasColumn('compra_detalles', 'peso')) {
            Schema::table('compra_detalles', function (Blueprint $table) {
                // Peso real de la línea (kg), opcional. Alimenta el método de prorrateo
                // "Peso" en Importaciones (ImportacionController::liquidar()) como
                // override del cálculo por defecto (cantidad × producto.peso) cuando el
                // usuario ingresa el peso real facturado/medido para esa línea.
                $table->decimal('peso', 12, 4)->nullable()->after('cantidad');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compra_detalles') && Schema::hasColumn('compra_detalles', 'peso')) {
            Schema::table('compra_detalles', function (Blueprint $table) {
                $table->dropColumn('peso');
            });
        }
    }
};
