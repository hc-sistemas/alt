<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $empresaActivaId = $request->session()->get('empresa_activa_id');

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'perfil' => $user->perfil?->nombre,
                    'empresa_id' => $user->empresa_id,
                    'centro_costo_id' => $user->centro_costo_id,
                    'avatar' => $user->avatar,
                ] : null,
            ],
            'empresa_activa' => $empresaActivaId ? \App\Models\Empresa::find($empresaActivaId)?->only([
                'id', 'nombre_comercial', 'ruc', 'logo',
            ]) : null,
            'empresas_usuario' => $user ? $user->empresas->map(fn($e) => [
                'id' => $e->id,
                'nombre_comercial' => $e->nombre_comercial,
                'ruc' => $e->ruc,
                'logo' => $e->logo,
            ]) : [],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
            ],
            'ziggy' => fn () => [
                ...(new \Tighten\Ziggy\Ziggy)->toArray(),
                'location' => $request->url(),
            ],
        ];
    }
}
