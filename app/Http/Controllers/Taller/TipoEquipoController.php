<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerTipoEquipo;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class TipoEquipoController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function index(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function update(Request $request, TallerTipoEquipo $tipoEquipo)
    {
        return response()->json(['ok' => true]);
    }

    public function destroy(TallerTipoEquipo $tipoEquipo)
    {
        return response()->json(['ok' => true]);
    }
}
