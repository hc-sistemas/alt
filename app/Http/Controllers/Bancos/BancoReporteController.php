<?php
namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CierreCaja;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BancoReporteController extends Controller
{
    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->orderBy('tipo')->orderBy('nombre')
            ->get(['id','nombre','tipo','saldo_actual','num_cuenta']);

        $cajas = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->cajas()->orderBy('nombre')
            ->get(['id','nombre','tipo','saldo_actual']);

        return Inertia::render('Bancos/Reportes/Index', [
            'bancos' => $bancos,
            'cajas'  => $cajas,
        ]);
    }

    public function estadoCuenta(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'banco_caja_id' => 'required|exists:bancos_cajas,id',
            'fecha_desde'   => 'required|date',
            'fecha_hasta'   => 'required|date|after_or_equal:fecha_desde',
        ]);

        $banco = BancoCaja::findOrFail($request->banco_caja_id);

        $saldoInicial = (float) MovimientoBancario::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $banco->id)
            ->where('fecha', '<', $request->fecha_desde)
            ->where('anulado', false)
            ->selectRaw("
                SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) -
                SUM(CASE WHEN tipo = 'egreso'  THEN monto ELSE 0 END) as saldo
            ")
            ->value('saldo') ?? 0;
        $saldoInicial += (float) $banco->saldo_inicial;

        $movimientos = MovimientoBancario::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $banco->id)
            ->whereBetween('fecha', [$request->fecha_desde, $request->fecha_hasta])
            ->where('anulado', false)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $saldoAcum = $saldoInicial;
        $movimientos = $movimientos->map(function ($m) use (&$saldoAcum) {
            if ($m->tipo === 'ingreso') {
                $saldoAcum += (float) $m->monto;
            } else {
                $saldoAcum -= (float) $m->monto;
            }
            $m->saldo_acumulado = $saldoAcum;
            return $m;
        });

        $totalIngresos = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalEgresos  = $movimientos->where('tipo', 'egreso')->sum('monto');
        $saldoFinal    = $saldoInicial + $totalIngresos - $totalEgresos;
        $empresa       = Empresa::find($empresaId);
        $fecha_desde   = $request->fecha_desde;
        $fecha_hasta   = $request->fecha_hasta;

        $pdf = Pdf::loadView('pdf.banco-estado-cuenta', compact(
            'banco', 'movimientos', 'saldoInicial', 'saldoFinal',
            'totalIngresos', 'totalEgresos', 'empresa',
            'fecha_desde', 'fecha_hasta'
        ))->setPaper('a4', 'portrait');

        return $pdf->stream(
            'estado-cuenta-' . str_replace(' ', '-', $banco->nombre) .
            '-' . now()->format('Y-m-d') . '.pdf'
        );
    }

    public function reporteMovimientos(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');

        $query = MovimientoBancario::with('bancoCaja')
            ->where('empresa_id', $empresaId)
            ->where('anulado', false);

        if ($request->filled('banco_caja_id')) {
            $query->where('banco_caja_id', $request->banco_caja_id);
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->tipo);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $movimientos   = $query->orderByDesc('fecha')->get();
        $totalIngresos = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalEgresos  = $movimientos->where('tipo', 'egreso')->sum('monto');
        $empresa       = Empresa::find($empresaId);

        $pdf = Pdf::loadView('pdf.bancos-movimientos', compact(
            'movimientos', 'totalIngresos', 'totalEgresos', 'empresa'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('movimientos-' . now()->format('Y-m-d') . '.pdf');
    }

    public function reporteCajaChica(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');

        $query = CierreCaja::with(['bancoCaja', 'usuarioApertura', 'usuarioCierre'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('banco_caja_id')) {
            $query->where('banco_caja_id', $request->banco_caja_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $cierres    = $query->orderByDesc('fecha')->get();
        $empresa    = Empresa::find($empresaId);
        $cajaNombre = $request->filled('banco_caja_id')
            ? BancoCaja::find($request->banco_caja_id)?->nombre
            : 'Todas las cajas';

        $pdf = Pdf::loadView('pdf.caja-chica-reporte', compact(
            'cierres', 'empresa', 'cajaNombre'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('caja-chica-' . now()->format('Y-m-d') . '.pdf');
    }
}
