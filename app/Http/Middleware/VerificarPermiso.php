<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $moduloClave, string $accion = 'ver'): Response
    {
        $user = Auth::user();

        if (!$user) {
            abort(401);
        }

        // Super Admin y Admin (los dueños del sistema) pueden todo y no consultan la matriz, igual que el
        // frontend (HandleInertiaRequests). El resto de perfiles se rige por lo guardado en
        // Configuración → Permisos (02-01 §2.3).
        if ($user->esAdministrador()) {
            return $next($request);
        }

        $permitido = DB::table('permisos')
            ->join('modulos', 'modulos.id', '=', 'permisos.modulo_id')
            ->where('permisos.perfil_id', $user->perfil_id)
            ->where('permisos.empresa_id', $request->session()->get('empresa_activa_id'))
            ->where('modulos.clave', $moduloClave)
            ->value("permisos.{$accion}");

        if (!$permitido) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
