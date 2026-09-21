<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige las filas de parametros_contables que quedaron apuntando a una
 * cuenta que NO es la que dice el nombre del parámetro.
 *
 * El mapa de códigos por defecto de AsientoService tenía varios valores
 * semánticamente equivocados, y como cuentaId() auto-guarda en
 * parametros_contables la cuenta que resuelve por fallback, esos errores
 * quedaron persistidos en la base. Corregir solo el mapa del código no basta:
 * cuentaId() prefiere siempre lo que ya está guardado, así que las filas malas
 * seguirían ganando.
 *
 * Errores corregidos (verificados contra el plan de cuentas real):
 *   cta_aporte_patronal        5.2.1.03 "Comisiones y Bonos"        -> 5.2.1.04
 *   cta_decimo_tercero         5.2.1.04 "Aporte Patronal"           -> 5.2.1.05
 *   cta_decimo_cuarto          5.2.1.05 "Décimo Tercer Sueldo"      -> 5.2.1.06
 *   cta_vacaciones             5.2.1.06 "Décimo Cuarto Sueldo"      -> 5.2.1.07
 *   cta_fondos_reserva         5.2.1.07 "Vacaciones"                -> 5.2.1.08
 *   cta_ganancias_acumuladas   3.1.3.01 "Superávit por Revaluación" -> 3.1.4.1
 *   cta_perdidas_acumuladas    3.1.3.02 "Gan./Pérd. Actuariales"    -> 3.1.4.2
 *   cta_utilidad_periodo       3.1.4.01 "Ganancias Acumuladas"      -> 3.1.5.1
 *   cta_perdida_periodo        3.1.4.02 "(-) Pérdidas Acumuladas"   -> 3.1.5.2
 *   cta_anticipos_clientes     2.1.6.01 "Porción Corriente Oblig."  -> 2.1.1.3
 *
 * Solo se toca la fila si sigue apuntando EXACTAMENTE a la cuenta equivocada
 * conocida: si el contador ya la reasignó a mano, se respeta su decisión.
 */
return new class extends Migration {
    /** parámetro => [código incorrecto conocido, código correcto] */
    private const CORRECCIONES = [
        'cta_aporte_patronal'      => ['5.2.1.03', '5.2.1.04'],
        'cta_decimo_tercero'       => ['5.2.1.04', '5.2.1.05'],
        'cta_decimo_cuarto'        => ['5.2.1.05', '5.2.1.06'],
        'cta_vacaciones'           => ['5.2.1.06', '5.2.1.07'],
        'cta_fondos_reserva'       => ['5.2.1.07', '5.2.1.08'],
        'cta_ganancias_acumuladas' => ['3.1.3.01', '3.1.4.1'],
        'cta_perdidas_acumuladas'  => ['3.1.3.02', '3.1.4.2'],
        'cta_utilidad_periodo'     => ['3.1.4.01', '3.1.5.1'],
        'cta_perdida_periodo'      => ['3.1.4.02', '3.1.5.2'],
        'cta_anticipos_clientes'   => ['2.1.6.01', '2.1.1.3'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('parametros_contables') || !Schema::hasTable('plan_cuentas')) {
            return;
        }

        // El orden importa: 5.2.1.04 pasa de ser "el valor malo de
        // cta_decimo_tercero" a ser "el valor bueno de cta_aporte_patronal".
        // Se resuelven todos los ids ANTES de escribir, así ninguna corrección
        // depende del estado dejado por la anterior.
        $objetivos = [];
        foreach (self::CORRECCIONES as $param => [$malo, $bueno]) {
            $idMalo  = \App\Services\AsientoService::buscarCuentaPorCodigo($malo)?->id;
            $idBueno = \App\Services\AsientoService::buscarCuentaPorCodigo($bueno)?->id;

            if ($idBueno && $idMalo && $idBueno !== $idMalo) {
                $objetivos[$param] = [$idMalo, $idBueno];
            }
        }

        foreach ($objetivos as $param => [$idMalo, $idBueno]) {
            DB::table('parametros_contables')
                ->where('codigo', $param)
                ->where('cuenta_id', $idMalo)
                ->update(['cuenta_id' => $idBueno]);
        }

        // 'cta_gasto_compras' ya no existe en la UI (nada en el sistema lo lee):
        // se borra para que no quede configuración fantasma.
        DB::table('parametros_contables')->where('codigo', 'cta_gasto_compras')->delete();
    }

    public function down(): void
    {
        // No se revierte: restaurar el mapeo incorrecto volvería a registrar
        // el aporte patronal como "Comisiones y Bonos".
    }
};
