<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-037 (02-01 §2.2): el centro de costo «Altamira Matriz» pasa a llamarse
 * «Altamira UIO». Solo cambia el nombre; el código MATRIZ se conserva porque
 * otras pantallas lo usan para identificar el centro.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('centros_costo')) {
            return;
        }

        DB::table('centros_costo')
            ->where('codigo', 'MATRIZ')
            ->where('nombre', '<>', 'Altamira UIO')
            ->update(['nombre' => 'Altamira UIO']);
    }

    public function down(): void
    {
        // Sin reversión: el nombre anterior queda solo en el historial.
    }
};
