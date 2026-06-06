<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personas\ProveedorRequest;
use App\Models\Proveedor;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProveedorController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = Proveedor::where('empresa_id', $empresaId)
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('identificacion', 'ilike', "%{$request->search}%")
                  ->orWhere('razon_social', 'ilike', "%{$request->search}%");
            }))
            ->when($request->tipo && $request->tipo !== 'todos', fn($q) =>
                $q->where('tipo', $request->tipo)
            )
            ->when($request->estado !== null && $request->estado !== '', fn($q) =>
                $q->where('estado', $request->estado === 'activo')
            )
            ->orderBy('razon_social');

        return Inertia::render('Personas/Proveedores/Index', [
            'proveedores' => $query->paginate(20)->withQueryString(),
            'filters'     => $request->only(['search', 'tipo', 'estado']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Personas/Proveedores/Form');
    }

    public function store(ProveedorRequest $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validated();
        if ($data['tipo'] === 'nacional') {
            $data['pais'] = 'ECUADOR';
        }

        $proveedor = Proveedor::create([
            ...$data,
            'empresa_id' => $empresaId,
        ]);

        $this->auditoria->documento('crear', 'personas', 'proveedores', $proveedor->id,
            "Proveedor {$proveedor->razon_social} creado ({$proveedor->tipo})");

        return redirect()->route('personas.proveedores.index')
            ->with('success', 'Proveedor creado correctamente.');
    }

    public function edit(Proveedor $proveedor): Response
    {
        return Inertia::render('Personas/Proveedores/Form', [
            'proveedor' => $proveedor,
        ]);
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor): RedirectResponse
    {
        $data = $request->validated();
        if ($data['tipo'] === 'nacional') {
            $data['pais'] = 'ECUADOR';
        }

        $proveedor->update($data);

        $this->auditoria->documento('editar', 'personas', 'proveedores', $proveedor->id,
            "Proveedor {$proveedor->razon_social} actualizado");

        return redirect()->route('personas.proveedores.index')
            ->with('success', 'Proveedor actualizado correctamente.');
    }

    public function reporteLista(Request $request): HttpResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->when($request->tipo && $request->tipo !== 'todos', fn($q) => $q->where('tipo', $request->tipo))
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado === 'activo'))
            ->orderBy('razon_social')
            ->get();

        $pdf = Pdf::loadView('reportes.personas.proveedores', [
            'proveedores' => $proveedores,
            'empresa'     => $empresa,
            'usuario'     => auth()->user(),
            'tipo'        => 'lista',
        ]);

        return $pdf->download('proveedores_' . now()->format('Ymd_His') . '.pdf');
    }

    public function reporteIndividual(Proveedor $proveedor): HttpResponse
    {
        $empresa = \App\Models\Empresa::find(session('empresa_activa_id'));

        $pdf = Pdf::loadView('reportes.personas.proveedores', [
            'proveedor' => $proveedor,
            'empresa'   => $empresa,
            'usuario'   => auth()->user(),
            'tipo'      => 'individual',
        ]);

        return $pdf->download('proveedor_' . ($proveedor->identificacion ?? $proveedor->id) . '.pdf');
    }

    public function destroy(Proveedor $proveedor): RedirectResponse
    {
        $nombre = $proveedor->razon_social;
        $proveedor->delete();

        $this->auditoria->documento('eliminar', 'personas', 'proveedores', $proveedor->id,
            "Proveedor {$nombre} eliminado");

        return back()->with('success', 'Proveedor eliminado correctamente.');
    }
}
