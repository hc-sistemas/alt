<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        DB::table('log_documentos')->insert([
            'usuario_id'  => Auth::id(),
            'username'    => Auth::user()?->username ?? Auth::user()?->email ?? 'sistema',
            'accion'      => $accion,
            'modulo'      => $modulo,
            'tabla'       => $tabla,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'ip_address'  => Request::ip(),   // ← era 'ip'
            'empresa_id'  => session('empresa_activa_id'),
            'created_at'  => now(),           // ← era 'fecha'
        ]);
    }

    public function sesion(string $tipo, ?string $email = null, ?int $empresaId = null): void
    {
        DB::table('log_sesiones')->insert([
            'usuario_id' => Auth::id(),
            'username'   => $email ?? Auth::user()?->username ?? Auth::user()?->email ?? 'sistema',
            'tipo'       => $tipo,
            'ip'         => Request::ip(),
            'user_agent' => Request::userAgent(),
            // empresa_id eliminado — no existe en log_sesiones
            'fecha'      => now(),
        ]);
    }
}