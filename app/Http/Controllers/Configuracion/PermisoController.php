<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\LimiteDescuento;
use App\Models\Modulo;
use App\Models\Perfil;
use App\Models\Permiso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermisoController extends Controller
{
    public function index(): Response
    {
        $perfiles = Perfil::with([
            'permisos.modulo',
            'limiteDescuento',
        ])->get();

        $modulos = Modulo::whereNull('padre_id')
            ->with('hijos')
            ->orderBy('orden')
            ->get();

        return Inertia::render('Configuracion/Permisos/Index', [
            'perfiles' => $perfiles,
            'modulos' => $modulos,
        ]);
    }

    public function actualizar(Request $request): JsonResponse
    {
        $data = $request->validate([
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'modulo_id' => ['required', 'exists:modulos,id'],
            'accion' => ['required', 'in:ver,crear,editar,eliminar,anular'],
            'valor' => ['required', 'boolean'],
        ]);

        Permiso::updateOrCreate(
            ['perfil_id' => $data['perfil_id'], 'modulo_id' => $data['modulo_id']],
            [$data['accion'] => $data['valor']]
        );

        return response()->json(['ok' => true]);
    }

    public function actualizarLimite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'porcentaje_maximo' => ['required', 'numeric', 'min:0', 'max:100'],
            'puede_aprobar' => ['required', 'boolean'],
            'porcentaje_aprobacion_max' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        LimiteDescuento::updateOrCreate(
            ['perfil_id' => $data['perfil_id']],
            $data
        );

        return response()->json(['ok' => true]);
    }
}
