<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\PlanCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BancoCajaController extends Controller
{
    /**
     * Fuente única de verdad para la cuenta contable automática por tipo.
     * Códigos con cero a la izquierda — deben coincidir EXACTAMENTE con el
     * formato real de plan_cuentas.codigo (verificado contra la BD, no
     * asumido: '1.1.1.3' nunca coincide con '1.1.1.03').
     * Se expone tal cual al frontend (ver index()) para que no exista una
     * segunda copia hardcodeada que pueda desincronizarse otra vez.
     */
    private const CUENTAS_AUTO = [
        'banco'      => '1.1.1.03',
        'caja'       => '1.1.1.01',
        'caja_chica' => '1.1.1.02',
        'tarjeta'    => '1.1.1.05',
    ];

    /** Tipos de bancos_cajas que requieren centro de costo obligatorio (petición del cliente: "al crear una Caja"). */
    private const TIPOS_REQUIEREN_CENTRO_COSTO = ['caja', 'caja_chica'];

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');
        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->with(['cuenta', 'centroCosto'])
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->map(fn($b) => [
                'id'              => $b->id,
                'tipo'            => $b->tipo,
                'tipo_label'      => $b->tipo_label,
                'tipo_color'      => $b->tipo_color,
                'nombre'          => $b->nombre,
                'num_cuenta'      => $b->num_cuenta,
                'tipo_cuenta'     => $b->tipo_cuenta,
                'saldo_inicial'   => $b->saldo_inicial,
                'saldo_actual'    => $b->saldo_actual,
                'cuenta_id'       => $b->cuenta_id,
                'cuenta'          => $b->cuenta
                    ? "{$b->cuenta->codigo} — {$b->cuenta->nombre}"
                    : null,
                'centro_costo_id' => $b->centro_costo_id,
                'centro_costo'    => $b->centroCosto?->nombre,
                'estado'          => $b->estado,
            ]);

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $centrosCosto = CentroCosto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return Inertia::render('Bancos/BancosCajas/Index', [
            'bancos'        => $bancos,
            'cuentas'       => $cuentas,
            'centrosCosto'  => $centrosCosto,
            'cuentasAutoMap' => self::CUENTAS_AUTO,
            'tiposRequierenCentroCosto' => self::TIPOS_REQUIEREN_CENTRO_COSTO,
            'stats'   => [
                'total_bancos'  => $bancos->where('tipo', 'banco')->count(),
                'total_cajas'   => $bancos->whereIn('tipo', ['caja', 'caja_chica', 'tarjeta'])->count(),
                'saldo_bancos'  => $bancos->where('tipo', 'banco')->sum('saldo_actual'),
                'saldo_cajas'   => $bancos->whereIn('tipo', ['caja', 'caja_chica', 'tarjeta'])->sum('saldo_actual'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $request->validate([
            'tipo'            => 'required|in:banco,caja,caja_chica,tarjeta',
            'nombre'          => 'required|string|max:150',
            'num_cuenta'      => 'nullable|string|max:30',
            'tipo_cuenta'     => 'nullable|in:ahorros,corriente',
            'cuenta_id'       => 'nullable|exists:plan_cuentas,id',
            'centro_costo_id' => [
                Rule::requiredIf(in_array($request->tipo, self::TIPOS_REQUIEREN_CENTRO_COSTO)),
                'nullable', 'exists:centros_costo,id',
            ],
            'saldo_inicial'   => 'numeric|min:0',
        ]);

        $cuentaId = $request->cuenta_id
            ?: ($this->getCuentaContableAutomatica($request->tipo, $empresaId)?->id);

        $banco = BancoCaja::create([
            ...$request->only(['tipo', 'nombre', 'num_cuenta', 'tipo_cuenta', 'saldo_inicial', 'centro_costo_id']),
            'empresa_id'   => $empresaId,
            'cuenta_id'    => $cuentaId,
            'saldo_actual' => $request->saldo_inicial ?? 0,
            'estado'       => true,
        ]);

        return back()->with('success', "{$banco->tipo_label} {$banco->nombre} creado correctamente.");
    }

    public function update(Request $request, BancoCaja $banco): RedirectResponse
    {
        $request->validate([
            'nombre'          => 'required|string|max:150',
            'num_cuenta'      => 'nullable|string|max:30',
            'tipo_cuenta'     => 'nullable|in:ahorros,corriente',
            'cuenta_id'       => 'nullable|exists:plan_cuentas,id',
            'centro_costo_id' => [
                Rule::requiredIf(in_array($banco->tipo, self::TIPOS_REQUIEREN_CENTRO_COSTO)),
                'nullable', 'exists:centros_costo,id',
            ],
        ]);

        $cuentaId = $request->cuenta_id
            ?: ($this->getCuentaContableAutomatica($banco->tipo, session('empresa_activa_id'))?->id);

        $banco->update([
            ...$request->only(['nombre', 'num_cuenta', 'tipo_cuenta', 'centro_costo_id']),
            'cuenta_id' => $cuentaId,
        ]);

        return back()->with('success', "{$banco->nombre} actualizado correctamente.");
    }

    private function getCuentaContableAutomatica(string $tipo, int $empresaId): ?PlanCuenta
    {
        $codigo = self::CUENTAS_AUTO[$tipo] ?? null;
        if (!$codigo) return null;

        return PlanCuenta::where('codigo', $codigo)
            ->where(function ($q) use ($empresaId) {
                $q->whereNull('empresa_id')->orWhere('empresa_id', $empresaId);
            })
            ->where('estado', true)
            ->first();
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
