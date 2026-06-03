<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Models\Transportista;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class TransportistaController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');

        return Inertia::render('Personas/Transportistas/Index', [
            'transportistas' => Transportista::where('empresa_id', $empresaId)
                ->orderBy('razon_social')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc'          => ['required', 'string', 'regex:/^\d{10}(\d{3})?$/'],
            'placa'        => ['nullable', 'string', 'max:10'],
            'contacto'     => ['nullable', 'string', 'max:255'],
            'telefono'     => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:15'],
            'estado'       => ['boolean'],
        ], [
            'ruc.regex'      => 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos)',
            'telefono.min'   => 'Ingrese un teléfono válido (mínimo 7 dígitos)',
            'telefono.regex' => 'El teléfono solo puede contener números, espacios y + - ()',
        ]);

        $transportista = Transportista::create([
            ...$data,
            'empresa_id' => $empresaId,
        ]);

        $this->auditoria->documento('crear', 'personas', 'transportistas', $transportista->id,
            "Transportista {$transportista->razon_social} creado");

        return back()->with('success', 'Transportista creado correctamente.');
    }

    public function update(Request $request, Transportista $transportista): RedirectResponse
    {
        $data = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc'          => ['required', 'string', 'regex:/^\d{10}(\d{3})?$/'],
            'placa'        => ['nullable', 'string', 'max:10'],
            'contacto'     => ['nullable', 'string', 'max:255'],
            'telefono'     => ['nullable', 'regex:/^\+?[\d\s\-\(\)]+$/', 'min:7', 'max:15'],
            'estado'       => ['boolean'],
        ], [
            'ruc.regex'      => 'Ingrese una cédula (10 dígitos) o RUC (13 dígitos)',
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
        $transportista->delete();

        $this->auditoria->documento('eliminar', 'personas', 'transportistas', $transportista->id,
            "Transportista {$nombre} eliminado");

        return back()->with('success', 'Transportista eliminado correctamente.');
    }

    public function reporteLista(): HttpResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $transportistas = Transportista::where('empresa_id', $empresaId)
            ->orderBy('razon_social')
            ->get();

        $pdf = Pdf::loadView('reportes.personas.transportistas', [
            'transportistas' => $transportistas,
            'empresa'        => $empresa,
            'usuario'        => auth()->user(),
            'tipo'           => 'lista',
        ]);

        return $pdf->download('transportistas_' . now()->format('Ymd_His') . '.pdf');
    }

    public function reporteIndividual(Transportista $transportista): HttpResponse
    {
        $empresa = \App\Models\Empresa::find(session('empresa_activa_id'));

        $pdf = Pdf::loadView('reportes.personas.transportistas', [
            'transportista' => $transportista,
            'empresa'       => $empresa,
            'usuario'       => auth()->user(),
            'tipo'          => 'individual',
        ]);

        return $pdf->download('transportista_' . $transportista->ruc . '.pdf');
    }
}
