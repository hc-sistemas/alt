<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Exports\CxPExport;
use App\Models\BancoCaja;
use App\Models\CuentaPagar;
use App\Models\Empresa;
use App\Models\MovimientoBancario;
use App\Models\Proveedor;
use App\Services\AsientoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class CuentaPagarController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = CuentaPagar::with(['proveedor', 'compra'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } else {
            $query->whereIn('estado', ['pendiente', 'parcial']);
        }
        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }

        $cxp = $query->orderBy('fecha_vencimiento')->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'proveedor'        => $c->proveedor?->razon_social,
                'num_documento'    => $c->compra?->num_documento,
                'monto'            => $c->monto,
                'saldo'            => $c->saldo,
                'fecha_emision'    => $c->fecha_emision?->format('d/m/Y'),
                'fecha_vencimiento'=> $c->fecha_vencimiento?->format('d/m/Y'),
                'estado'           => $c->estado,
                'urgencia'         => $c->urgencia,
                'color_urgencia'   => $c->color_urgencia,
                'dias_vencimiento' => $c->dias_vencimiento,
            ]);

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social']);

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'saldo_actual']);

        return Inertia::render('Compras/CuentasPagar/Index', [
            'cxp'         => $cxp,
            'proveedores' => $proveedores,
            'bancos'      => $bancos,
            'filtros'     => $request->only(['estado', 'proveedor_id']),
            'resumen' => [
                'total_pendiente' => (float) CuentaPagar::where('empresa_id', $empresaId)
                    ->whereIn('estado', ['pendiente', 'parcial'])->sum('saldo'),
                'vencidas'   => CuentaPagar::where('empresa_id', $empresaId)->vencidas()->count(),
                'por_vencer' => CuentaPagar::where('empresa_id', $empresaId)->porVencer(15)->count(),
            ],
        ]);
    }

    public function pagar(Request $request, CuentaPagar $cuentaPagar): RedirectResponse
    {
        $request->validate([
            'monto_pago'    => "required|numeric|min:0.01|max:{$cuentaPagar->saldo}",
            'banco_caja_id' => 'required|exists:bancos_cajas,id',
            'fecha_pago'    => 'required|date',
            'referencia'    => 'nullable|string|max:100',
        ]);

        if ($cuentaPagar->estado === 'pagada') {
            return back()->with('error', 'Esta cuenta ya está pagada.');
        }

        DB::transaction(function () use ($request, $cuentaPagar) {
            $monto  = (float) $request->monto_pago;
            $banco  = BancoCaja::findOrFail($request->banco_caja_id);

            $nuevoSaldo  = max(0, (float) $cuentaPagar->saldo - $monto);
            $nuevoEstado = $nuevoSaldo <= 0 ? 'pagada' : 'parcial';

            $cuentaPagar->update(['saldo' => $nuevoSaldo, 'estado' => $nuevoEstado]);

            if ($nuevoEstado === 'pagada' && $cuentaPagar->compra) {
                $cuentaPagar->compra->update(['tiene_pago' => true]);
            }

            $movimiento = MovimientoBancario::create([
                'empresa_id'     => $cuentaPagar->empresa_id,
                'banco_caja_id'  => $banco->id,
                'tipo'           => 'egreso',
                'sub_tipo'       => 'pago_proveedor',
                'fecha'          => $request->fecha_pago,
                'monto'          => $monto,
                'persona_tipo'   => 'proveedor',
                'persona_id'     => $cuentaPagar->proveedor_id,
                'beneficiario'   => $cuentaPagar->proveedor?->razon_social,
                'num_documento'  => $cuentaPagar->compra?->num_documento,
                'descripcion'    => $request->referencia ?? 'Pago CxP',
                'documento_tipo' => 'COMPRA',
                'documento_id'   => $cuentaPagar->compra_id,
                'created_by'     => Auth::id(),
            ]);

            $banco->actualizarSaldo($monto, 'egreso');

            app(AsientoService::class)->pagoProveedor(
                $cuentaPagar->empresa_id,
                $cuentaPagar->id,
                $request->referencia ?? "Pago #{$movimiento->id}",
                $monto,
            );
        });

        return back()->with('success', 'Pago registrado correctamente.');
    }

    public function pdf(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $query     = CuentaPagar::with(['proveedor', 'compra'])->where('empresa_id', $empresaId);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        } else {
            $query->whereIn('estado', ['pendiente', 'parcial']);
        }
        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }
        $cxp     = $query->orderBy('fecha_vencimiento')->get();
        $empresa = Empresa::find($empresaId);
        $pdf = Pdf::loadView('pdf.cxp', compact('cxp', 'empresa'))->setPaper('a4', 'landscape');
        return $pdf->stream('cuentas-pagar-' . now()->format('Y-m-d') . '.pdf');
    }

    public function excel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $empresaId = session('empresa_activa_id');
        return Excel::download(
            new CxPExport((int) $empresaId, $request->only(['estado', 'proveedor_id'])),
            'cuentas-pagar-' . now()->format('Y-m-d') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}
