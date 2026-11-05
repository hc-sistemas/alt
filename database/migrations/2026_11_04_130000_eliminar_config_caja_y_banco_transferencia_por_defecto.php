<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sin banco ni caja por defecto (01 §5, 02-06 §2.6): se eliminan las claves de configuración de cobros que lo permitían.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('configuraciones')) {
            DB::table('configuraciones')->whereIn('clave', ['cobro_caja_efectivo', 'cobro_banco_transferencia'])->delete();
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no se recrea un destino por defecto.
    }
};
