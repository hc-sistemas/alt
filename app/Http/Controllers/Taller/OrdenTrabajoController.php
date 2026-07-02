<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerOrdenTrabajo;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class OrdenTrabajoController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function index(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function show(TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }

    public function cambiarEstado(Request $request, TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }
}
