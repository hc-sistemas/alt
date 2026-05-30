<?php

namespace App\Observers;

use App\Models\Usuario;
use App\Models\LogCambioCritico;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class UsuarioObserver
{
    private array $camposCriticos = ['password', 'email', 'estado', 'perfil_id', 'codigo_aprobacion'];

    public function updating(Usuario $usuario): void
    {
        $dirty = $usuario->getDirty();
        $original = $usuario->getOriginal();

        foreach ($this->camposCriticos as $campo) {
            if (!array_key_exists($campo, $dirty)) {
                continue;
            }

            $valorAnterior = $original[$campo] ?? null;
            $valorNuevo = $dirty[$campo];

            if (in_array($campo, ['password', 'codigo_aprobacion'])) {
                $valorAnterior = $valorAnterior ? '[hasheado]' : null;
                $valorNuevo = '[hasheado]';
            }

            LogCambioCritico::create([
                'usuario_id' => Auth::id(),
                'empresa_id' => Auth::user()?->empresa_id,
                'tabla' => 'usuarios',
                'registro_id' => $usuario->id,
                'campo' => $campo,
                'valor_anterior' => $valorAnterior,
                'valor_nuevo' => $valorNuevo,
                'ip_address' => Request::ip(),
            ]);
        }
    }
}
