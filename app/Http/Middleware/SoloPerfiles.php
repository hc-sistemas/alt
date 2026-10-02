<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restringe una ruta a una lista fija de perfiles por nombre, para los casos
 * puntuales que no ameritan un módulo de permiso propio en la tabla
 * `modulos` (ej. C6 en CHECKLIST_ERRORES_COMPLICACIONES.md: ocultar
 * Inventario → Movimientos a todos menos Bodeguero/Admin/Contador, dentro
 * del mismo módulo 'inventario' que ya usan Vendedor y Técnico para otras
 * pantallas). super_admin siempre pasa, igual que VerificarPermiso.
 *
 * Uso en routes/web.php: ->middleware('solo_perfiles:admin,contador,bodeguero')
 */
class SoloPerfiles
{
    public function handle(Request $request, Closure $next, string ...$perfiles): Response
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        if ($user->perfil?->nombre === 'super_admin' || in_array($user->perfil?->nombre, $perfiles, true)) {
            return $next($request);
        }

        abort(403, 'No tienes permiso para acceder a esta sección.');
    }
}
