<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\ConciliacionBancaria;
use App\Models\MovimientoBancario;
use App\Models\PartidaTransito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ConciliacionController extends Controller
{
    public function index(): Response
    {
        $empresaId      = session('empresa_activa_id');
        $conciliaciones = ConciliacionBancaria::where('empresa_id', $empresaId)
            ->with('bancoCaja')
            ->orderByDesc('fecha_corte')
            ->get()
            ->map(fn($c) => [
                'id'            => $c->id,
                'banco'         => $c->bancoCaja?->nombre,
                'fecha_corte'   => $c->fecha_corte?->format('d/m/Y'),
                'saldo_banco'   => $c->saldo_banco,
                'saldo_sistema' => $c->saldo_sistema,
                'diferencia'    => $c->diferencia,
                'estado'        => $c->estado,
                'tiene_dif'     => $c->tieneDiferencia(),
                'created_at'    => $c->created_at?->format('d/m/Y'),
            ]);

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->bancos()->activos()->orderBy('nombre')
            ->get(['id', 'nombre', 'saldo_actual']);

        return Inertia::render('Bancos/Conciliaciones/Index', [
            'conciliaciones' => $conciliaciones,
            'bancos'         => $bancos,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'banco_caja_id' => 'required|exists:bancos_cajas,id',
            'fecha_corte'   => 'required|date',
            'saldo_banco'   => 'required|numeric',
            'descripcion'   => 'nullable|string|max:300',
            'archivo'       => 'nullable|file|mimes:csv,txt,xlsx,xls|max:5120',
        ]);

        $banco        = BancoCaja::findOrFail($request->banco_caja_id);
        $saldoSistema = $banco->saldo_actual;
        $diferencia   = $request->saldo_banco - $saldoSistema;
        $archivoPath  = null;

        if ($request->hasFile('archivo')) {
            $archivoPath = $request->file('archivo')->store('conciliaciones', 'local');
        }

        $conciliacion = ConciliacionBancaria::create([
            'empresa_id'    => $empresaId,
            'banco_caja_id' => $request->banco_caja_id,
            'fecha_corte'   => $request->fecha_corte,
            'saldo_banco'   => $request->saldo_banco,
            'saldo_sistema' => $saldoSistema,
            'diferencia'    => $diferencia,
            'descripcion'   => $request->descripcion,
            'archivo_csv'   => $archivoPath,
            'estado'        => 'pendiente',
            'created_by'    => Auth::id(),
            'created_at'    => now(),
        ]);

        $movimientosNoConciliados = MovimientoBancario::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $request->banco_caja_id)
            ->where('fecha', '<=', $request->fecha_corte)
            ->where('conciliado', false)
            ->where('anulado', false)
            ->get();

        foreach ($movimientosNoConciliados as $mov) {
            PartidaTransito::create([
                'conciliacion_id' => $conciliacion->id,
                'tipo'            => 'sistema',
                'fecha'           => $mov->fecha,
                'descripcion'     => $mov->descripcion,
                'monto'           => $mov->monto,
                'movimiento_id'   => $mov->id,
                'conciliada'      => false,
            ]);
        }

        $msg = "Conciliación creada. Saldo banco: \${$request->saldo_banco} · Saldo sistema: \${$saldoSistema}";
        if (abs($diferencia) > 0.01) {
            $msg .= ' · Diferencia: $' . number_format(abs($diferencia), 2);
        }

        return back()->with(
            abs($diferencia) > 0.01 ? 'warning' : 'success',
            $msg
        );
    }

    public function show(ConciliacionBancaria $conciliacion): Response
    {
        $conciliacion->load(['bancoCaja', 'partidas.movimiento']);
        return Inertia::render('Bancos/Conciliaciones/Show', [
            'conciliacion' => $conciliacion,
        ]);
    }

    public function marcarConciliada(ConciliacionBancaria $conciliacion): RedirectResponse
    {
        if ($conciliacion->estaConciliada()) {
            return back()->with('error', 'Esta conciliación ya está marcada como conciliada.');
        }

        $movIds = $conciliacion->partidas()
            ->whereNotNull('movimiento_id')
            ->pluck('movimiento_id');

        MovimientoBancario::whereIn('id', $movIds)->update(['conciliado' => true]);
        $conciliacion->update(['estado' => 'conciliada']);

        return back()->with('success', 'Conciliación marcada como conciliada correctamente.');
    }
}
