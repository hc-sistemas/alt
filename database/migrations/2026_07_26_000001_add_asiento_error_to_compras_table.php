<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras') && !Schema::hasColumn('compras', 'asiento_error')) {
            Schema::table('compras', function (Blueprint $table) {
                // Guarda el motivo cuando compraRegistrada() falla (ej. período
                // contable cerrado) y la Compra se guarda de todas formas sin
                // asiento_id — ver AsientoService::compraRegistrada() y
                // CLAUDE.md, sección "Decisiones de diseño intencionales".
                $table->text('asiento_error')->nullable()->after('asiento_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compras') && Schema::hasColumn('compras', 'asiento_error')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropColumn('asiento_error');
            });
        }
    }
};
