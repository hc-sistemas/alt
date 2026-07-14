<?php
namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\CierreCaja;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

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

    // ── Consulta avanzada de cobros y pagos (página Inertia) ──────────────────
    public function consultaCobrosPagos(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->orderBy('tipo')->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $centrosCosto = CentroCosto::where('empresa_id', $empresaId)
            ->where('estado', true)->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        $movimientos = collect();

        if ($request->hasAny(['banco_caja_id', 'tipo', 'sub_tipo', 'fecha_desde', 'fecha_hasta', 'beneficiario', 'num_documento', 'centro_costo_id'])) {
            $query = MovimientoBancario::with(['bancoCaja', 'centroCosto'])
                ->where('empresa_id', $empresaId)
                ->where('anulado', false);

            if ($request->filled('banco_caja_id'))   $query->where('banco_caja_id', $request->banco_caja_id);
            if ($request->filled('tipo'))             $query->where('tipo',          $request->tipo);
            if ($request->filled('sub_tipo'))         $query->where('sub_tipo',      $request->sub_tipo);
            if ($request->filled('fecha_desde'))      $query->where('fecha', '>=',   $request->fecha_desde);
            if ($request->filled('fecha_hasta'))      $query->where('fecha', '<=',   $request->fecha_hasta);
            if ($request->filled('beneficiario'))     $query->where('beneficiario', 'ilike', '%' . $request->beneficiario . '%');
            if ($request->filled('num_documento'))    $query->where('num_documento', 'ilike', '%' . $request->num_documento . '%');
            if ($request->filled('centro_costo_id'))  $query->where('centro_costo_id', $request->centro_costo_id);

            $movimientos = $query->orderByDesc('fecha')->orderByDesc('id')
                ->get()
                ->map(fn($m) => [
                    'id'            => $m->id,
                    'banco'         => $m->bancoCaja?->nombre,
                    'tipo'          => $m->tipo,
                    'sub_tipo'      => $m->sub_tipo,
                    'fecha'         => $m->fecha?->format('d/m/Y'),
                    'monto'         => $m->monto,
                    'beneficiario'  => $m->beneficiario,
                    'descripcion'   => $m->descripcion,
                    'num_documento' => $m->num_documento,
                    'centro_costo'  => $m->centroCosto?->nombre,
                    'conciliado'    => $m->conciliado,
                ]);
        }

        return Inertia::render('Bancos/Reportes/ConsultaCobrosPagos', [
            'bancos'       => $bancos,
            'centrosCosto' => $centrosCosto,
            'movimientos'  => $movimientos->values(),
            'filtros'      => $request->only(['banco_caja_id', 'tipo', 'sub_tipo', 'fecha_desde', 'fecha_hasta', 'beneficiario', 'num_documento', 'centro_costo_id']),
            'totales'      => [
                'ingresos' => $movimientos->where('tipo', 'ingreso')->sum('monto'),
                'egresos'  => $movimientos->where('tipo', 'egreso')->sum('monto'),
                'count'    => $movimientos->count(),
            ],
        ]);
    }

    // ── Exportar consulta a Excel ─────────────────────────────────────────────
    public function consultaExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $empresaId   = session('empresa_activa_id');
        $movimientos = $this->consultaQuery($request, $empresaId);

        $filas = $movimientos->map(fn($m) => [
            $m->bancoCaja?->nombre,
            $m->fecha?->format('d/m/Y'),
            ucfirst($m->tipo),
            ucfirst(str_replace('_', ' ', $m->sub_tipo ?? '')),
            $m->beneficiario,
            $m->descripcion,
            $m->num_documento,
            $m->tipo === 'ingreso' ? number_format($m->monto, 2) : '',
            $m->tipo === 'egreso'  ? number_format($m->monto, 2) : '',
            $m->conciliado ? 'Sí' : 'No',
        ]);

        $export = new class($filas) implements FromCollection, WithHeadings, WithStyles {
            public function __construct(private $filas) {}
            public function collection() { return $this->filas; }
            public function headings(): array {
                return ['Banco/Caja', 'Fecha', 'Tipo', 'Sub-tipo', 'Beneficiario',
                        'Descripción', 'Nº Documento', 'Ingreso', 'Egreso', 'Conciliado'];
            }
            public function styles(Worksheet $sheet): array {
                return [
                    1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                          'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1F2D3D']]],
                ];
            }
        };

        return Excel::download($export, 'consulta-cobros-pagos-' . now()->format('Y-m-d') . '.xlsx');
    }

    // ── Exportar consulta a PDF ────────────────────────────────────────────────
    public function consultaPdf(Request $request): \Illuminate\Http\Response
    {
        $empresaId   = session('empresa_activa_id');
        $movimientos = $this->consultaQuery($request, $empresaId);
        $empresa     = Empresa::find($empresaId);

        $totalIngresos = $movimientos->where('tipo', 'ingreso')->sum('monto');
        $totalEgresos  = $movimientos->where('tipo', 'egreso')->sum('monto');
        $fecha_desde   = $request->fecha_desde;
        $fecha_hasta   = $request->fecha_hasta;

        $pdf = Pdf::loadView('pdf.bancos-movimientos', compact(
            'movimientos', 'totalIngresos', 'totalEgresos', 'empresa',
            'fecha_desde', 'fecha_hasta'
        ))->setPaper('a4', 'landscape');

        return $pdf->stream('consulta-cobros-pagos-' . now()->format('Y-m-d') . '.pdf');
    }

    // ── Consulta compartida ────────────────────────────────────────────────────
    private function consultaQuery(Request $request, int $empresaId)
    {
        $query = MovimientoBancario::with(['bancoCaja', 'centroCosto'])
            ->where('empresa_id', $empresaId)
            ->where('anulado', false);

        if ($request->filled('banco_caja_id'))   $query->where('banco_caja_id', $request->banco_caja_id);
        if ($request->filled('tipo'))             $query->where('tipo',          $request->tipo);
        if ($request->filled('sub_tipo'))         $query->where('sub_tipo',      $request->sub_tipo);
        if ($request->filled('fecha_desde'))      $query->where('fecha', '>=',   $request->fecha_desde);
        if ($request->filled('fecha_hasta'))      $query->where('fecha', '<=',   $request->fecha_hasta);
        if ($request->filled('beneficiario'))     $query->where('beneficiario', 'ilike', '%' . $request->beneficiario . '%');
        if ($request->filled('num_documento'))    $query->where('num_documento', 'ilike', '%' . $request->num_documento . '%');
        if ($request->filled('centro_costo_id'))  $query->where('centro_costo_id', $request->centro_costo_id);

        return $query->orderByDesc('fecha')->orderByDesc('id')->get();
    }
}
