<?php

namespace App\Support;

use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve a dónde debe aterrizar un usuario cuando el Dashboard no le está
 * permitido (CHECKLIST_ERRORES_COMPLICACIONES.md, ítem C3 — antes /dashboard
 * no se podía ocultar por perfil: sin middleware de permiso, forzado siempre
 * visible en el Sidebar, y destino fijo del login). Se usa desde
 * DashboardController::index(), que es el único punto de entrada real (login,
 * selección de empresa y "/" ya redirigen a la ruta 'dashboard').
 */
class LandingRoute
{
    /**
     * módulo (clave) => nombre de ruta de su pantalla principal, en el orden
     * en que se prueban. El primero en el que el usuario tenga ver=true gana.
     */
    private const RUTAS_POR_MODULO = [
        'ventas'        => 'ventas.facturas.index',
        'inventario'    => 'inventario.productos.index',
        'compras'       => 'compras.facturas.index',
        'taller'        => 'taller.ingresos.index',
        'bancos'        => 'bancos.cajas.index',
        'contabilidad'  => 'contabilidad.asientos.index',
        'rrhh'          => 'rrhh.colaboradores.index',
        'reportes'      => 'reportes.sri.index',
        'configuracion' => 'configuracion.usuarios.index',
    ];

    /**
     * true si el usuario puede ver el módulo 'dashboard' en la empresa dada
     * (super_admin/admin siempre pueden, igual que VerificarPermiso).
     */
    public static function puedeVerDashboard(Usuario $usuario, int $empresaId): bool
    {
        return self::puedeVer($usuario, $empresaId, 'dashboard');
    }

    /**
     * Nombre de ruta al que debe ir el usuario si no puede ver el Dashboard:
     * el primer módulo de RUTAS_POR_MODULO en el que tenga ver=true. Si no
     * tiene ninguno (perfil sin nada asignado todavía), se queda en
     * 'dashboard' — mejor la pantalla vacía que un ciclo de redirects.
     */
    public static function primeraDisponible(Usuario $usuario, int $empresaId): string
    {
        foreach (self::RUTAS_POR_MODULO as $modulo => $ruta) {
            if (self::puedeVer($usuario, $empresaId, $modulo)) {
                return $ruta;
            }
        }

        return 'dashboard';
    }

    private static function puedeVer(Usuario $usuario, int $empresaId, string $moduloClave): bool
    {
        if (in_array($usuario->perfil?->nombre, ['super_admin', 'admin'], true)) {
            return true;
        }

        return (bool) DB::table('permisos')
            ->join('modulos', 'modulos.id', '=', 'permisos.modulo_id')
            ->where('permisos.perfil_id', $usuario->perfil_id)
            ->where('permisos.empresa_id', $empresaId)
            ->where('modulos.clave', $moduloClave)
            ->value('permisos.ver');
    }
}
