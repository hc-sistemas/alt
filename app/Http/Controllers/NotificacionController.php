<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;

class NotificacionController extends Controller
{
    public function index()
    {
        $notificaciones = Notificacion::where('usuario_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->limit(30)
            ->get();

        $noLeidas = $notificaciones->where('leida', false)->count();

        return response()->json([
            'notificaciones' => $notificaciones,
            'no_leidas'      => $noLeidas,
        ]);
    }

    public function marcarLeida(int $id)
    {
        Notificacion::where('id', $id)
            ->where('usuario_id', auth()->id())
            ->update(['leida' => true, 'leida_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function marcarTodasLeidas()
    {
        Notificacion::where('usuario_id', auth()->id())
            ->where('leida', false)
            ->update(['leida' => true, 'leida_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
