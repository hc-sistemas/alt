<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\TallerIngreso;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;

class IngresoController extends Controller
{
    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function index(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function create(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function store(Request $request)
    {
        return response()->json(['ok' => true]);
    }

    public function show(TallerIngreso $ingreso)
    {
        return response()->json(['ok' => true]);
    }
}
