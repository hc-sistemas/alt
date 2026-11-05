<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 02-01 §2.2: el centro de costo del taller se llama «Altamira FIX».
 * Solo cambia el nombre; el código FIX se conserva. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('centros_costo')) {
            return;
        }

        DB::table('centros_costo')
            ->where('codigo', 'FIX')
            ->where('nombre', '<>', 'Altamira FIX')
            ->update(['nombre' => 'Altamira FIX']);
    }

    public function down(): void
    {
        // Sin reversión.
    }
};
