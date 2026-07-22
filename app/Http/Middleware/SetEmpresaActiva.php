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
        if (Auth::check() && !$request->session()->has('empresa_activa_id')) {
            $user = Auth::user();
            $primeraEmpresa = $user->empresas->first();
            if ($primeraEmpresa) {
                $request->session()->put('empresa_activa_id', $primeraEmpresa->id);
            }
        }

        return $next($request);
    }
}
