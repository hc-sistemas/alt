<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 02-01 §2.1: el nombre comercial de las dos empresas es «Altamira Light &
 * Sound». Como el nombre ya no distingue una de otra, se agrega una etiqueta
 * corta (UIO / Import) que las pantallas internas muestran junto al nombre.
 * La razón social y el RUC no se tocan.
 *
 * Idempotente.
 */
return new class extends Migration
{
    private const NOMBRE_COMERCIAL = 'Altamira Light & Sound';

    private const ETIQUETAS = [
        '1711293454001' => 'UIO',
        '1755265848001' => 'Import',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('empresas')) {
            return;
        }

        if (!Schema::hasColumn('empresas', 'etiqueta')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->string('etiqueta', 30)->nullable();
            });
        }

        foreach (self::ETIQUETAS as $ruc => $etiqueta) {
            DB::table('empresas')->where('ruc', $ruc)->update([
                'nombre_comercial' => self::NOMBRE_COMERCIAL,
                'etiqueta'         => DB::raw("COALESCE(etiqueta, " . DB::getPdo()->quote($etiqueta) . ")"),
            ]);
        }
    }

    public function down(): void
    {
        // Sin reversión: el nombre comercial anterior de Import no se conserva.
    }
};
