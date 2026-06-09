<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\CuentaPagar;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        return Inertia::render('Compras/CuentasPagar/Index', [
            'cxp'         => $cxp,
            'proveedores' => $proveedores,
            'filtros'     => $request->only(['estado', 'proveedor_id']),
            'resumen' => [
                'total_pendiente' => (float) CuentaPagar::where('empresa_id', $empresaId)
                    ->whereIn('estado', ['pendiente', 'parcial'])->sum('saldo'),
                'vencidas'   => CuentaPagar::where('empresa_id', $empresaId)->vencidas()->count(),
                'por_vencer' => CuentaPagar::where('empresa_id', $empresaId)->porVencer(15)->count(),
            ],
        ]);
    }
}
