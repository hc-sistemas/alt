<?php

namespace App\Http\Controllers\Contabilidad;

use App\Exports\PlanCuentasExport;
use App\Http\Controllers\Controller;
use App\Models\PlanCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PlanCuentaController extends Controller
{
    public function index(): Response
    {
        $cuentas = PlanCuenta::with('hijos.hijos.hijos.hijos')
            ->whereNull('padre_id')
            ->orderBy('codigo')
            ->get();

        return Inertia::render('Contabilidad/PlanCuentas/Index', [
            'cuentas' => $cuentas,
            'stats'   => [
                'total'        => PlanCuenta::count(),
                'activas'      => PlanCuenta::where('estado', true)->count(),
                'con_asientos' => PlanCuenta::where('total_asientos', '>', 0)->count(),
                'sin_uso'      => PlanCuenta::where('permite_asientos', true)
                                            ->where('total_asientos', 0)
                                            ->count(),
            ],
            'todasLasCuentas' => PlanCuenta::orderBy('codigo')
                                           ->get(['id', 'codigo', 'nombre', 'tipo', 'nivel', 'padre_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'codigo'           => 'required|string|max:30|unique:plan_cuentas,codigo',
            'nombre'           => 'required|string|max:200',
            'descripcion'      => 'nullable|string|max:500',
            'tipo'             => 'required|in:activo,pasivo,patrimonio,ingreso,gasto',
            'padre_id'         => 'nullable|exists:plan_cuentas,id',
            'permite_asientos' => 'boolean',
        ], [
            'codigo.unique'   => 'Ya existe una cuenta con ese código.',
            'codigo.required' => 'El código es obligatorio.',
            'nombre.required' => 'El nombre es obligatorio.',
        ]);

        $nivel = 1;
        if ($request->padre_id) {
            $padre = PlanCuenta::findOrFail($request->padre_id);
            $nivel = $padre->nivel + 1;
        }

        $cuenta = PlanCuenta::create([
            'codigo'           => $request->codigo,
            'nombre'           => $request->nombre,
            'descripcion'      => $request->descripcion,
            'tipo'             => $request->tipo,
            'padre_id'         => $request->padre_id,
            'nivel'            => $nivel,
            'permite_asientos' => $request->boolean('permite_asientos'),
            'estado'           => true,
            'total_asientos'   => 0,
        ]);

        return back()->with('success',
            "Cuenta {$cuenta->codigo} — {$cuenta->nombre} creada correctamente.");
    }

    public function update(Request $request, PlanCuenta $cuenta): RedirectResponse
    {
        $request->validate([
            'nombre'           => 'required|string|max:200',
            'descripcion'      => 'nullable|string|max:500',
            'permite_asientos' => 'boolean',
        ]);

        $cuenta->update($request->only(['nombre', 'descripcion', 'permite_asientos']));

        return back()->with('success',
            "Cuenta {$cuenta->codigo} — {$cuenta->nombre} actualizada.");
    }

    public function toggleEstado(PlanCuenta $cuenta): RedirectResponse
    {
        if ($cuenta->estado && $cuenta->total_asientos > 0) {
            return back()->with('error',
                "No se puede desactivar: la cuenta {$cuenta->codigo} tiene {$cuenta->total_asientos} asiento(s) registrado(s).");
        }

        $cuenta->update(['estado' => !$cuenta->estado]);
        $estado = $cuenta->estado ? 'activada' : 'desactivada';

        return back()->with('success', "Cuenta {$cuenta->codigo} {$estado}.");
    }

    public function destroy(PlanCuenta $cuenta): RedirectResponse
    {
        $motivo = $cuenta->motivoNoPuedeEliminarse();
        if ($motivo) {
            return back()->with('error', $motivo);
        }

        $info = "{$cuenta->codigo} — {$cuenta->nombre}";
        $cuenta->delete();

        return back()->with('success', "Cuenta {$info} eliminada permanentemente.");
    }

    public function exportar(): BinaryFileResponse
    {
        $fecha = now()->format('Y-m-d');
        return Excel::download(
            new PlanCuentasExport(),
            "plan-cuentas-altamira-{$fecha}.xlsx",
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
