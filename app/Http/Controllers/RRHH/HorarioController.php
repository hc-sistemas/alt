<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Horario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HorarioController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'descripcion'        => 'required|string|max:100',
            'hora_entrada'       => 'required|date_format:H:i',
            'hora_salida'        => 'required|date_format:H:i',
            'tolerancia_minutos' => 'nullable|integer|min:0|max:120',
        ]);

        Horario::create($data);

        return back()->with('success', 'Horario creado correctamente.');
    }
}
