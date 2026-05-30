<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use App\Models\Empresa;
use App\Models\Secuencial;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmpresaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa = Empresa::with(['centrosCosto', 'secuenciales'])
            ->findOrFail($empresaId);

        return Inertia::render('Configuracion/Empresa/Index', [
            'empresa' => $empresa,
            'centros_costo' => $empresa->centrosCosto,
            'secuenciales' => $empresa->secuenciales,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa = Empresa::findOrFail($empresaId);

        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:300'],
            'nombre_comercial' => ['required', 'string', 'max:300'],
            'ruc' => ['required', 'string', 'size:13'],
            'direccion_matriz' => ['nullable', 'string', 'max:500'],
            'email_notificaciones' => ['nullable', 'email'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'ambiente_sri' => ['required', 'in:1,2'],
            'codigo_establecimiento' => ['required', 'string', 'size:3'],
            'codigo_punto_emision' => ['required', 'string', 'size:3'],
            'obligado_contabilidad' => ['boolean'],
            'contribuyente_especial' => ['boolean'],
            'numero_resolucion_agente_retencion' => ['nullable', 'string', 'max:50'],
        ]);

        $empresa->update($data);

        $this->auditoria->documento('editar', 'configuracion', 'empresas', $empresa->id,
            "Datos de empresa {$empresa->nombre_comercial} actualizados");

        return back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function actualizarSecuencial(Request $request, Secuencial $secuencial): RedirectResponse
    {
        $data = $request->validate([
            'siguiente' => ['required', 'integer', 'min:1'],
        ]);

        $secuencial->update($data);

        return back()->with('success', 'Secuencial actualizado.');
    }
}
