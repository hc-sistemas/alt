<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Models\Transportista;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransportistaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(): Response
    {
        return Inertia::render('Personas/Transportistas/Index', [
            'transportistas' => Transportista::orderBy('razon_social')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identificacion' => [
                'nullable', 'string', 'max:20',
                Rule::unique('transportistas', 'identificacion'),
            ],
            'razon_social'   => ['required', 'string', 'max:200'],
            'placa'          => ['nullable', 'string', 'max:20'],
            'email'          => ['nullable', 'email', 'max:200'],
            'telefono'       => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:20'],
            'direccion'      => ['nullable', 'string', 'max:300'],
            'estado'         => ['boolean'],
        ], [
            'telefono.min'   => 'Ingrese un teléfono válido (mínimo 7 dígitos)',
            'telefono.regex' => 'El teléfono solo puede contener números, espacios y + - ()',
        ]);

        $transportista = Transportista::create($data);

        $this->auditoria->documento('crear', 'personas', 'transportistas', $transportista->id,
            "Transportista {$transportista->razon_social} creado");

        return back()->with('success', 'Transportista creado correctamente.');
    }

    public function update(Request $request, Transportista $transportista): RedirectResponse
    {
        $data = $request->validate([
            'identificacion' => [
                'nullable', 'string', 'max:20',
                Rule::unique('transportistas', 'identificacion')
                    ->ignore($transportista->id),
            ],
            'razon_social'   => ['required', 'string', 'max:200'],
            'placa'          => ['nullable', 'string', 'max:20'],
            'email'          => ['nullable', 'email', 'max:200'],
            'telefono'       => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:20'],
            'direccion'      => ['nullable', 'string', 'max:300'],
            'estado'         => ['boolean'],
        ], [
            'telefono.min'   => 'Ingrese un teléfono válido (mínimo 7 dígitos)',
            'telefono.regex' => 'El teléfono solo puede contener números, espacios y + - ()',
        ]);

        $transportista->update($data);

        $this->auditoria->documento('editar', 'personas', 'transportistas', $transportista->id,
            "Transportista {$transportista->razon_social} actualizado");

        return back()->with('success', 'Transportista actualizado correctamente.');
    }

    public function destroy(Transportista $transportista): RedirectResponse
    {
        $nombre = $transportista->razon_social;

        if ($transportista->guiasRemision()->exists()) {
            $transportista->update(['estado' => false]);

            $this->auditoria->documento('desactivar', 'personas', 'transportistas', $transportista->id,
                "Transportista {$nombre} desactivado (tiene guías de remisión asociadas)");

            return back()->with('flash', [
                'type'    => 'warning',
                'message' => 'El transportista tiene guías de remisión asociadas y fue desactivado en lugar de eliminado.',
            ]);
        }

        $this->auditoria->documento('eliminar', 'personas', 'transportistas', $transportista->id,
            "Transportista {$nombre} eliminado");

        $transportista->delete();

        return back()->with('flash', [
            'type'    => 'success',
            'message' => 'Transportista eliminado correctamente.',
        ]);
    }

    public function reporteLista(Request $request): HttpResponse
    {
        $empresa = \App\Models\Empresa::find(session('empresa_activa_id'));

        $transportistas = Transportista::orderBy('razon_social')->get();

        $pdf = Pdf::loadView('reportes.personas.transportistas', [
            'transportistas' => $transportistas,
            'empresa'        => $empresa,
            'usuario'        => auth()->user(),
            'tipo'           => 'lista',
        ]);

        $nombre = 'transportistas_' . now()->format('Ymd_His') . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($nombre)
            : $pdf->stream($nombre);
    }

    public function reporteIndividual(Request $request, Transportista $transportista): HttpResponse
    {
        $empresa = \App\Models\Empresa::find(session('empresa_activa_id'));

        $pdf = Pdf::loadView('reportes.personas.transportistas', [
            'transportista' => $transportista,
            'empresa'       => $empresa,
            'usuario'       => auth()->user(),
            'tipo'          => 'individual',
        ]);

        $nombre = 'transportista_' . $transportista->id . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($nombre)
            : $pdf->stream($nombre);
    }
}
