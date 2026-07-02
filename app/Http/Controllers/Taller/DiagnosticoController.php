<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerDiagnostico;
use App\Models\TallerOrdenTrabajo;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class DiagnosticoController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function create(TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request, TallerOrdenTrabajo $orden)
    {
        return response()->json(['ok' => true]);
    }

    public function aprobar(Request $request, TallerDiagnostico $diagnostico)
    {
        return response()->json(['ok' => true]);
    }
}
