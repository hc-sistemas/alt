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
            'accion'      => $accion,
            'modulo'      => $modulo,
            'tabla'       => $tabla,
            'registro_id' => $registroId,
            'descripcion' => $descripcion,
            'ip_address'  => Request::ip(),
            'empresa_id'  => session('empresa_activa_id'),
            'created_at'  => now(),
        ]);
    }

    public function sesion(string $tipo, ?string $email = null, ?int $empresaId = null): void
    {
        DB::table('log_sesiones')->insert([
            'usuario_id' => Auth::id(),
            'email'      => $email ?? Auth::user()?->email,
            'tipo'       => $tipo,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'empresa_id' => $empresaId ?? session('empresa_activa_id'),
            'created_at' => now(),
        ]);
    }
}
