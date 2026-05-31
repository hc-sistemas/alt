<?php

namespace Database\Seeders;

use App\Models\Modulo;
use App\Models\Perfil;
use App\Models\Permiso;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class ModuloSeeder extends Seeder
{
    public function run(): void
    {
        $tieneEstado = Schema::hasColumn('modulos', 'estado');
        $tieneClave = Schema::hasColumn('modulos', 'clave');
        // Columnas reales de permisos
        $colVerReal = Schema::hasColumn('permisos', 'puede_ver') ? 'puede_ver' : 'ver';

        $modulosData = [
            ['nombre' => 'Dashboard', 'clave' => 'dashboard', 'icono' => 'LayoutDashboard', 'orden' => 1],
            ['nombre' => 'Ventas', 'clave' => 'ventas', 'icono' => 'FileText', 'orden' => 2],
            ['nombre' => 'Compras', 'clave' => 'compras', 'icono' => 'ShoppingCart', 'orden' => 3],
            ['nombre' => 'Inventario', 'clave' => 'inventario', 'icono' => 'Package', 'orden' => 4],
            ['nombre' => 'Contabilidad', 'clave' => 'contabilidad', 'icono' => 'BookOpen', 'orden' => 5],
            ['nombre' => 'Bancos', 'clave' => 'bancos', 'icono' => 'Landmark', 'orden' => 6],
            ['nombre' => 'RRHH', 'clave' => 'rrhh', 'icono' => 'Users', 'orden' => 7],
            ['nombre' => 'Taller', 'clave' => 'taller', 'icono' => 'Wrench', 'orden' => 8],
            ['nombre' => 'Reportes', 'clave' => 'reportes', 'icono' => 'BarChart2', 'orden' => 9],
            ['nombre' => 'Configuración', 'clave' => 'configuracion', 'icono' => 'Settings', 'orden' => 10],
        ];  

        $creados = [];
        foreach ($modulosData as $data) {
                if (!$tieneEstado) unset($data['estado']);

            $creados[$data['clave']] = Modulo::firstOrCreate(
                ['clave' => $data['clave']],
                $data
            );
        }

        $perfiles = Perfil::all()->keyBy('nombre');
        if ($perfiles->isEmpty()) return;

        $usaPuedeVer = ($colVerReal === 'puede_ver');

        $matrizPermisos = [
            'super_admin' => ['*' => ['ver', 'crear', 'editar', 'eliminar', 'anular']],
            'admin' => [
                '*' => ['ver', 'crear', 'editar', 'eliminar', 'anular'],
                'contabilidad' => ['ver'],
            ],
            'contador' => [
                'contabilidad' => ['ver', 'crear', 'editar', 'eliminar', 'anular'],
                'compras' => ['ver', 'crear', 'editar', 'eliminar', 'anular'],
                'bancos' => ['ver', 'crear', 'editar', 'eliminar', 'anular'],
                'ventas' => ['ver'], 'inventario' => ['ver'], 'reportes' => ['ver'],
            ],
            'vendedor' => ['ventas' => ['ver', 'crear', 'editar'], 'inventario' => ['ver'], 'reportes' => ['ver']],
            'bodeguero' => ['inventario' => ['ver', 'crear', 'editar', 'eliminar'], 'ventas' => ['ver']],
            'tecnico' => ['taller' => ['ver', 'crear', 'editar', 'eliminar']],
        ];

        foreach ($matrizPermisos as $nombrePerfil => $permisosPerfil) {
            $perfil = $perfiles[$nombrePerfil] ?? null;
            if (!$perfil) continue;

            foreach ($creados as $codigo => $modulo) {
                $acciones = $permisosPerfil[$codigo] ?? ($permisosPerfil['*'] ?? []);

                if ($usaPuedeVer) {
                    Permiso::firstOrCreate(
                        ['perfil_id' => $perfil->id, 'modulo_id' => $modulo->id],
                        [
                            'puede_ver' => in_array('ver', $acciones),
                            'puede_crear' => in_array('crear', $acciones),
                            'puede_editar' => in_array('editar', $acciones),
                            'puede_eliminar' => in_array('eliminar', $acciones),
                            'puede_anular' => in_array('anular', $acciones),
                        ]
                    );
                } else {
                    Permiso::firstOrCreate(
                        ['perfil_id' => $perfil->id, 'modulo_id' => $modulo->id],
                        [
                            'ver' => in_array('ver', $acciones),
                            'crear' => in_array('crear', $acciones),
                            'editar' => in_array('editar', $acciones),
                            'eliminar' => in_array('eliminar', $acciones),
                            'anular' => in_array('anular', $acciones),
                        ]
                    );
                }
            }
        }
    }
}
