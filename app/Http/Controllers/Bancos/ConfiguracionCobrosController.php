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
        'cobro_banco_tarjeta'       => 'Banco donde entran las ventas con tarjeta (modo banco)',
        'cobro_banco_cheque'        => 'Banco donde se depositan los cheques de ventas',
        'cobro_tarjeta_modo'        => 'Ventas con tarjeta: banco directo o lote Datafast',
    ];

    public function __construct(private CobroBancoService $cobros) {}

    public function index(): Response
    {
        $empresaId = session('empresa_activa_id');

        $cuentas = BancoCaja::where('empresa_id', $empresaId)->activos()
            ->orderBy('tipo')->orderBy('nombre')
            ->with('cuenta:id,codigo,nombre')
            ->get(['id', 'nombre', 'tipo', 'cuenta_id'])
            // La cuenta contable se muestra antes de guardar (02-06 §2.6)
            ->map(fn ($c) => [
                'id' => $c->id, 'nombre' => $c->nombre, 'tipo' => $c->tipo,
                'cuenta_contable' => $c->cuenta ? "{$c->cuenta->codigo} — {$c->cuenta->nombre}" : null,
            ]);

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
            'usos'    => $this->usosPorDestino((int) $empresaId),
        ]);
    }

    /**
     * Facturas ya registradas en cada banco o caja, por forma de pago (02-06 §2.6: «mostrar qué facturas usan cada
     * configuración»). Un cambio de configuración solo afecta a las facturas nuevas: las emitidas conservan su destino.
     *
     * @return array<int, array<string, mixed>>
     */
    private function usosPorDestino(int $empresaId): array
    {
        $filas = DB::table('movimientos_bancarios as m')
            ->join('bancos_cajas as b', 'b.id', '=', 'm.banco_caja_id')
            ->where('m.empresa_id', $empresaId)->where('m.documento_tipo', 'FACTURA_VENTA')->where('m.anulado', false)
            ->groupBy('b.id', 'b.nombre', 'm.sub_tipo')
            ->orderBy('b.nombre')->orderBy('m.sub_tipo')
            ->selectRaw('b.nombre as destino, m.sub_tipo as forma, count(distinct m.documento_id) as facturas, sum(m.monto) as total, min(m.fecha) as desde, max(m.fecha) as hasta')
            ->get();

        return $filas->map(fn ($f) => [
            'destino' => $f->destino, 'forma' => $f->forma, 'facturas' => (int) $f->facturas,
            'total' => round((float) $f->total, 2), 'desde' => $f->desde, 'hasta' => $f->hasta,
        ])->all();
    }

    public function update(Request $request): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        $existeCuenta = Rule::exists('bancos_cajas', 'id')->where('empresa_id', $empresaId);

        $request->validate([
            // Transferencia y cheque siempre se registran en Bancos (02-06 §2.7, CAM-101):
            // ya no existe la opción «No registrar en Bancos».
            'cobro_banco_cheque'        => ['required', $existeCuenta],
            'cobro_tarjeta_modo'        => ['required', 'in:banco,datafast'],
            'cobro_banco_tarjeta'       => [Rule::requiredIf($request->input('cobro_tarjeta_modo') === 'banco'), 'nullable', $existeCuenta],
        ], [
            'cobro_banco_cheque.required'        => 'Seleccione el banco donde se depositan los cheques.',
            'cobro_tarjeta_modo.required'        => 'Indique cómo se cobra con tarjeta.',
            'cobro_banco_tarjeta.required'       => 'Seleccione el banco donde entran las ventas con tarjeta.',
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
