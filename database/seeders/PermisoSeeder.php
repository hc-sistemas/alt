<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermisoSeeder extends Seeder
{
    public function run(): void
    {
        // IDs de perfiles (super_admin=1 se excluye — bypassa el sistema)
        $admin     = 2;
        $contador  = 3;
        $vendedor  = 4;
        $bodeguero = 5;
        $tecnico   = 6;

        // IDs de módulos
        $modulos = [
            'dashboard'     => 1,
            'ventas'        => 2,
            'compras'       => 3,
            'inventario'    => 4,
            'contabilidad'  => 5,
            'bancos'        => 6,
            'rrhh'          => 7,
            'taller'        => 8,
            'reportes'      => 9,
            'configuracion' => 10,
        ];

        $todos = array_values($modulos);

        $permisos = [
            // admin: acceso total a todo
            [$admin, $todos, true, true, true, true, true],

            // contador: ver en la mayoría, acceso total a contabilidad
            [$contador, [$modulos['dashboard'], $modulos['ventas'], $modulos['compras'], $modulos['inventario'], $modulos['bancos'], $modulos['rrhh'], $modulos['reportes']], true, false, false, false, false],
            [$contador, [$modulos['contabilidad']], true, true, true, true, true],
            [$contador, [$modulos['taller'], $modulos['configuracion']], false, false, false, false, false],

            // vendedor: acceso total a ventas, ver en algunos
            [$vendedor, [$modulos['dashboard'], $modulos['inventario'], $modulos['taller'], $modulos['reportes']], true, false, false, false, false],
            [$vendedor, [$modulos['ventas']], true, true, true, true, true],
            [$vendedor, [$modulos['compras'], $modulos['contabilidad'], $modulos['bancos'], $modulos['rrhh'], $modulos['configuracion']], false, false, false, false, false],

            // bodeguero: acceso total a inventario, ver en algunos
            [$bodeguero, [$modulos['dashboard'], $modulos['ventas'], $modulos['compras'], $modulos['taller'], $modulos['reportes']], true, false, false, false, false],
            [$bodeguero, [$modulos['inventario']], true, true, true, true, true],
            [$bodeguero, [$modulos['contabilidad'], $modulos['bancos'], $modulos['rrhh'], $modulos['configuracion']], false, false, false, false, false],

            // tecnico: acceso total a taller, ver en algunos
            [$tecnico, [$modulos['dashboard'], $modulos['inventario'], $modulos['reportes']], true, false, false, false, false],
            [$tecnico, [$modulos['taller']], true, true, true, true, true],
            [$tecnico, [$modulos['ventas'], $modulos['compras'], $modulos['contabilidad'], $modulos['bancos'], $modulos['rrhh'], $modulos['configuracion']], false, false, false, false, false],
        ];

        foreach ($permisos as [$perfilId, $moduloIds, $ver, $crear, $editar, $eliminar, $anular]) {
            foreach ($moduloIds as $moduloId) {
                DB::table('permisos')->updateOrInsert(
                    ['perfil_id' => $perfilId, 'modulo_id' => $moduloId],
                    [
                        'ver'      => $ver,
                        'crear'    => $crear,
                        'editar'   => $editar,
                        'eliminar' => $eliminar,
                        'anular'   => $anular,
                    ]
                );
            }
        }
    }
}
