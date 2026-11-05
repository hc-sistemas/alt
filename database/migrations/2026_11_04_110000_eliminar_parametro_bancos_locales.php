<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * No existe banco por defecto (01 §5, DEC-038): la cuenta contable sale del banco que el usuario elige en cada cobro o
 * pago. Se elimina el parámetro genérico `cta_bancos_locales` (apuntaba a Banco Pichincha). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('parametros_contables')) {
            DB::table('parametros_contables')->where('codigo', 'cta_bancos_locales')->delete();
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no se recrea un banco por defecto.
    }
};
