<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetEmpresaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $idsPermitidos = $user->empresas()->pluck('empresas.id')->map(fn ($i) => (int) $i)->all();
            $activa = $request->session()->get('empresa_activa_id');

            // La empresa activa debe seguir siendo una a la que el usuario tiene acceso: si a alguien se le
            // quita una empresa mientras tiene la sesión abierta, no conserva el acceso hasta cerrar sesión.
            if ($activa !== null && !in_array((int) $activa, $idsPermitidos, true)) {
                $request->session()->forget('empresa_activa_id');
                $activa = null;
            }

            if ($activa === null && $idsPermitidos) {
                $request->session()->put('empresa_activa_id', $idsPermitidos[0]);
            }
        }

        return $next($request);
    }
}
