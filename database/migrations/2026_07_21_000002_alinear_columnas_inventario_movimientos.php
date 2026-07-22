<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * inventario_movimientos se creó con bodega_origen_id/bodega_destino_id,
 * tipo_movimiento, documento_tipo/documento_id/documento_numero, observacion,
 * numero_serie, fecha, hora — pero todo el código real (InventarioService,
 * KardexController, TrasladoController, OrdenTrabajoController, etc.) fue
 * escrito contra bodega_id, tipo, doc_tipo, doc_id, stock_anterior,
 * stock_nuevo, notas (ver database/altamira_dump_dev2.sql). Se alinea la BD
 * al código. La tabla está vacía en este punto (Fase 2 de inventario aún no
 * se usa en producción), así que no hay datos que preservar/migrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventario_movimientos')) {
            return;
        }

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            if (!Schema::hasColumn('inventario_movimientos', 'bodega_id')) {
                $table->foreignId('bodega_id')->nullable()->after('producto_id')->constrained('bodegas');
            }
            if (!Schema::hasColumn('inventario_movimientos', 'tipo')) {
                $table->string('tipo', 30)->nullable()->after('bodega_id');
            }
            if (!Schema::hasColumn('inventario_movimientos', 'doc_tipo')) {
                $table->string('doc_tipo', 50)->nullable()->after('tipo');
            }
            if (!Schema::hasColumn('inventario_movimientos', 'doc_id')) {
                $table->unsignedBigInteger('doc_id')->nullable()->after('doc_tipo');
            }
            if (!Schema::hasColumn('inventario_movimientos', 'stock_anterior')) {
                $table->decimal('stock_anterior', 12, 4)->default(0)->after('costo_total');
            }
            if (!Schema::hasColumn('inventario_movimientos', 'stock_nuevo')) {
                $table->decimal('stock_nuevo', 12, 4)->default(0)->after('stock_anterior');
            }
            if (!Schema::hasColumn('inventario_movimientos', 'notas')) {
                $table->text('notas')->nullable()->after('stock_nuevo');
            }
        });

        // Backfill defensivo por si esta migración corre con filas ya insertadas.
        DB::table('inventario_movimientos')->update([
            'bodega_id' => DB::raw('COALESCE(bodega_id, bodega_destino_id, bodega_origen_id)'),
            'tipo'      => DB::raw('COALESCE(tipo, tipo_movimiento)'),
            'doc_tipo'  => DB::raw('COALESCE(doc_tipo, documento_tipo)'),
            'doc_id'    => DB::raw('COALESCE(doc_id, documento_id)'),
            'notas'     => DB::raw('COALESCE(notas, observacion)'),
        ]);

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            foreach (['bodega_origen_id', 'bodega_destino_id'] as $col) {
                if (Schema::hasColumn('inventario_movimientos', $col)) {
                    $table->dropConstrainedForeignId($col);
                }
            }
            foreach (['tipo_movimiento', 'documento_tipo', 'documento_id', 'documento_numero', 'numero_serie', 'fecha', 'hora'] as $col) {
                if (Schema::hasColumn('inventario_movimientos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

    }

    public function down(): void
    {
        // Cambio de esquema irreversible en el sentido estricto (se pierde la
        // distinción origen/destino); no se implementa rollback automático.
    }
};
