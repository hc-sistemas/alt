<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Libro de la cuenta (02-06 §2.3): fecha de conciliación y comentario por movimiento. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('movimientos_bancarios')) {
            return;
        }
        if (!Schema::hasColumn('movimientos_bancarios', 'fecha_conciliacion')) {
            Schema::table('movimientos_bancarios', fn (Blueprint $t) => $t->date('fecha_conciliacion')->nullable());
        }
        if (!Schema::hasColumn('movimientos_bancarios', 'comentario')) {
            Schema::table('movimientos_bancarios', fn (Blueprint $t) => $t->text('comentario')->nullable());
        }

        // Movimientos ya conciliados: la mejor fecha disponible es la de su última modificación
        DB::table('movimientos_bancarios')->where('conciliado', true)->whereNull('fecha_conciliacion')
            ->update(['fecha_conciliacion' => DB::raw('COALESCE(updated_at::date, fecha)')]);
    }

    public function down(): void
    {
        // Sin reversa: no se descartan datos de conciliación.
    }
};
