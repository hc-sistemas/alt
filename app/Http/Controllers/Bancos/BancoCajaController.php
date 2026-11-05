<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Models\ParametroContable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BancoCajaController extends Controller
{
    /**
     * La cuenta contable se asigna sola y no se edita (02-06 §2.1, PC-BAN-01).
     * Los códigos viven en parametros_contables; aquí solo se dice qué parámetro
     * corresponde a cada tipo de caja y a cada banco con su tipo de cuenta.
     */
    private const PARAM_POR_TIPO = [
        'caja'       => 'cta_caja_general',
        'caja_chica' => 'cta_cajas_chicas',
        'tarjeta'    => 'cta_vouchers',
    ];

    /** Bancos admitidos: parte del nombre, tipo de cuenta que les corresponde y parámetro. */
    private const REGLAS_BANCO = [
        ['patron' => 'pichincha',  'tipo_cuenta' => 'corriente', 'param' => 'cta_banco_pichincha',  'etiqueta' => 'Banco Pichincha (corriente)'],
        ['patron' => 'produbanco', 'tipo_cuenta' => 'ahorros',   'param' => 'cta_banco_produbanco', 'etiqueta' => 'ProduBanco (ahorros)'],
        ['patron' => 'guayaquil',  'tipo_cuenta' => 'ahorros',   'param' => 'cta_banco_guayaquil',  'etiqueta' => 'Banco de Guayaquil (ahorros)'],
        ['patron' => 'exterior',   'tipo_cuenta' => null,        'param' => 'cta_bancos_exterior',  'etiqueta' => 'Bancos del exterior'],
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

        $centrosCosto = CentroCosto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'codigo']);

        return Inertia::render('Bancos/BancosCajas/Index', [
            'bancos'        => $bancos,
            'centrosCosto'  => $centrosCosto,
            'reglasBanco'   => $this->reglasParaPantalla((int) $empresaId),
            'cuentasFijas'  => $this->cuentasFijasParaPantalla((int) $empresaId),
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
            'centro_costo_id' => [
                Rule::requiredIf(in_array($request->tipo, self::TIPOS_REQUIEREN_CENTRO_COSTO)),
                'nullable', 'exists_empresa:centros_costo',
            ],
            'saldo_inicial'   => 'numeric|min:0',
        ]);

        [$cuenta, $error] = $this->resolverCuenta($request->tipo, $request->nombre, $request->tipo_cuenta, (int) $empresaId);
        if ($error) {
            return back()->withErrors(['nombre' => $error])->withInput();
        }
        $cuentaId = $cuenta->id;

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
            'centro_costo_id' => [
                Rule::requiredIf(in_array($banco->tipo, self::TIPOS_REQUIEREN_CENTRO_COSTO)),
                'nullable', 'exists_empresa:centros_costo',
            ],
        ]);

        [$cuenta, $error] = $this->resolverCuenta($banco->tipo, $request->nombre, $request->tipo_cuenta, (int) session('empresa_activa_id'));
        if ($error) {
            return back()->withErrors(['nombre' => $error])->withInput();
        }
        $cuentaId = $cuenta->id;

        $banco->update([
            ...$request->only(['nombre', 'num_cuenta', 'tipo_cuenta', 'centro_costo_id']),
            'cuenta_id' => $cuentaId,
        ]);

        return back()->with('success', "{$banco->nombre} actualizado correctamente.");
    }

    /**
     * Cuenta contable que corresponde a un banco o caja. Devuelve [cuenta, error].
     * Si el banco no tiene cuenta para su tipo, no se guarda (02-06 §2.1).
     */
    private function resolverCuenta(string $tipo, ?string $nombre, ?string $tipoCuenta, int $empresaId): array
    {
        if ($tipo !== 'banco') {
            $param  = self::PARAM_POR_TIPO[$tipo] ?? null;
            $cuenta = $param ? ParametroContable::getCuenta($param, $empresaId) : null;
            return $cuenta
                ? [$cuenta, null]
                : [null, "No hay cuenta contable configurada para el tipo {$tipo}. Configúrela en Contabilidad → Parámetros Contables."];
        }

        $nombreNorm = mb_strtolower((string) $nombre);
        foreach (self::REGLAS_BANCO as $regla) {
            if (!str_contains($nombreNorm, $regla['patron'])) {
                continue;
            }
            if ($regla['tipo_cuenta'] && $regla['tipo_cuenta'] !== $tipoCuenta) {
                return [null, "{$regla['etiqueta']}: este banco solo tiene cuenta contable de tipo {$regla['tipo_cuenta']}. Seleccione el tipo correcto."];
            }
            $cuenta = ParametroContable::getCuenta($regla['param'], $empresaId);
            return $cuenta
                ? [$cuenta, null]
                : [null, "El parámetro {$regla['param']} no está configurado. Configúrelo en Contabilidad → Parámetros Contables."];
        }

        $admitidos = implode(', ', array_column(self::REGLAS_BANCO, 'etiqueta'));
        return [null, "Este banco no tiene cuenta contable definida, por eso no se puede guardar. Bancos admitidos: {$admitidos}."];
    }

    /** Reglas de bancos con su cuenta, para mostrar la cuenta en el formulario. */
    private function reglasParaPantalla(int $empresaId): array
    {
        return array_map(function ($r) use ($empresaId) {
            $c = ParametroContable::getCuenta($r['param'], $empresaId);
            return [
                'patron'      => $r['patron'],
                'tipo_cuenta' => $r['tipo_cuenta'],
                'etiqueta'    => $r['etiqueta'],
                'cuenta'      => $c ? "{$c->codigo} — {$c->nombre}" : null,
            ];
        }, self::REGLAS_BANCO);
    }

    /** Cuenta fija de caja, caja chica y tarjeta, para mostrarla en el formulario. */
    private function cuentasFijasParaPantalla(int $empresaId): array
    {
        $out = [];
        foreach (self::PARAM_POR_TIPO as $tipo => $param) {
            $c = ParametroContable::getCuenta($param, $empresaId);
            $out[$tipo] = $c ? "{$c->codigo} — {$c->nombre}" : null;
        }
        return $out;
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
