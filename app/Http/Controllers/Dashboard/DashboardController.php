<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $user = Auth::user();
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Dashboard/Index', [
            'stats' => [
                'ventas_hoy' => 0,
                'ventas_ayer' => 0,
                'meta_mes' => 0,
                'ventas_mes' => 0,
            ],
            'notificaciones_count' => $user->notificacionesNoLeidas()->count(),
        ]);
    }
}
