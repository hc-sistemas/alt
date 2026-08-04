<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $notificacionesNoLeidas = 0;
        if ($user) {
            $notificacionesNoLeidas = \App\Models\Notificacion::where('usuario_id', $user->id)
                ->where('leida', false)
                ->count();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'perfil' => $user->perfil?->nombre,
                    'perfil_clave' => $user->perfil?->nombre,
                    'empresa_id' => $user->empresa_id,
                    'centro_costo_id' => $user->centro_costo_id,
                    'avatar' => $user->avatar,
                ] : null,
            ],
            'permisos' => function () use ($user, $empresaActivaId) {
                if (!$user) return [];

                if ($user->perfil?->nombre === 'super_admin') return '*';

                return DB::table('permisos')
                    ->join('modulos', 'modulos.id', '=', 'permisos.modulo_id')
                    ->where('permisos.perfil_id', $user->perfil_id)
                    ->where('permisos.empresa_id', $empresaActivaId)
                    ->select('modulos.clave', 'permisos.ver', 'permisos.crear', 'permisos.editar', 'permisos.eliminar', 'permisos.anular')
                    ->get()
                    ->mapWithKeys(fn ($p) => [
                        $p->clave => [
                            'ver'      => (bool) $p->ver,
                            'crear'    => (bool) $p->crear,
                            'editar'   => (bool) $p->editar,
                            'eliminar' => (bool) $p->eliminar,
                            'anular'   => (bool) $p->anular,
                        ]
                    ])
                    ->toArray();
            },
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
            'notificaciones_no_leidas' => $notificacionesNoLeidas,
        ];
    }
}
