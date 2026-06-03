<?php

namespace App\Http\Controllers\Personas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personas\ClienteRequest;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ClienteController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = Cliente::where('empresa_id', $empresaId)
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('ruc_cedula', 'ilike', "%{$request->search}%")
                  ->orWhere('nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->estado !== null && $request->estado !== '', fn($q) =>
                $q->where('estado', $request->estado === 'activo')
            )
            ->orderBy('nombre');

        return Inertia::render('Personas/Clientes/Index', [
            'clientes' => $query->paginate(20)->withQueryString(),
            'filters' => $request->only(['search', 'estado']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Personas/Clientes/Form');
    }

    public function store(ClienteRequest $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $cliente = Cliente::create([
            ...$request->validated(),
            'empresa_id' => $empresaId,
        ]);

        $this->auditoria->documento('crear', 'personas', 'clientes', $cliente->id,
            "Cliente {$cliente->nombre} ({$cliente->ruc_cedula}) creado");

        return redirect()->route('personas.clientes.index')
            ->with('success', 'Cliente creado correctamente.');
    }

    public function edit(Cliente $cliente): Response
    {
        return Inertia::render('Personas/Clientes/Form', [
            'cliente' => $cliente,
        ]);
    }

    public function update(ClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($request->validated());

        $this->auditoria->documento('editar', 'personas', 'clientes', $cliente->id,
            "Cliente {$cliente->nombre} actualizado");

        return redirect()->route('personas.clientes.index')
            ->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $nombre = $cliente->nombre;
        $cliente->delete();

        $this->auditoria->documento('eliminar', 'personas', 'clientes', $cliente->id,
            "Cliente {$nombre} eliminado");

        return back()->with('success', 'Cliente eliminado correctamente.');
    }

    public function reporteLista(Request $request): HttpResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->when($request->estado, fn($q) => $q->where('estado', $request->estado === 'activo'))
            ->orderBy('nombre')
            ->get();

        $pdf = Pdf::loadView('reportes.personas.clientes', [
            'clientes' => $clientes,
            'empresa'  => $empresa,
            'usuario'  => auth()->user(),
            'tipo'     => 'lista',
        ]);

        return $pdf->download('clientes_' . now()->format('Ymd_His') . '.pdf');
    }

    public function reporteIndividual(Cliente $cliente): HttpResponse
    {
        $empresa = \App\Models\Empresa::find(session('empresa_activa_id'));

        $pdf = Pdf::loadView('reportes.personas.clientes', [
            'cliente'  => $cliente,
            'empresa'  => $empresa,
            'usuario'  => auth()->user(),
            'tipo'     => 'individual',
        ]);

        return $pdf->download('cliente_' . $cliente->ruc_cedula . '.pdf');
    }

    public function search(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');
        $term = $request->get('q', '');

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->where(fn($q) => $q
                ->where('ruc_cedula', 'ilike', "%{$term}%")
                ->orWhere('nombre', 'ilike', "%{$term}%")
            )
            ->select('id', 'ruc_cedula', 'nombre', 'email', 'telefono', 'tiene_credito', 'dias_credito')
            ->limit(10)
            ->get();

        return response()->json($clientes);
    }
}
