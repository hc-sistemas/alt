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

    public function balanceComprobacion(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get()
            ->map(function ($cuenta) use ($empresaId, $request) {
                $query = AsientoDetalle::where('cuenta_id', $cuenta->id)
                    ->whereHas('asiento', function ($q) use ($empresaId, $request) {
                        $q->where('empresa_id', $empresaId)->where('estado', 1);
                        if ($request->filled('ejercicio_id')) {
                            $q->where('ejercicio_id', $request->ejercicio_id);
                        }
                        if ($request->filled('fecha_desde')) {
                            $q->where('fecha', '>=', $request->fecha_desde);
                        }
                        if ($request->filled('fecha_hasta')) {
                            $q->where('fecha', '<=', $request->fecha_hasta);
                        }
                    });

                $sumaDebe  = (float) $query->sum('debe');
                $sumaHaber = (float) $query->sum('haber');

                if ($sumaDebe == 0 && $sumaHaber == 0) return null;

                $saldo = $sumaDebe - $sumaHaber;
                return [
                    'codigo'         => $cuenta->codigo,
                    'nombre'         => $cuenta->nombre,
                    'tipo'           => $cuenta->tipo,
                    'suma_debe'      => $sumaDebe,
                    'suma_haber'     => $sumaHaber,
                    'saldo_deudor'   => $saldo > 0 ? $saldo : 0,
                    'saldo_acreedor' => $saldo < 0 ? abs($saldo) : 0,
                ];
            })
            ->filter()
            ->values();

        $totales = [
            'debe'     => $cuentas->sum('suma_debe'),
            'haber'    => $cuentas->sum('suma_haber'),
            'deudor'   => $cuentas->sum('saldo_deudor'),
            'acreedor' => $cuentas->sum('saldo_acreedor'),
        ];

        $pdf = Pdf::loadView(
            'pdf.balance-comprobacion',
            compact('cuentas', 'empresa', 'totales')
        )->setPaper('a4', 'landscape');

        return $pdf->stream('balance-comprobacion-' . now()->format('Y-m-d') . '.pdf');
    }

    public function balanceGeneral(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $todasCuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->whereIn('tipo', ['activo', 'pasivo', 'patrimonio'])
            ->orderBy('tipo')->orderBy('codigo')
            ->get()
            ->map(function ($cuenta) use ($empresaId, $request) {
                $query = AsientoDetalle::where('cuenta_id', $cuenta->id)
                    ->whereHas('asiento', function ($q) use ($empresaId, $request) {
                        $q->where('empresa_id', $empresaId)->where('estado', 1);
                        if ($request->filled('ejercicio_id')) {
                            $q->where('ejercicio_id', $request->ejercicio_id);
                        }
                    });

                $debe  = (float) $query->sum('debe');
                $haber = (float) $query->sum('haber');

                if ($debe == 0 && $haber == 0) return null;

                return [
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'tipo'   => $cuenta->tipo,
                    'saldo'  => round($debe - $haber, 2),
                ];
            })
            ->filter()->values();

        $activos = $todasCuentas->where('tipo', 'activo')->values();
        $pasivos = $todasCuentas->whereIn('tipo', ['pasivo', 'patrimonio'])->values();
        $totales = [
            'activos' => $activos->sum('saldo'),
            'pasivos' => $pasivos->sum(fn($c) => abs($c['saldo'])),
        ];

        $pdf = Pdf::loadView(
            'pdf.balance-general',
            compact('activos', 'pasivos', 'empresa', 'totales')
        )->setPaper('a4', 'portrait');

        return $pdf->stream('balance-general-' . now()->format('Y-m-d') . '.pdf');
    }

    public function estadoResultados(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $todasCuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->whereIn('tipo', ['ingreso', 'gasto'])
            ->orderBy('tipo')->orderBy('codigo')
            ->get()
            ->map(function ($cuenta) use ($empresaId, $request) {
                $query = AsientoDetalle::where('cuenta_id', $cuenta->id)
                    ->whereHas('asiento', function ($q) use ($empresaId, $request) {
                        $q->where('empresa_id', $empresaId)->where('estado', 1);
                        if ($request->filled('ejercicio_id')) {
                            $q->where('ejercicio_id', $request->ejercicio_id);
                        }
                        if ($request->filled('fecha_desde')) {
                            $q->where('fecha', '>=', $request->fecha_desde);
                        }
                        if ($request->filled('fecha_hasta')) {
                            $q->where('fecha', '<=', $request->fecha_hasta);
                        }
                    });

                $debe  = (float) $query->sum('debe');
                $haber = (float) $query->sum('haber');

                if ($debe == 0 && $haber == 0) return null;

                $saldo = $cuenta->tipo === 'ingreso' ? ($haber - $debe) : ($debe - $haber);

                return [
                    'codigo' => $cuenta->codigo,
                    'nombre' => $cuenta->nombre,
                    'tipo'   => $cuenta->tipo,
                    'saldo'  => round($saldo, 2),
                ];
            })
            ->filter()->values();

        $ingresos = $todasCuentas->where('tipo', 'ingreso')->values();
        $gastos   = $todasCuentas->where('tipo', 'gasto')->values();
        $utilidad = round($ingresos->sum('saldo') - $gastos->sum('saldo'), 2);
        $totales  = [
            'ingresos' => $ingresos->sum('saldo'),
            'gastos'   => $gastos->sum('saldo'),
            'utilidad' => $utilidad,
        ];

        $pdf = Pdf::loadView(
            'pdf.estado-resultados',
            compact('ingresos', 'gastos', 'empresa', 'totales', 'utilidad')
        )->setPaper('a4', 'portrait');

        return $pdf->stream('estado-resultados-' . now()->format('Y-m-d') . '.pdf');
    }
}
