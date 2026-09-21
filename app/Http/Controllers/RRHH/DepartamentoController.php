<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Departamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepartamentoController extends Controller
{
    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');

        $departamentos = Departamento::where('empresa_id', $empresaId)
            ->withCount([
                'colaboradores',
                'colaboradores as colaboradores_activos_count' => fn($q) => $q->where('estado', true),
            ])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('RRHH/Departamentos/Index', [
            'departamentos'         => $departamentos,
            'colaboradores_sin_dep' => Colaborador::where('empresa_id', $empresaId)
                ->where('estado', true)->whereNull('departamento_id')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $data      = $this->validar($request, $empresaId);

        Departamento::create($data + ['empresa_id' => $empresaId, 'estado' => true]);

        return back()->with('success', "Departamento {$data['nombre']} creado.");
    }

    public function update(Request $request, Departamento $departamento): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $this->asegurarEmpresa($departamento, $empresaId);

        $data = $this->validar($request, $empresaId, $departamento->id);

        DB::transaction(function () use ($departamento, $data) {
            $departamento->update($data);
            // colaboradores.departamento guarda el nombre desnormalizado (filtros/reportes)
            Colaborador::where('departamento_id', $departamento->id)
                ->update(['departamento' => $data['nombre']]);
        });

        return back()->with('success', 'Departamento actualizado.');
    }

    public function toggle(Departamento $departamento): RedirectResponse
    {
        $this->asegurarEmpresa($departamento, session('empresa_activa_id'));

        $departamento->update(['estado' => !$departamento->estado]);

        return back()->with('success', 'Departamento ' . ($departamento->estado ? 'activado' : 'desactivado') . '.');
    }

    public function destroy(Departamento $departamento): RedirectResponse
    {
        $this->asegurarEmpresa($departamento, session('empresa_activa_id'));

        if ($departamento->colaboradores()->exists()) {
            return back()->with('error',
                'No se puede eliminar: tiene colaboradores asignados. Reasígnalos o desactiva el departamento.');
        }

        $departamento->delete();

        return back()->with('success', 'Departamento eliminado.');
    }

    private function validar(Request $request, int $empresaId, ?int $ignorarId = null): array
    {
        $data = $request->validate([
            'nombre'      => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:300',
        ]);

        $data['nombre'] = trim(preg_replace('/\s+/', ' ', $data['nombre']));

        $duplicado = Departamento::where('empresa_id', $empresaId)
            ->whereRaw('lower(nombre) = ?', [mb_strtolower($data['nombre'])])
            ->when($ignorarId, fn($q) => $q->where('id', '!=', $ignorarId))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un departamento con ese nombre.']);
        }

        return $data;
    }

    private function asegurarEmpresa(Departamento $departamento, $empresaId): void
    {
        abort_unless((int) $departamento->empresa_id === (int) $empresaId, 404);
    }
}
