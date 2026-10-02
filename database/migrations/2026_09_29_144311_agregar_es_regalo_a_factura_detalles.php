<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A8 (CHECKLIST_ERRORES_COMPLICACIONES.md): marca una línea de factura como
 * regalo. Con `es_regalo=true` la línea se factura a $0 sin pasar por la
 * aprobación de precio bajo costo (es intencional, no un error) — ver
 * FacturaController::store(). El stock se descuenta igual que cualquier otra
 * línea de producto (sin cambios ahí). Decisión: avisar si ya hay 2 regalos
 * en la factura, sin bloquear el tercero — eso se valida en el frontend, no
 * necesita nada en el servidor.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('factura_detalles') && !Schema::hasColumn('factura_detalles', 'es_regalo')) {
            Schema::table('factura_detalles', function (Blueprint $table) {
                $table->boolean('es_regalo')->default(false)->after('descripcion');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('factura_detalles') && Schema::hasColumn('factura_detalles', 'es_regalo')) {
            Schema::table('factura_detalles', function (Blueprint $table) {
                $table->dropColumn('es_regalo');
            });
        }
    }
};
