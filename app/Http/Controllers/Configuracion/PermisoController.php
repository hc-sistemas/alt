<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
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
    public function index(Request $request): Response
    {
        $empresas = Empresa::orderBy('id')->get(['id', 'nombre_comercial', 'razon_social', 'etiqueta']);
        $empresaId = $request->integer('empresa_id') ?: $empresas->first()?->id;

        $perfiles = Perfil::with([
            'permisos' => fn ($q) => $q->where('empresa_id', $empresaId)->with('modulo'),
            'limiteDescuento',
        ])->get();

        // Módulos y submódulos en una sola lista (cada submódulo justo debajo de su módulo)
        $modulos = collect();
        foreach (Modulo::whereNull('padre_id')->with(['hijos' => fn ($q) => $q->orderBy('orden')])->orderBy('orden')->get() as $m) {
            $modulos->push($m->setAttribute('es_hijo', false));
            foreach ($m->hijos as $h) {
                $modulos->push($h->setAttribute('es_hijo', true));
            }
        }

        return Inertia::render('Configuracion/Permisos/Index', [
            'perfiles' => $perfiles,
            'modulos' => $modulos,
            'empresas' => $empresas,
            'empresaId' => $empresaId,
        ]);
    }

    public function actualizar(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'modulo_id' => ['required', 'exists:modulos,id'],
            'empresa_id' => ['required', 'exists:empresas,id'],
            'accion' => ['required', 'in:ver,crear,editar,eliminar,anular'],
            'valor' => ['required', 'boolean'],
        ]);

        Permiso::updateOrCreate(
            ['perfil_id' => $data['perfil_id'], 'modulo_id' => $data['modulo_id'], 'empresa_id' => $data['empresa_id']],
            [$data['accion'] => $data['valor']]
        );

        // Al marcar cualquier acción de un submódulo se marca también «Ver» del módulo padre: sin él la
        // persona no llegaría a la pantalla.
        if ($data['valor']) {
            $padreId = Modulo::where('id', $data['modulo_id'])->value('padre_id');
            if ($padreId) {
                Permiso::updateOrCreate(
                    ['perfil_id' => $data['perfil_id'], 'modulo_id' => $padreId, 'empresa_id' => $data['empresa_id']],
                    ['ver' => true]
                );
            }
        }

        return back();
    }

    public function actualizarLimite(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'perfil_id' => ['required', 'exists:perfiles,id'],
            'porcentaje_maximo' => ['required', 'numeric', 'min:0', 'max:100'],
            'puede_aprobar' => ['required', 'boolean'],
            'porcentaje_aprobacion_max' => ['required', 'numeric', 'min:0', 'max:100'],
            'solo_cero_o_maximo' => ['required', 'boolean'],
        ]);

        $perfil = Perfil::findOrFail($data['perfil_id']);

        if ((float) $data['porcentaje_aprobacion_max'] < (float) $data['porcentaje_maximo']) {
            return back()->withErrors([
                'porcentaje_aprobacion_max' => 'El % con aprobación no puede ser menor que el % sin aprobación.',
            ]);
        }

        LimiteDescuento::updateOrCreate(
            ['perfil_id' => $data['perfil_id']],
            $data
        );

        return back()->with('success', 'Límites de descuento guardados.');
    }
}
