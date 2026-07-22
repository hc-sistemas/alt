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

        $sumasPorCuenta = AsientoDetalle::query()
            ->join('asientos_contables', 'asientos_contables.id', '=', 'asiento_detalles.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.estado', 1)
            ->when($request->filled('ejercicio_id'), fn($q) => $q->where('asientos_contables.ejercicio_id', $request->ejercicio_id))
            ->when($request->filled('fecha_desde'), fn($q) => $q->where('asientos_contables.fecha', '>=', $request->fecha_desde))
            ->when($request->filled('fecha_hasta'), fn($q) => $q->where('asientos_contables.fecha', '<=', $request->fecha_hasta))
            ->groupBy('asiento_detalles.cuenta_id')
            ->selectRaw('asiento_detalles.cuenta_id, SUM(asiento_detalles.debe) as suma_debe, SUM(asiento_detalles.haber) as suma_haber')
            ->get()
            ->keyBy('cuenta_id');

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get()
            ->map(function ($cuenta) use ($sumasPorCuenta) {
                $fila      = $sumasPorCuenta->get($cuenta->id);
                $sumaDebe  = (float) ($fila->suma_debe ?? 0);
                $sumaHaber = (float) ($fila->suma_haber ?? 0);

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

        $sumasPorCuenta = AsientoDetalle::query()
            ->join('asientos_contables', 'asientos_contables.id', '=', 'asiento_detalles.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.estado', 1)
            ->when($request->filled('ejercicio_id'), fn($q) => $q->where('asientos_contables.ejercicio_id', $request->ejercicio_id))
            ->groupBy('asiento_detalles.cuenta_id')
            ->selectRaw('asiento_detalles.cuenta_id, SUM(asiento_detalles.debe) as suma_debe, SUM(asiento_detalles.haber) as suma_haber')
            ->get()
            ->keyBy('cuenta_id');

        $todasCuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->whereIn('tipo', ['activo', 'pasivo', 'patrimonio'])
            ->orderBy('tipo')->orderBy('codigo')
            ->get()
            ->map(function ($cuenta) use ($sumasPorCuenta) {
                $fila  = $sumasPorCuenta->get($cuenta->id);
                $debe  = (float) ($fila->suma_debe ?? 0);
                $haber = (float) ($fila->suma_haber ?? 0);

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

    public function flujoCaja(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $fechaDesde = $request->fecha_desde ?? now()->startOfYear()->toDateString();
        $fechaHasta = $request->fecha_hasta ?? now()->toDateString();
        $fechaAntes = date('Y-m-d', strtotime($fechaDesde . ' -1 day'));

        // Saldo acumulado de una cuenta hasta una fecha dada
        $saldoCuenta = function (int $cuentaId, string $hasta) use ($empresaId): float {
            $row = AsientoDetalle::where('cuenta_id', $cuentaId)
                ->whereHas('asiento', fn($q) =>
                    $q->where('empresa_id', $empresaId)->where('estado', 1)->where('fecha', '<=', $hasta)
                )
                ->selectRaw('COALESCE(SUM(debe),0) as d, COALESCE(SUM(haber),0) as h')
                ->first();
            return (float)($row->d ?? 0) - (float)($row->h ?? 0);
        };

        // Step 1 — Utilidad neta del período
        $cuentasResultado = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->whereIn('tipo', ['ingreso', 'gasto'])
            ->get();

        $totalIngresos = 0.0;
        $totalGastos   = 0.0;

        foreach ($cuentasResultado as $c) {
            $q = AsientoDetalle::where('cuenta_id', $c->id)
                ->whereHas('asiento', fn($qb) =>
                    $qb->where('empresa_id', $empresaId)->where('estado', 1)
                       ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                );
            if ($request->filled('centro_costo_id')) {
                $q->where('centro_costo_id', $request->centro_costo_id);
            }
            $sums = $q->selectRaw('COALESCE(SUM(debe),0) as d, COALESCE(SUM(haber),0) as h')->first();
            $d = (float)($sums->d ?? 0);
            $h = (float)($sums->h ?? 0);
            if ($c->tipo === 'ingreso') $totalIngresos += $h - $d;
            else                        $totalGastos   += $d - $h;
        }
        $utilidad = round($totalIngresos - $totalGastos, 2);

        // Step 2 — Variaciones cuentas de balance
        $cuentasBalance = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->whereIn('tipo', ['activo', 'pasivo'])
            ->orderBy('codigo')
            ->get();

        $operativoActivo  = collect();
        $operativoPasivo  = collect();
        $inversion        = collect();
        $financiamiento   = collect();
        $efectivoInicio   = 0.0;
        $efectivoFin      = 0.0;

        foreach ($cuentasBalance as $c) {
            $si = $saldoCuenta($c->id, $fechaAntes);
            $sf = $saldoCuenta($c->id, $fechaHasta);

            if (round($si, 2) === 0.0 && round($sf, 2) === 0.0) continue;

            $cod = $c->codigo;

            if (str_starts_with($cod, '1.1.1')) {
                $efectivoInicio += $si;
                $efectivoFin    += $sf;
                continue;
            }

            $categoria = null;
            if (str_starts_with($cod, '1.1.'))    $categoria = 'operativo_activo';
            elseif (str_starts_with($cod, '1.2') || str_starts_with($cod, '1.3')) $categoria = 'inversion';
            elseif (str_starts_with($cod, '2.1')) $categoria = 'operativo_pasivo';
            elseif (str_starts_with($cod, '2.2')) $categoria = 'financiamiento';

            if (!$categoria) continue;

            if ($c->tipo === 'activo') {
                $saldo_i    = $si;
                $saldo_f    = $sf;
                $variacion  = $sf - $si;
                $efectoCaja = -$variacion;
            } else {
                $saldo_i    = -$si;
                $saldo_f    = -$sf;
                $variacion  = $saldo_f - $saldo_i;
                $efectoCaja = $variacion;
            }

            $item = [
                'codigo'      => $cod,
                'nombre'      => $c->nombre,
                'saldo_inicio' => round($saldo_i, 2),
                'saldo_fin'    => round($saldo_f, 2),
                'variacion'    => round($variacion, 2),
                'efecto_caja'  => round($efectoCaja, 2),
            ];

            match ($categoria) {
                'operativo_activo' => $operativoActivo->push($item),
                'operativo_pasivo' => $operativoPasivo->push($item),
                'inversion'        => $inversion->push($item),
                'financiamiento'   => $financiamiento->push($item),
            };
        }

        $ajustesCapTrabajo   = round($operativoActivo->sum('efecto_caja') + $operativoPasivo->sum('efecto_caja'), 2);
        $flujoOperativo      = round($utilidad + $ajustesCapTrabajo, 2);
        $flujoInversion      = round($inversion->sum('efecto_caja'), 2);
        $flujoFinanciamiento = round($financiamiento->sum('efecto_caja'), 2);
        $flujoNeto           = round($flujoOperativo + $flujoInversion + $flujoFinanciamiento, 2);

        $resumen = [
            'utilidad'             => $utilidad,
            'total_ingresos'       => round($totalIngresos, 2),
            'total_gastos'         => round($totalGastos, 2),
            'ajustes_cap_trabajo'  => $ajustesCapTrabajo,
            'flujo_operativo'      => $flujoOperativo,
            'flujo_inversion'      => $flujoInversion,
            'flujo_financiamiento' => $flujoFinanciamiento,
            'flujo_neto'           => $flujoNeto,
            'efectivo_inicio'      => round($efectivoInicio, 2),
            'efectivo_fin'         => round($efectivoFin, 2),
            'diferencia_efectivo'  => round($efectivoFin - $efectivoInicio, 2),
        ];

        $pdf = Pdf::loadView('pdf.flujo-caja', [
            'empresa'         => $empresa,
            'fecha_desde'     => $fechaDesde,
            'fecha_hasta'     => $fechaHasta,
            'resumen'         => $resumen,
            'operativoActivo' => $operativoActivo,
            'operativoPasivo' => $operativoPasivo,
            'inversion'       => $inversion,
            'financiamiento'  => $financiamiento,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream('flujo-caja-' . now()->format('Y-m-d') . '.pdf');
    }

    public function flujoCajaExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $fechaDesde = $request->fecha_desde ?? now()->startOfYear()->toDateString();
        $fechaHasta = $request->fecha_hasta ?? now()->toDateString();
        $fechaAntes = date('Y-m-d', strtotime($fechaDesde . ' -1 day'));

        $saldoCuenta = function (int $cuentaId, string $hasta) use ($empresaId): float {
            $row = AsientoDetalle::where('cuenta_id', $cuentaId)
                ->whereHas('asiento', fn($q) =>
                    $q->where('empresa_id', $empresaId)->where('estado', 1)->where('fecha', '<=', $hasta)
                )
                ->selectRaw('COALESCE(SUM(debe),0) as d, COALESCE(SUM(haber),0) as h')
                ->first();
            return (float)($row->d ?? 0) - (float)($row->h ?? 0);
        };

        $cuentasResultado = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)->whereIn('tipo', ['ingreso', 'gasto'])->get();
        $totalIngresos = 0.0; $totalGastos = 0.0;
        foreach ($cuentasResultado as $c) {
            $sums = AsientoDetalle::where('cuenta_id', $c->id)
                ->whereHas('asiento', fn($qb) =>
                    $qb->where('empresa_id', $empresaId)->where('estado', 1)
                       ->whereBetween('fecha', [$fechaDesde, $fechaHasta])
                )
                ->selectRaw('COALESCE(SUM(debe),0) as d, COALESCE(SUM(haber),0) as h')->first();
            if ($c->tipo === 'ingreso') $totalIngresos += (float)$sums->h - (float)$sums->d;
            else                        $totalGastos   += (float)$sums->d - (float)$sums->h;
        }
        $utilidad = round($totalIngresos - $totalGastos, 2);

        $cuentasBalance = PlanCuenta::where('permite_asientos', true)->where('estado', true)
            ->whereIn('tipo', ['activo', 'pasivo'])->orderBy('codigo')->get();

        $filas = [];
        $efectivoInicio = 0.0; $efectivoFin = 0.0;
        foreach ($cuentasBalance as $c) {
            $si = $saldoCuenta($c->id, $fechaAntes);
            $sf = $saldoCuenta($c->id, $fechaHasta);
            if (round($si, 2) === 0.0 && round($sf, 2) === 0.0) continue;
            $cod = $c->codigo;
            if (str_starts_with($cod, '1.1.1')) { $efectivoInicio += $si; $efectivoFin += $sf; continue; }
            $seccion = null;
            if (str_starts_with($cod, '1.1.')) $seccion = 'Operativo — Activo';
            elseif (str_starts_with($cod, '1.2') || str_starts_with($cod, '1.3')) $seccion = 'Inversión';
            elseif (str_starts_with($cod, '2.1')) $seccion = 'Operativo — Pasivo';
            elseif (str_starts_with($cod, '2.2')) $seccion = 'Financiamiento';
            if (!$seccion) continue;
            if ($c->tipo === 'activo') { $saldo_i = $si; $saldo_f = $sf; $efecto = -($sf - $si); }
            else                       { $saldo_i = -$si; $saldo_f = -$sf; $efecto = $saldo_f - $saldo_i; }
            $filas[] = [$seccion, $cod, $c->nombre, round($saldo_i,2), round($saldo_f,2), round($efecto,2)];
        }

        $nombre = ($empresa?->nombre ?? 'Altamira') . ' — Flujo de Caja ' . $fechaDesde . ' al ' . $fechaHasta;

        return response()->streamDownload(function () use ($nombre, $utilidad, $filas, $efectivoInicio, $efectivoFin) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ESTADO DE FLUJO DE EFECTIVO — MÉTODO INDIRECTO']);
            fputcsv($out, []);
            fputcsv($out, ['Utilidad neta del período', $utilidad]);
            fputcsv($out, []);
            fputcsv($out, ['Sección', 'Código', 'Cuenta', 'Saldo Inicio', 'Saldo Fin', 'Efecto en Caja']);
            foreach ($filas as $fila) fputcsv($out, $fila);
            fputcsv($out, []);
            fputcsv($out, ['Saldo inicial efectivo/bancos', round($efectivoInicio, 2)]);
            fputcsv($out, ['Saldo final efectivo/bancos',  round($efectivoFin, 2)]);
            fclose($out);
        }, 'flujo-caja-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function estadoResultados(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = \App\Models\Empresa::find($empresaId);

        $sumasPorCuenta = AsientoDetalle::query()
            ->join('asientos_contables', 'asientos_contables.id', '=', 'asiento_detalles.asiento_id')
            ->where('asientos_contables.empresa_id', $empresaId)
            ->where('asientos_contables.estado', 1)
            ->when($request->filled('ejercicio_id'), fn($q) => $q->where('asientos_contables.ejercicio_id', $request->ejercicio_id))
            ->when($request->filled('fecha_desde'), fn($q) => $q->where('asientos_contables.fecha', '>=', $request->fecha_desde))
            ->when($request->filled('fecha_hasta'), fn($q) => $q->where('asientos_contables.fecha', '<=', $request->fecha_hasta))
            ->groupBy('asiento_detalles.cuenta_id')
            ->selectRaw('asiento_detalles.cuenta_id, SUM(asiento_detalles.debe) as suma_debe, SUM(asiento_detalles.haber) as suma_haber')
            ->get()
            ->keyBy('cuenta_id');

        $todasCuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->whereIn('tipo', ['ingreso', 'gasto'])
            ->orderBy('tipo')->orderBy('codigo')
            ->get()
            ->map(function ($cuenta) use ($sumasPorCuenta) {
                $fila  = $sumasPorCuenta->get($cuenta->id);
                $debe  = (float) ($fila->suma_debe ?? 0);
                $haber = (float) ($fila->suma_haber ?? 0);

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
