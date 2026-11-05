<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo C (02-06 §2.1, PC-BAN-01, CAM-040): un parámetro por cada cuenta
 * bancaria. La cuenta contable de un banco se asigna sola según banco y tipo;
 * ya no se elige a mano. Idempotente.
 */
return new class extends Migration
{
    private const PARAMETROS = [
        'cta_banco_pichincha' => ['1.01.02.01.01', 'Banco Pichincha (cuenta corriente)'],
        'cta_banco_produbanco' => ['1.01.02.02.01', 'ProduBanco (cuenta de ahorros)'],
        'cta_banco_guayaquil' => ['1.01.02.02.02', 'Banco de Guayaquil (cuenta de ahorros)'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('parametros_contables') || !Schema::hasTable('plan_cuentas')) {
            return;
        }
        foreach (self::PARAMETROS as $clave => [$codigoCuenta, $descripcion]) {
            $cuentaId = DB::table('plan_cuentas')->where('codigo', $codigoCuenta)->value('id');
            if (!$cuentaId) {
                continue;
            }
            $existe = DB::table('parametros_contables')->whereNull('empresa_id')->where('codigo', $clave)->exists();
            if (!$existe) {
                DB::table('parametros_contables')->insert([
                    'empresa_id'  => null,
                    'codigo'      => $clave,
                    'cuenta_id'   => $cuentaId,
                    'descripcion' => $descripcion,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Sin reversa: no se eliminan parámetros contables.
    }
};
