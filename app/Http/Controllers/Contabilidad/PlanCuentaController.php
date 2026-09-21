<?php

namespace App\Http\Controllers\Contabilidad;

use App\Exports\PlanCuentasExport;
use App\Http\Controllers\Controller;
use App\Imports\PlanCuentasImport;
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

            // Coherencia de la jerarquía. No se validaba nada: se podía crear
            // una cuenta de tipo "ingreso" colgando de un padre "activo", y
            // como TODOS los reportes clasifican por cuenta.tipo, esa cuenta
            // aparecía en la sección equivocada del balance descuadrándolo.
            if ($padre->tipo !== $request->tipo) {
                return back()->with('error',
                    "La cuenta debe ser del mismo tipo que su padre ({$padre->codigo} es de tipo " .
                    ucfirst($padre->tipo) . ", y se intentó crear una de tipo " .
                    ucfirst($request->tipo) . ').');
            }

            // El código de la hija tiene que colgar del código del padre; si no,
            // el árbol del plan y los prefijos que usan los reportes (5.1 costo
            // de ventas, 1.1.1 efectivo, etc.) dejan de significar nada.
            if (!str_starts_with($request->codigo, $padre->codigo . '.')) {
                return back()->with('error',
                    "El código debe comenzar con el del padre: {$padre->codigo}. " .
                    "Ejemplo válido: {$padre->codigo}.01");
            }

            // Una cuenta que ya recibe movimientos no puede volverse de
            // agrupación: su saldo quedaría mezclado con el de sus hijas.
            if ($padre->permite_asientos && $padre->total_asientos > 0) {
                return back()->with('error',
                    "La cuenta {$padre->codigo} ya tiene {$padre->total_asientos} asiento(s) registrados, " .
                    'así que no puede convertirse en cuenta de agrupación. Use otra cuenta padre.');
            }

            // El padre pasa a ser cuenta de agrupación.
            if ($padre->permite_asientos) {
                $padre->update(['permite_asientos' => false]);
            }
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

        // Apagar `permite_asientos` en una cuenta con movimientos la hace
        // DESAPARECER de todos los estados financieros: el balance de
        // comprobación, el balance general, el estado de resultados y el cierre
        // anual filtran `permite_asientos = true`. El saldo se queda en el libro
        // pero deja de sumar en los reportes, y el balance descuadra sin que
        // nada lo explique. toggleEstado() ya protegía este caso; update() no.
        if ($request->has('permite_asientos')
            && !$request->boolean('permite_asientos')
            && $cuenta->permite_asientos
            && $cuenta->total_asientos > 0
        ) {
            return back()->with('error',
                "No se puede quitar 'permite asientos' a {$cuenta->codigo}: tiene " .
                "{$cuenta->total_asientos} asiento(s) registrados y su saldo desaparecería " .
                'de los estados financieros.');
        }

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

    public function importarExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'archivo.required' => 'Selecciona un archivo Excel.',
            'archivo.mimes'    => 'El archivo debe ser .xlsx o .xls.',
            'archivo.max'      => 'El archivo no debe superar 5 MB.',
        ]);

        $import = new PlanCuentasImport();
        Excel::import($import, $request->file('archivo'));

        $msg = "Importación completada: {$import->creadas} cuenta(s) creada(s), {$import->omitidas} omitida(s).";

        if (!empty($import->errores)) {
            $msg .= ' Errores: ' . implode(' | ', array_slice($import->errores, 0, 5));
        }

        return back()->with('success', $msg);
    }
}
