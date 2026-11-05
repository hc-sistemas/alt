<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permisos 100 % desde la matriz (02-01 §2.3, DEC-036): las pantallas de Inventario que estaban restringidas
 * por nombre de perfil en el código (Consolidado, Movimientos/Traslados, Equipos Listos) pasan a ser
 * submódulos de Inventario dentro de Configuración → Permisos.
 *
 * Los valores iniciales conservan lo que esas pantallas permitían hasta hoy, para que nadie gane ni pierda
 * acceso por esta migración: Consolidado nadie (solo Super Admin / Admin, que siempre pasan), Movimientos
 * Bodeguero todo y Contador solo ver, Equipos Listos Bodeguero y Contador solo ver. Idempotente.
 */
return new class extends Migration
{
    private const SUBMODULOS = [
        'inventario_consolidado'    => ['nombre' => 'Inventario Consolidado',  'orden' => 41],
        'inventario_movimientos'    => ['nombre' => 'Movimientos (Traslados)', 'orden' => 42],
        'inventario_equipos_taller' => ['nombre' => 'Equipos Listos (Taller)', 'orden' => 43],
    ];

    /** [ver, crear, editar, eliminar, anular] por perfil */
    private const INICIAL = [
        'inventario_movimientos'    => ['bodeguero' => [1, 1, 1, 1, 1], 'contador' => [1, 0, 0, 0, 0]],
        'inventario_equipos_taller' => ['bodeguero' => [1, 0, 0, 0, 0], 'contador' => [1, 0, 0, 0, 0]],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('modulos') || !Schema::hasTable('permisos')) {
            return;
        }
        $padreId = DB::table('modulos')->where('clave', 'inventario')->value('id');
        if (!$padreId) {
            return;
        }

        $cols = Schema::getColumnListing('modulos');
        foreach (self::SUBMODULOS as $clave => $def) {
            if (DB::table('modulos')->where('clave', $clave)->exists()) {
                continue;
            }
            $fila = ['clave' => $clave, 'nombre' => $def['nombre'], 'orden' => $def['orden'], 'padre_id' => $padreId];
            foreach (['estado' => true, 'created_at' => now(), 'updated_at' => now()] as $c => $v) {
                if (in_array($c, $cols, true)) {
                    $fila[$c] = $v;
                }
            }
            DB::table('modulos')->insert($fila);
        }

        $perfiles = DB::table('perfiles')->pluck('id', 'nombre');
        $empresas = DB::table('empresas')->pluck('id');
        $pcols = Schema::getColumnListing('permisos');

        foreach (self::SUBMODULOS as $clave => $_) {
            $moduloId = DB::table('modulos')->where('clave', $clave)->value('id');
            foreach ($perfiles as $nombre => $perfilId) {
                [$ver, $crear, $editar, $eliminar, $anular] = self::INICIAL[$clave][$nombre] ?? [0, 0, 0, 0, 0];
                foreach ($empresas as $empresaId) {
                    $existe = DB::table('permisos')->where('perfil_id', $perfilId)->where('modulo_id', $moduloId)
                        ->where('empresa_id', $empresaId)->exists();
                    if ($existe) {
                        continue;
                    }
                    $fila = ['perfil_id' => $perfilId, 'modulo_id' => $moduloId, 'empresa_id' => $empresaId,
                        'ver' => (bool) $ver, 'crear' => (bool) $crear, 'editar' => (bool) $editar,
                        'eliminar' => (bool) $eliminar, 'anular' => (bool) $anular];
                    foreach (['created_at', 'updated_at'] as $ts) {
                        if (in_array($ts, $pcols, true)) {
                            $fila[$ts] = now();
                        }
                    }
                    DB::table('permisos')->insert($fila);
                }
            }
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
