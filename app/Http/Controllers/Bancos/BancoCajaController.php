<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\PlanCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BancoCajaController extends Controller
{
    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');
        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->with('cuenta')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->map(fn($b) => [
                'id'            => $b->id,
                'tipo'          => $b->tipo,
                'tipo_label'    => $b->tipo_label,
                'tipo_color'    => $b->tipo_color,
                'nombre'        => $b->nombre,
                'num_cuenta'    => $b->num_cuenta,
                'tipo_cuenta'   => $b->tipo_cuenta,
                'saldo_inicial' => $b->saldo_inicial,
                'saldo_actual'  => $b->saldo_actual,
                'cuenta'        => $b->cuenta
                    ? "{$b->cuenta->codigo} — {$b->cuenta->nombre}"
                    : null,
                'estado'        => $b->estado,
            ]);

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('Bancos/BancosCajas/Index', [
            'bancos'  => $bancos,
            'cuentas' => $cuentas,
            'stats'   => [
                'total_bancos' => $bancos->where('tipo', 'banco')->count(),
                'total_cajas'  => $bancos->whereIn('tipo', ['caja', 'caja_chica'])->count(),
                'saldo_bancos' => $bancos->where('tipo', 'banco')->sum('saldo_actual'),
                'saldo_cajas'  => $bancos->whereIn('tipo', ['caja', 'caja_chica'])->sum('saldo_actual'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'tipo'          => 'required|in:banco,caja,caja_chica,tarjeta',
            'nombre'        => 'required|string|max:150',
            'num_cuenta'    => 'nullable|string|max:30',
            'tipo_cuenta'   => 'nullable|in:ahorros,corriente',
            'cuenta_id'     => 'nullable|exists:plan_cuentas,id',
            'saldo_inicial' => 'numeric|min:0',
        ]);

        $banco = BancoCaja::create([
            ...$request->only(['tipo', 'nombre', 'num_cuenta', 'tipo_cuenta', 'cuenta_id', 'saldo_inicial']),
            'empresa_id'   => $empresaId,
            'saldo_actual' => $request->saldo_inicial ?? 0,
            'estado'       => true,
        ]);

        return back()->with('success', "{$banco->tipo_label} {$banco->nombre} creado correctamente.");
    }

    public function update(Request $request, BancoCaja $banco): RedirectResponse
    {
        $request->validate([
            'nombre'      => 'required|string|max:150',
            'num_cuenta'  => 'nullable|string|max:30',
            'tipo_cuenta' => 'nullable|in:ahorros,corriente',
            'cuenta_id'   => 'nullable|exists:plan_cuentas,id',
        ]);

        $banco->update($request->only(['nombre', 'num_cuenta', 'tipo_cuenta', 'cuenta_id']));

        return back()->with('success', "{$banco->nombre} actualizado correctamente.");
    }

    public function toggleEstado(BancoCaja $banco): RedirectResponse
    {
        if ($banco->estado && $banco->saldo_actual != 0) {
            return back()->with('error',
                "No se puede desactivar: tiene saldo de \${$banco->saldo_actual}.");
        }
        $banco->update(['estado' => !$banco->estado]);
        $accion = $banco->fresh()->estado ? 'activado' : 'desactivado';
        return back()->with('success', "{$banco->nombre} {$accion} correctamente.");
    }

    public function destroy(BancoCaja $banco): RedirectResponse
    {
        if (abs((float)$banco->saldo_actual) > 0.01) {
            return back()->with('error',
                "No se puede eliminar \"{$banco->nombre}\": tiene saldo de \$" .
                number_format($banco->saldo_actual, 2) . ". Solo puedes inactivarlo.");
        }

        if ($banco->movimientos()->exists()) {
            return back()->with('error',
                "No se puede eliminar \"{$banco->nombre}\": tiene movimientos registrados. Solo puedes inactivarlo.");
        }

        $nombre = $banco->nombre;
        $banco->delete();

        return back()->with('success', "{$nombre} eliminado permanentemente.");
    }
}
