<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige filas de `permisos` que quedaron mal cargadas para Vendedor y
 * Bodeguero (CHECKLIST_ERRORES_COMPLICACIONES.md, ítems C2/C3 y la
 * inconsistencia entre empresas hallada en la auditoría):
 *
 *  - Vendedor tenía `reportes.ver=true` en ambas empresas: veía Reportes SRI
 *    (ya protegido por middleware, pero el dato seguía habilitándolo).
 *  - Bodeguero de Altamira Import (empresa 2) tenía `ventas.ver`, `compras.ver`
 *    y `taller.ver` en true, mientras que en Matriz (empresa 1) esos mismos
 *    módulos ya estaban en false — dato no replicado entre empresas.
 *  - Bodeguero tenía `dashboard.ver=true` en ambas empresas: con el Dashboard
 *    ahora ocultable por permiso (ver DashboardController::index), debe
 *    aterrizar en Inventario en vez de en el Dashboard.
 *
 * PermisoSeeder.php se corrigió también, para que una reinstalación desde
 * cero ya nazca así. Esta migración es solo para bases ya sembradas.
 * Solo se actualiza si el valor sigue siendo el equivocado conocido: si
 * alguien ya lo corrigió a mano con otro criterio, no se pisa.
 */
return new class extends Migration {
    /** [perfil, módulo] => valor incorrecto conocido de `ver` a corregir a false. */
    private const CORRECCIONES = [
        ['vendedor', 'reportes'],
        ['bodeguero', 'ventas'],
        ['bodeguero', 'compras'],
        ['bodeguero', 'taller'],
        ['bodeguero', 'dashboard'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('permisos') || !Schema::hasTable('perfiles') || !Schema::hasTable('modulos')) {
            return;
        }

        foreach (self::CORRECCIONES as [$perfilNombre, $moduloClave]) {
            $perfilId = DB::table('perfiles')->where('nombre', $perfilNombre)->value('id');
            $moduloId = DB::table('modulos')->where('clave', $moduloClave)->value('id');

            if (!$perfilId || !$moduloId) {
                continue;
            }

            DB::table('permisos')
                ->where('perfil_id', $perfilId)
                ->where('modulo_id', $moduloId)
                ->where('ver', true)
                ->update(['ver' => false]);
        }
    }

    public function down(): void
    {
        // No se revierte: restaurar ver=true en estos módulos reabriría los
        // huecos de permisos que motivaron esta corrección.
    }
};
