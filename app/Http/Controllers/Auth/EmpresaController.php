<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    public function seleccionar(): Response
    {
        $user = Auth::user();
        return Inertia::render('Auth/SeleccionarEmpresa', [
            'empresas' => $user->empresas->map(fn($e) => [
                'id' => $e->id,
                'nombre_comercial' => $e->nombre_comercial,
                'ruc' => $e->ruc,
                'logo' => $e->logo,
            ]),
        ]);
    }

    public function cambiar(Request $request): RedirectResponse
    {
        $request->validate(['empresa_id' => ['required', 'integer']]);

        $user = Auth::user();
        $empresa = $user->empresas->find($request->empresa_id);

        if (!$empresa) {
            return back()->withErrors(['empresa_id' => 'No tienes acceso a esa empresa.']);
        }

        $request->session()->put('empresa_activa_id', $empresa->id);

        return redirect()->route('dashboard');
    }
}
