<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea el módulo 'asistencia' como módulo propio, separado de 'rrhh'
 * (CHECKLIST_ERRORES_COMPLICACIONES.md, ítem C4). Antes /rrhh/asistencia
 * colgaba del permiso 'rrhh', así que un perfil solo podía marcar su propia
 * asistencia si tenía acceso a TODO RRHH (Colaboradores, Nómina, Préstamos,
 * Liquidaciones...). Vendedor, Bodeguero y Técnico deben poder marcar su
 * entrada/salida sin ese acceso — ver routes/web.php (grupo
 * 'permiso:asistencia,ver' / 'permiso:asistencia,crear').
 *
 * Idempotente: no crea el módulo si ya existe (por si esta migración corre
 * dos veces, o si alguien ya lo agregó a mano), y updateOrInsert en permisos
 * no duplica filas.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('modulos') || !Schema::hasTable('permisos')
            || !Schema::hasTable('perfiles') || !Schema::hasTable('empresas')) {
            return;
        }

        $moduloId = DB::table('modulos')->where('clave', 'asistencia')->value('id');

        if (!$moduloId) {
            $siguienteOrden = (int) DB::table('modulos')->max('orden') + 1;

            $moduloId = DB::table('modulos')->insertGetId([
                'nombre'     => 'Asistencia',
                'clave'      => 'asistencia',
                'icono'      => 'ClipboardList',
                'orden'      => $siguienteOrden,
                'padre_id'   => null,
                'estado'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $perfiles = DB::table('perfiles')->pluck('id', 'nombre');
        $empresaIds = DB::table('empresas')->pluck('id');

        // super_admin (bypassa el sistema de permisos) se excluye, igual que
        // en PermisoSeeder. admin: acceso total, igual que en los otros 10
        // módulos. contador: solo ver (como ya tenía sobre RRHH). Vendedor,
        // Bodeguero y Técnico: ver + crear, para poder marcar su propia
        // entrada/salida sin editar ni eliminar marcaciones ajenas.
        $accesos = [
            'admin'     => [true, true, true, true, true],
            'contador'  => [true, false, false, false, false],
            'vendedor'  => [true, true, false, false, false],
            'bodeguero' => [true, true, false, false, false],
            'tecnico'   => [true, true, false, false, false],
        ];

        foreach ($accesos as $perfilNombre => [$ver, $crear, $editar, $eliminar, $anular]) {
            $perfilId = $perfiles[$perfilNombre] ?? null;
            if (!$perfilId) {
                continue;
            }

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

    public function down(): void
    {
        // No se revierte: borrar el módulo dejaría sin permiso 'asistencia' a
        // todo el mundo y rompería /rrhh/asistencia (ver routes/web.php).
    }
};
