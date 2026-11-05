<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos 100 % desde la matriz (DEC-036): las acciones sensibles que estaban fijas por nombre de perfil en
 * los controladores pasan a ser submódulos de Configuración → Permisos, para que el administrador las asigne
 * o quite a cualquier perfil. Super Admin y Admin siempre pueden todo.
 *
 * Cada submódulo se gobierna con la columna indicada (la que mejor describe la acción). Los valores
 * iniciales conservan lo que el código permitía hasta hoy (Contador en reapertura de períodos, liquidación
 * de tarjetas, edición manual de nómina y autorización de crédito; el resto, nadie más que los
 * administradores). Idempotente.
 */
return new class extends Migration
{
    /** clave => [módulo padre, nombre, orden, perfiles con acceso inicial => columna] */
    private const SUBMODULOS = [
        'contabilidad_reabrir_periodos' => ['contabilidad', 'Reapertura de períodos (Editar)',          51, ['contador' => 'editar']],
        'contabilidad_cierre_fiscal'    => ['contabilidad', 'Cierre fiscal anual (Editar)',             52, []],
        'contabilidad_anular_asientos'  => ['contabilidad', 'Anular asientos contables (Anular)',       53, []],
        'ventas_facturas_criticas'      => ['ventas',       'Anular/eliminar facturas (Anular, Eliminar)', 61, []],
        'ventas_castigo_cartera'        => ['ventas',       'Castigo de cartera (Anular)',              62, []],
        'ventas_aprobar_credito'        => ['ventas',       'Autorizar crédito con PIN (Editar)',       63, []],
        'ventas_editar_clientes'        => ['ventas',       'Editar clientes desde facturas (Editar)',  64, ['contador' => 'editar']],
        'bancos_liquidar_tarjetas'      => ['bancos',       'Liquidar lotes de tarjeta (Editar)',       71, ['contador' => 'editar']],
        'rrhh_nomina_edicion_manual'    => ['rrhh',         'Edición manual de nómina (Editar)',        81, ['contador' => 'editar']],
        'asistencia_resumen_dia'        => ['asistencia',   'Ver asistencia de todos (Ver)',            111, []],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('modulos') || !Schema::hasTable('permisos')) {
            return;
        }
        $mcols = Schema::getColumnListing('modulos');
        $pcols = Schema::getColumnListing('permisos');
        $perfiles = DB::table('perfiles')->pluck('id', 'nombre');
        $empresas = DB::table('empresas')->pluck('id');

        foreach (self::SUBMODULOS as $clave => [$padreClave, $nombre, $orden, $iniciales]) {
            $padreId = DB::table('modulos')->where('clave', $padreClave)->value('id');
            if (!$padreId) {
                continue;
            }
            if (DB::table('modulos')->where('clave', $clave)->exists()) {
                DB::table('modulos')->where('clave', $clave)->update(['nombre' => $nombre]);
            } else {
                $fila = ['clave' => $clave, 'nombre' => $nombre, 'orden' => $orden, 'padre_id' => $padreId];
                foreach (['estado' => true, 'created_at' => now(), 'updated_at' => now()] as $c => $v) {
                    if (in_array($c, $mcols, true)) {
                        $fila[$c] = $v;
                    }
                }
                DB::table('modulos')->insert($fila);
            }
            $moduloId = DB::table('modulos')->where('clave', $clave)->value('id');

            foreach ($perfiles as $perfilNombre => $perfilId) {
                foreach ($empresas as $empresaId) {
                    if (DB::table('permisos')->where('perfil_id', $perfilId)->where('modulo_id', $moduloId)->where('empresa_id', $empresaId)->exists()) {
                        continue;
                    }
                    $col = $iniciales[$perfilNombre] ?? null;
                    $fila = ['perfil_id' => $perfilId, 'modulo_id' => $moduloId, 'empresa_id' => $empresaId,
                        'ver' => false, 'crear' => false, 'editar' => false, 'eliminar' => false, 'anular' => false];
                    if ($col) {
                        $fila[$col] = true;
                    }
                    foreach (['created_at', 'updated_at'] as $ts) {
                        if (in_array($ts, $pcols, true)) {
                            $fila[$ts] = now();
                        }
                    }
                    DB::table('permisos')->insert($fila);
                }
            }
        }

        $this->soloAdministradoresAprueban();
    }

    /** Decisión del 03/11/2026: solo los administradores aprueban (el contador no vende ni aprueba). */
    private function soloAdministradoresAprueban(): void
    {
        $moduloId = DB::table('modulos')->where('clave', 'ventas_aprobar_credito')->value('id');
        $contadorId = DB::table('perfiles')->where('nombre', 'contador')->value('id');
        if ($moduloId && $contadorId) {
            DB::table('permisos')->where('modulo_id', $moduloId)->where('perfil_id', $contadorId)->update(['editar' => false]);
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
