<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\CierreCaja;
use App\Models\Factura;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CierreCajaController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');
        $cajas = BancoCaja::where('empresa_id', $empresaId)
            ->cajas()->activos()->orderBy('nombre')->get();

        $cierres = CierreCaja::where('empresa_id', $empresaId)
            ->with(['bancoCaja', 'centroCosto', 'usuarioApertura', 'usuarioCierre'])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->take(30)->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'caja'             => $c->bancoCaja?->nombre,
                'centro_costo'     => $c->centroCosto?->nombre,
                'fecha'            => $c->fecha?->format('d/m/Y'),
                'monto_inicial'    => $c->monto_inicial,
                'total_facturado'  => $c->total_facturado,
                'total_cobrado'    => $c->total_cobrado,
                'total_efectivo'   => $c->total_efectivo,
                'total_tarjeta'    => $c->total_tarjeta,
                'diferencia'       => $c->diferencia,
                'estado'           => $c->estado,
                'hora_apertura'    => $c->hora_apertura?->format('H:i'),
                'hora_cierre'      => $c->hora_cierre?->format('H:i'),
                'usuario_apertura' => $c->usuarioApertura?->nombre,
                'usuario_cierre'   => $c->usuarioCierre?->nombre,
                'tiene_diferencia' => $c->tieneDiferencia(),
            ]);

        $cajaAbierta = CierreCaja::where('empresa_id', $empresaId)
            ->where('estado', 'abierto')
            ->where('fecha', now()->toDateString())
            ->with('bancoCaja')
            ->first();

        $centros = CentroCosto::where('empresa_id', $empresaId)->get(['id', 'nombre']);

        return Inertia::render('Bancos/Cajas/Index', [
            'cierres'     => $cierres,
            'cajas'       => $cajas,
            'centros'     => $centros,
            'cajaAbierta' => $cajaAbierta,
        ]);
    }

    public function abrir(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'banco_caja_id'   => 'required|exists:bancos_cajas,id',
            'monto_inicial'   => 'required|numeric|min:0',
            'centro_costo_id' => 'nullable|exists:centros_costo,id',
        ]);

        $yaAbierta = CierreCaja::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $request->banco_caja_id)
            ->where('fecha', now()->toDateString())
            ->where('estado', 'abierto')
            ->exists();

        if ($yaAbierta) {
            return back()->with('error', 'Ya hay una caja abierta para hoy en esta caja.');
        }

        CierreCaja::create([
            'empresa_id'          => $empresaId,
            'banco_caja_id'       => $request->banco_caja_id,
            'centro_costo_id'     => $request->centro_costo_id,
            'fecha'               => now()->toDateString(),
            'usuario_apertura_id' => Auth::id(),
            'monto_inicial'       => $request->monto_inicial,
            'estado'              => 'abierto',
            'hora_apertura'       => now(),
            'created_at'          => now(),
        ]);

        return back()->with('success', 'Caja abierta correctamente.');
    }

    public function cerrar(Request $request, CierreCaja $cierre): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        if ($cierre->estaCerrada()) {
            return back()->with('error', 'Esta caja ya está cerrada.');
        }

        $request->validate([
            'total_efectivo'      => 'required|numeric|min:0',
            'total_tarjeta'       => 'numeric|min:0',
            'total_cheque'        => 'numeric|min:0',
            'total_transferencia' => 'numeric|min:0',
            'observaciones'       => 'nullable|string|max:500',
        ]);

        // Total real facturado del día en este centro de costo
        $totalFacturado = Factura::where('empresa_id', $empresaId)
            ->whereDate('fecha_emision', $cierre->fecha)
            ->whereNotIn('estado_sri', ['anulada'])
            ->when($cierre->centro_costo_id, fn($q) =>
                $q->where('centro_costo_id', $cierre->centro_costo_id)
            )
            ->sum('total');

        $totalCobrado = $request->total_efectivo
            + ($request->total_tarjeta       ?? 0)
            + ($request->total_cheque        ?? 0)
            + ($request->total_transferencia ?? 0);

        $diferencia = $totalCobrado - (float) $totalFacturado;

        $cierre->update([
            'usuario_cierre_id'   => Auth::id(),
            'total_facturado'     => $totalFacturado,
            'total_cobrado'       => $totalCobrado,
            'total_efectivo'      => $request->total_efectivo,
            'total_tarjeta'       => $request->total_tarjeta ?? 0,
            'total_cheque'        => $request->total_cheque ?? 0,
            'total_transferencia' => $request->total_transferencia ?? 0,
            'diferencia'          => $diferencia,
            'observaciones'       => $request->observaciones,
            'estado'              => 'cerrado',
            'hora_cierre'         => now(),
        ]);

        // Asiento contable del cierre (no bloqueante)
        if ($totalCobrado > 0) {
            try {
                $this->asientoService->cierreCaja(
                    empresaId:    $empresaId,
                    cierreId:     $cierre->id,
                    totalCobrado: $totalCobrado,
                    bancoCajaId:  $cierre->banco_caja_id,
                    fecha:        is_string($cierre->fecha) ? $cierre->fecha : $cierre->fecha->toDateString(),
                );
            } catch (\Throwable) {
                // Período cerrado o parámetro no configurado — no bloquear el cierre
            }
        }

        $msg = 'Caja cerrada. Facturado: $' . number_format((float) $totalFacturado, 2)
            . ' · Cobrado: $' . number_format($totalCobrado, 2);

        if (abs($diferencia) > 0.01) {
            $msg .= ' · Diferencia: $' . number_format(abs($diferencia), 2);
        }

        return back()->with(
            abs($diferencia) > 0.01 ? 'warning' : 'success',
            $msg
        );
    }
}
