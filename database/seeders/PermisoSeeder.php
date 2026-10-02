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
            // Separado de 'rrhh' (CHECKLIST_ERRORES_COMPLICACIONES.md, C4): ver
            // ModuloSeeder.php, que es quien realmente crea esta fila.
            'asistencia'    => 11,
        ];

        $todos = array_values($modulos);

        $permisos = [
            // admin: acceso total a todo
            [$admin, $todos, true, true, true, true, true],

            // contador: ver en la mayoría, acceso total a contabilidad
            [$contador, [$modulos['dashboard'], $modulos['ventas'], $modulos['compras'], $modulos['inventario'], $modulos['bancos'], $modulos['rrhh'], $modulos['reportes'], $modulos['asistencia']], true, false, false, false, false],
            [$contador, [$modulos['contabilidad']], true, true, true, true, true],
            [$contador, [$modulos['taller'], $modulos['configuracion']], false, false, false, false, false],

            // vendedor: acceso total a ventas, ver en algunos. 'reportes' NO va
            // aquí — el vendedor no debe ver Reportes SRI (CHECKLIST_ERRORES_COMPLICACIONES.md, C2).
            // 'asistencia' con ver+crear para poder marcar su propia entrada/salida (C4).
            [$vendedor, [$modulos['dashboard'], $modulos['inventario'], $modulos['taller']], true, false, false, false, false],
            [$vendedor, [$modulos['ventas']], true, true, true, true, true],
            [$vendedor, [$modulos['asistencia']], true, true, false, false, false],
            [$vendedor, [$modulos['compras'], $modulos['contabilidad'], $modulos['bancos'], $modulos['rrhh'], $modulos['reportes'], $modulos['configuracion']], false, false, false, false, false],

            // bodeguero: acceso total a inventario, ver en algunos. No ve
            // Dashboard, Ventas, Compras (por ahí cuelga Proveedores), Taller
            // ni Reportes SRI (CHECKLIST_ERRORES_COMPLICACIONES.md, C3).
            [$bodeguero, [$modulos['reportes']], false, false, false, false, false],
            [$bodeguero, [$modulos['inventario']], true, true, true, true, true],
            [$bodeguero, [$modulos['asistencia']], true, true, false, false, false],
            [$bodeguero, [$modulos['dashboard'], $modulos['ventas'], $modulos['compras'], $modulos['taller'], $modulos['contabilidad'], $modulos['bancos'], $modulos['rrhh'], $modulos['configuracion']], false, false, false, false, false],

            // tecnico: acceso total a taller, ver en algunos
            [$tecnico, [$modulos['dashboard'], $modulos['inventario'], $modulos['reportes']], true, false, false, false, false],
            [$tecnico, [$modulos['taller']], true, true, true, true, true],
            [$tecnico, [$modulos['asistencia']], true, true, false, false, false],
            [$tecnico, [$modulos['ventas'], $modulos['compras'], $modulos['contabilidad'], $modulos['bancos'], $modulos['rrhh'], $modulos['configuracion']], false, false, false, false, false],
        ];

        $empresaIds = DB::table('empresas')->pluck('id');

        foreach ($permisos as [$perfilId, $moduloIds, $ver, $crear, $editar, $eliminar, $anular]) {
            foreach ($moduloIds as $moduloId) {
                foreach ($empresaIds as $empresaId) {
                    DB::table('permisos')->updateOrInsert(
                        ['perfil_id' => $perfilId, 'modulo_id' => $moduloId, 'empresa_id' => $empresaId],
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
}
