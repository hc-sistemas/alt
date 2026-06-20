<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\AsientoDetalle;
use App\Models\EjercicioContable;
use App\Models\PlanCuenta;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReporteContableController extends Controller
{
    public function index(): Response
    {
        $empresaId  = session('empresa_activa_id');
        $ejercicios = EjercicioContable::where('empresa_id', $empresaId)
            ->orderByDesc('anio')->orderByDesc('mes')
            ->get(['id','anio','mes','descripcion','estado']);

        $cuentas = PlanCuenta::where('empresa_id', $empresaId)
            ->where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id','codigo','descripcion']);

        return Inertia::render('Contabilidad/Reportes/Index', [
            'ejercicios' => $ejercicios,
            'cuentas'    => $cuentas,
        ]);
    }

    public function libroDiario(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');

        $query = AsientoContable::with([
                'ejercicio','creadoPor','detalles.cuenta'
            ])
            ->where('empresa_id', $empresaId)
            ->where('estado', 1);

        if ($request->filled('ejercicio_id')) {
            $query->where('ejercicio_id', $request->ejercicio_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $asientos   = $query->orderBy('fecha')->orderBy('id')->get();
        $empresa    = \App\Models\Empresa::find($empresaId);
        $totalDebe  = $asientos->sum('total_debe');
        $totalHaber = $asientos->sum('total_haber');

        $pdf = Pdf::loadView(
            'pdf.libro-diario',
            compact('asientos','empresa','totalDebe','totalHaber')
        )->setPaper('a4', 'landscape');

        return $pdf->stream(
            'libro-diario-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    public function mayor(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'cuenta_id' => 'required|exists:plan_cuentas,id',
        ]);

        $cuenta  = PlanCuenta::findOrFail($request->cuenta_id);
        $empresa = \App\Models\Empresa::find($empresaId);

        $query = AsientoDetalle::with(['asiento'])
            ->where('cuenta_id', $cuenta->id)
            ->whereHas('asiento', fn($q) =>
                $q->where('empresa_id', $empresaId)
                  ->where('estado', 1)
            );

        if ($request->filled('fecha_desde')) {
            $query->whereHas('asiento', fn($q) =>
                $q->where('fecha', '>=', $request->fecha_desde)
            );
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereHas('asiento', fn($q) =>
                $q->where('fecha', '<=', $request->fecha_hasta)
            );
        }

        $detalles   = $query->orderBy('id')->get();
        $totalDebe  = $detalles->sum('debe');
        $totalHaber = $detalles->sum('haber');
        $saldo      = $totalDebe - $totalHaber;

        $pdf = Pdf::loadView(
            'pdf.mayor-cuenta',
            compact('cuenta','detalles','totalDebe','totalHaber','saldo','empresa')
        )->setPaper('a4', 'portrait');

        return $pdf->stream(
            'mayor-' . $cuenta->codigo . '-' . now()->format('Y-m-d') . '.pdf'
        );
    }
}
