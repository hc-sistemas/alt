<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerOrdenTrabajo;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class LiquidacionController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function show(TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }

    public function liquidar(Request $request, TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }
}
