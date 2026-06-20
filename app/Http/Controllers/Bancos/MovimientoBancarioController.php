<?php

namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\MovimientoBancario;
use App\Models\PlanCuenta;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoBancarioController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = MovimientoBancario::with(['bancoCaja', 'cuentaContrapartida', 'creadoPor'])
            ->where('empresa_id', $empresaId);

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
        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->where('descripcion',    'ilike', "%{$q}%")
                   ->orWhere('beneficiario', 'ilike', "%{$q}%")
                   ->orWhere('num_documento','ilike', "%{$q}%")
            );
        }

        $movimientos = $query->orderByDesc('fecha')
                             ->orderByDesc('id')
                             ->paginate(25)
                             ->withQueryString();

        $bancos  = BancoCaja::where('empresa_id', $empresaId)
                    ->activos()->orderBy('nombre')
                    ->get(['id', 'nombre', 'tipo', 'saldo_actual']);
        $cuentas = PlanCuenta::where('permite_asientos', true)
                    ->where('estado', true)->orderBy('codigo')
                    ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('Bancos/Movimientos/Index', [
            'movimientos' => $movimientos,
            'bancos'      => $bancos,
            'cuentas'     => $cuentas,
            'filtros'     => $request->only([
                'banco_caja_id', 'tipo', 'fecha_desde', 'fecha_hasta', 'buscar',
            ]),
            'stats' => [
                'total_ingresos'       => MovimientoBancario::where('empresa_id', $empresaId)
                    ->where('tipo', 'ingreso')->where('anulado', false)->sum('monto'),
                'total_egresos'        => MovimientoBancario::where('empresa_id', $empresaId)
                    ->where('tipo', 'egreso')->where('anulado', false)->sum('monto'),
                'pendientes_conciliar' => MovimientoBancario::where('empresa_id', $empresaId)
                    ->where('conciliado', false)->where('anulado', false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'banco_caja_id'           => 'required|exists:bancos_cajas,id',
            'tipo'                    => 'required|in:ingreso,egreso',
            'sub_tipo'                => 'required|in:transferencia,cheque,efectivo,deposito',
            'fecha'                   => 'required|date',
            'monto'                   => 'required|numeric|min:0.01',
            'beneficiario'            => 'nullable|string|max:200',
            'num_documento'           => 'nullable|string|max:50',
            'descripcion'             => 'required|string|max:500',
            'cuenta_contrapartida_id' => 'required|exists:plan_cuentas,id',
        ], [
            'monto.min'                           => 'El monto debe ser mayor a 0.',
            'cuenta_contrapartida_id.required'    => 'Selecciona la cuenta contable de contrapartida.',
            'descripcion.required'                => 'La descripción es obligatoria.',
        ]);

        try {
            DB::transaction(function () use ($request, $empresaId) {
                $banco = BancoCaja::findOrFail($request->banco_caja_id);

                if ($request->tipo === 'egreso' && $banco->saldo_actual < $request->monto) {
                    throw new \Exception(
                        "Saldo insuficiente. Saldo disponible: \${$banco->saldo_actual}"
                    );
                }

                $movimiento = MovimientoBancario::create([
                    ...$request->only([
                        'banco_caja_id', 'tipo', 'sub_tipo', 'fecha', 'monto',
                        'persona_tipo', 'persona_id', 'beneficiario',
                        'num_documento', 'num_cheque', 'fecha_cheque',
                        'descripcion', 'documento_tipo', 'documento_id',
                        'cuenta_contrapartida_id', 'es_postfechado',
                    ]),
                    'empresa_id' => $empresaId,
                    'anulado'    => false,
                    'conciliado' => false,
                    'created_by' => Auth::id(),
                ]);

                $banco->actualizarSaldo($request->monto, $request->tipo);

                try {
                    $ctaBanco = $banco->cuenta_id;
                    $ctaContra = $request->cuenta_contrapartida_id;

                    if ($ctaBanco) {
                        $partidas = $request->tipo === 'ingreso'
                            ? [
                                ['cuenta_id' => $ctaBanco,  'debe' => $request->monto, 'haber' => 0, 'descripcion' => $request->descripcion],
                                ['cuenta_id' => $ctaContra, 'debe' => 0, 'haber' => $request->monto, 'descripcion' => $request->descripcion],
                              ]
                            : [
                                ['cuenta_id' => $ctaContra, 'debe' => $request->monto, 'haber' => 0, 'descripcion' => $request->descripcion],
                                ['cuenta_id' => $ctaBanco,  'debe' => 0, 'haber' => $request->monto, 'descripcion' => $request->descripcion],
                              ];

                        $asiento = $this->asientoService->crear(
                            empresaId:    $empresaId,
                            concepto:     "{$request->tipo} — {$request->descripcion}",
                            partidas:     $partidas,
                            documentoTipo:'BANCO',
                            documentoId:  $movimiento->id,
                            esAutomatico: true,
                        );
                        $movimiento->update(['asiento_id' => $asiento->id]);
                    }
                } catch (\Exception $e) {
                    // No bloquear si período cerrado o cuenta sin plan
                }
            });

            return back()->with('success', 'Movimiento registrado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function exportarXml(Request $request): \Illuminate\Http\Response
    {
        $empresaId   = session('empresa_activa_id');
        $movimientos = MovimientoBancario::with(['bancoCaja', 'cuentaContrapartida'])
            ->where('empresa_id', $empresaId)
            ->where('anulado', false)
            ->orderByDesc('fecha')
            ->get();

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<movimientos_bancarios>' . "\n";
        $xml .= '  <empresa_id>' . $empresaId . '</empresa_id>' . "\n";
        $xml .= '  <generado>' . now()->format('Y-m-d H:i:s') . '</generado>' . "\n";
        $xml .= '  <total>' . $movimientos->count() . '</total>' . "\n";

        foreach ($movimientos as $m) {
            $xml .= '  <movimiento>' . "\n";
            $xml .= '    <id>'            . $m->id                                                  . '</id>' . "\n";
            $xml .= '    <fecha>'         . ($m->fecha?->format('Y-m-d') ?? '')                     . '</fecha>' . "\n";
            $xml .= '    <banco>'         . htmlspecialchars($m->bancoCaja?->nombre ?? '')           . '</banco>' . "\n";
            $xml .= '    <tipo>'          . $m->tipo                                                 . '</tipo>' . "\n";
            $xml .= '    <sub_tipo>'      . ($m->sub_tipo ?? '')                                     . '</sub_tipo>' . "\n";
            $xml .= '    <monto>'         . number_format($m->monto, 2, '.', '')                    . '</monto>' . "\n";
            $xml .= '    <beneficiario>'  . htmlspecialchars($m->beneficiario ?? '')                 . '</beneficiario>' . "\n";
            $xml .= '    <descripcion>'   . htmlspecialchars($m->descripcion ?? '')                  . '</descripcion>' . "\n";
            $xml .= '    <num_documento>' . htmlspecialchars($m->num_documento ?? '')                . '</num_documento>' . "\n";
            $xml .= '    <conciliado>'    . ($m->conciliado ? 'true' : 'false')                     . '</conciliado>' . "\n";
            $xml .= '  </movimiento>' . "\n";
        }

        $xml .= '</movimientos_bancarios>';

        return response($xml, 200, [
            'Content-Type'        => 'application/xml',
            'Content-Disposition' => 'attachment; filename="movimientos-' . now()->format('Y-m-d') . '.xml"',
        ]);
    }

    public function anular(Request $request, MovimientoBancario $movimiento): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:300',
        ]);

        if ($movimiento->anulado) {
            return back()->with('error', 'Este movimiento ya está anulado.');
        }
        if ($movimiento->conciliado) {
            return back()->with('error',
                'No se puede anular: el movimiento ya fue conciliado con el banco.');
        }

        DB::transaction(function () use ($movimiento, $request) {
            $movimiento->update(['anulado' => true]);

            $movimiento->bancoCaja->actualizarSaldo(
                $movimiento->monto,
                $movimiento->tipo === 'ingreso' ? 'egreso' : 'ingreso'
            );

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'empresa_id'     => $movimiento->empresa_id,
                'tabla'          => 'movimientos_bancarios',
                'registro_id'    => $movimiento->id,
                'campo'          => 'anulado',
                'valor_anterior' => 'false',
                'valor_nuevo'    => "true — {$request->motivo}",
                'ip_address'     => $request->ip(),
            ]);
        });

        return back()->with('success', 'Movimiento anulado correctamente.');
    }
}
