<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\CentroCosto;
use App\Services\CobroBancoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Decisiones del usuario sobre a dónde entra el dinero de las ventas cobradas en el acto.
 * Se guardan en `configuraciones` (clave/valor por empresa). Mientras no se configuren, las
 * facturas funcionan igual que antes y no generan movimientos bancarios.
 */
class ConfiguracionCobrosController extends Controller
{
    private const DESCRIPCIONES = [
        'cobro_banco_transferencia' => 'Banco donde entran las transferencias de ventas',
        'cobro_banco_tarjeta'       => 'Banco donde entran las ventas con tarjeta (modo banco)',
        'cobro_banco_cheque'        => 'Banco donde se depositan los cheques de ventas',
        'cobro_caja_efectivo'       => 'Caja por defecto para ventas en efectivo (si el centro de costo no tiene caja)',
        'cobro_tarjeta_modo'        => 'Ventas con tarjeta: banco directo o lote Datafast',
    ];

    public function __construct(private CobroBancoService $cobros) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');

        $cuentas = BancoCaja::where('empresa_id', $empresaId)->activos()
            ->orderBy('tipo')->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $cajasPorCentro = BancoCaja::where('empresa_id', $empresaId)->activos()->cajas()
            ->whereNotNull('centro_costo_id')
            ->get(['nombre', 'centro_costo_id'])
            ->groupBy('centro_costo_id');

        $centros = CentroCosto::where('empresa_id', $empresaId)->where('estado', true)
            ->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn($c) => [
                'id'     => $c->id,
                'nombre' => $c->nombre,
                'cajas'  => ($cajasPorCentro[$c->id] ?? collect())->pluck('nombre')->values(),
            ]);

        return Inertia::render('Bancos/ConfiguracionCobros/Index', [
            'config'  => $this->cobros->config((int) $empresaId),
            'cuentas' => $cuentas,
            'centros' => $centros,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        $existeCuenta = Rule::exists('bancos_cajas', 'id')->where('empresa_id', $empresaId);

        $request->validate([
            'cobro_banco_transferencia' => ['nullable', $existeCuenta],
            'cobro_banco_tarjeta'       => ['nullable', $existeCuenta],
            'cobro_banco_cheque'        => ['nullable', $existeCuenta],
            'cobro_caja_efectivo'       => ['nullable', $existeCuenta],
            'cobro_tarjeta_modo'        => ['nullable', 'in:banco,datafast'],
        ]);

        DB::transaction(function () use ($request, $empresaId) {
            foreach (CobroBancoService::CLAVES as $clave) {
                $valor = $request->input($clave);
                $fila  = DB::table('configuraciones')->where('empresa_id', $empresaId)->where('clave', $clave);

                if ($valor === null || $valor === '') {
                    $fila->delete();
                    continue;
                }

                if ($fila->exists()) {
                    $fila->update(['valor' => (string) $valor, 'updated_at' => now()]);
                } else {
                    DB::table('configuraciones')->insert([
                        'empresa_id'  => $empresaId,
                        'clave'       => $clave,
                        'valor'       => (string) $valor,
                        'tipo'        => 'string',
                        'descripcion' => self::DESCRIPCIONES[$clave],
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        });

        return back()->with('success', 'Configuración de cobros guardada.');
    }
}
