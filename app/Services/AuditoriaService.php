<?php

namespace App\Services;

use App\Models\LogDocumento;
use App\Models\LogSesion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditoriaService
{
    public function documento(
        string $accion,
        string $modulo,
        string $tabla,
        ?int $registroId = null,
        ?string $descripcion = null
    ): void {
        LogDocumento::create([
            'usuario_id' => Auth::id(),
            'username' => Auth::user()?->username,
            'accion' => $accion,
            'modulo' => $modulo,
            'tabla' => $tabla,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'ip' => Request::ip(),
            'empresa_id' => session('empresa_activa_id'),
            'fecha' => now(),
        ]);
    }

    public function sesion(string $tipo, ?string $email = null, ?int $empresaId = null): void
    {
        LogSesion::create([
            'usuario_id' => Auth::id(),
            'username' => $email ?? Auth::user()?->username ?? Auth::user()?->email,
            'tipo' => $tipo,
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'fecha' => now(),
        ]);
    }
}
