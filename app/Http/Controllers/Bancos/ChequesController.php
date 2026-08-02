<?php
namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\Cheque;
use App\Models\MovimientoBancario;
use App\Models\ParametroContable;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChequesController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}
    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $cheques = null;

        if ($request->boolean('buscado')) {
            $query = Cheque::with(['bancoCaja'])
                ->where('empresa_id', $empresaId);

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('banco_caja_id')) {
                $query->where('banco_caja_id', $request->banco_caja_id);
            }
            if ($request->filled('buscar')) {
                $q = $request->buscar;
                $query->where(fn($qb) =>
                    $qb->where('numero',       'ilike', "%{$q}%")
                       ->orWhere('beneficiario','ilike', "%{$q}%")
                );
            }

            $cheques = $query->orderByDesc('fecha_emision')
                ->orderByDesc('id')
                ->get()
                ->map(fn($c) => [
                    'id'            => $c->id,
                    'numero'        => $c->numero,
                    'banco_nombre'  => $c->bancoCaja?->nombre,
                    'banco_caja_id' => $c->banco_caja_id,
                    'banco'         => $c->banco,
                    'cuenta'        => $c->cuenta,
                    'monto'         => $c->monto,
                    'fecha_emision' => $c->fecha_emision?->format('d/m/Y'),
                    'fecha_cobro'   => $c->fecha_cobro?->format('d/m/Y'),
                    'beneficiario'  => $c->beneficiario,
                    'estado'        => $c->estado,
                    'observacion'   => $c->observacion,
                    'movimiento_id' => $c->movimiento_id,
                ]);
        }

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->bancos()->orderBy('nombre')
            ->get(['id','nombre','num_cuenta','saldo_actual']);

        return Inertia::render('Bancos/Cheques/Index', [
            'cheques'  => $cheques,
            'bancos'   => $bancos,
            'filtros'  => $request->only(['estado','banco_caja_id','buscar']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'banco_caja_id' => 'required|exists:bancos_cajas,id',
            'numero'        => 'required|string|max:20',
            'monto'         => 'required|numeric|min:0.01',
            'fecha_emision' => 'required|date',
            'beneficiario'  => 'required|string|max:200',
            'banco'         => 'nullable|string|max:100',
            'cuenta'        => 'nullable|string|max:30',
            'fecha_cobro'   => 'nullable|date|after_or_equal:fecha_emision',
            'observacion'   => 'nullable|string|max:300',
        ], [
            'numero.required'       => 'El número de cheque es obligatorio.',
            'beneficiario.required' => 'El beneficiario es obligatorio.',
            'monto.min'             => 'El monto debe ser mayor a $0.',
        ]);

        $existe = Cheque::where('empresa_id', $empresaId)
            ->where('banco_caja_id', $request->banco_caja_id)
            ->where('numero', $request->numero)
            ->exists();

        if ($existe) {
            return back()->with('error',
                "Ya existe el cheque N° {$request->numero} en este banco.");
        }

        try {
            DB::transaction(function () use ($request, $empresaId) {
                $banco = BancoCaja::findOrFail($request->banco_caja_id);

                if ((float) $banco->saldo_actual < (float) $request->monto) {
                    throw new \Exception(
                        "Saldo insuficiente en {$banco->nombre}. " .
                        'Disponible: $' . number_format((float) $banco->saldo_actual, 2)
                    );
                }

                // Crear el movimiento bancario de egreso
                $movimiento = MovimientoBancario::create([
                    'empresa_id'    => $empresaId,
                    'banco_caja_id' => $request->banco_caja_id,
                    'tipo'          => 'egreso',
                    'sub_tipo'      => 'cheque',
                    'fecha'         => $request->fecha_emision,
                    'monto'         => $request->monto,
                    'beneficiario'  => $request->beneficiario,
                    'num_cheque'    => $request->numero,
                    'fecha_cheque'  => $request->fecha_cobro,
                    'descripcion'   => "Cheque N° {$request->numero} — {$request->beneficiario}",
                    'anulado'       => false,
                    'conciliado'    => false,
                    'created_by'    => Auth::id(),
                ]);

                // Descontar saldo del banco
                $banco->actualizarSaldo($request->monto, 'egreso');

                // Registrar el cheque vinculado al movimiento
                Cheque::create([
                    'empresa_id'    => $empresaId,
                    'banco_caja_id' => $request->banco_caja_id,
                    'movimiento_id' => $movimiento->id,
                    'numero'        => $request->numero,
                    'banco'         => $request->banco,
                    'cuenta'        => $request->cuenta,
                    'monto'         => $request->monto,
                    'fecha_emision' => $request->fecha_emision,
                    'fecha_cobro'   => $request->fecha_cobro,
                    'beneficiario'  => $request->beneficiario,
                    'estado'        => 'emitido',
                    'observacion'   => $request->observacion,
                    'created_at'    => now(),
                ]);

                // Asiento contable: DEBE cta_proveedores_locales / HABER banco.cuenta_id
                try {
                    $ctaBanco = $banco->cuenta_id
                        ?? ParametroContable::getCuentaId('cta_bancos_locales', $empresaId);
                    $ctaCxP   = ParametroContable::getCuentaId('cta_proveedores_locales', $empresaId);

                    if ($ctaBanco && $ctaCxP) {
                        $asiento = $this->asientoService->crear(
                            empresaId:    $empresaId,
                            concepto:     "Cheque N° {$request->numero} — {$request->beneficiario}",
                            partidas: [
                                ['cuenta_id'   => $ctaCxP,
                                 'debe'        => (float) $request->monto,
                                 'haber'       => 0,
                                 'descripcion' => "Pago cheque {$request->numero} a {$request->beneficiario}"],
                                ['cuenta_id'   => $ctaBanco,
                                 'debe'        => 0,
                                 'haber'       => (float) $request->monto,
                                 'descripcion' => "Cheque {$request->numero} emitido"],
                            ],
                            documentoTipo: 'BANCO',
                            documentoId:   $movimiento->id,
                            documentoRef:  "CHQ-{$request->numero}",
                            esAutomatico:  true,
                            fecha:         $request->fecha_emision,
                        );
                        $movimiento->update(['asiento_id' => $asiento->id]);
                    }
                } catch (\Throwable) {
                    // No bloquear si período cerrado o cuenta sin configurar
                }
            });

            return back()->with('success',
                "Cheque N° {$request->numero} registrado. Saldo del banco actualizado.");

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cambiarEstado(Request $request, Cheque $cheque): RedirectResponse
    {
        $request->validate([
            'estado'      => 'required|in:cobrado,protestado,anulado',
            'fecha_cobro' => 'nullable|date',
            'observacion' => 'nullable|string|max:300',
        ]);

        if ($cheque->estado !== 'emitido') {
            return back()->with('error',
                "Este cheque ya fue {$cheque->estado}. No se puede modificar.");
        }

        DB::transaction(function () use ($request, $cheque) {
            $cheque->update([
                'estado'      => $request->estado,
                'fecha_cobro' => $request->fecha_cobro ?? now()->toDateString(),
                'observacion' => $request->observacion,
            ]);

            // Si el cheque es protestado o anulado → revertir saldo bancario y asiento contable
            if (in_array($request->estado, ['protestado', 'anulado']) && $cheque->movimiento_id) {
                $movimiento = $cheque->movimiento()->with('asiento')->first();

                if ($movimiento && !$movimiento->anulado) {
                    $tipoReversa = $movimiento->tipo === 'ingreso' ? 'egreso' : 'ingreso';

                    // Revertir asiento contable si existe (genera su propio asiento de reversa)
                    $asientoReversaId = null;
                    if ($movimiento->asiento_id && $movimiento->asiento && !$movimiento->asiento->estaAnulado()) {
                        try {
                            $asientoReversa = $this->asientoService->anular(
                                $movimiento->asiento,
                                "Cheque N° {$cheque->numero} {$request->estado}" .
                                ($request->observacion ? " — {$request->observacion}" : '')
                            );
                            $asientoReversaId = $asientoReversa->id;
                        } catch (\Throwable) {
                            // Si el período está cerrado no bloquear: el contador revisará manualmente
                        }
                    }

                    // El movimiento original queda intacto como evidencia histórica, solo
                    // marcado anulado. La reversión real es un movimiento NUEVO de signo
                    // contrario, enlazado al cheque vía documento_tipo/documento_id.
                    $movimiento->update(['anulado' => true]);

                    MovimientoBancario::create([
                        'empresa_id'     => $movimiento->empresa_id,
                        'banco_caja_id'  => $movimiento->banco_caja_id,
                        'tipo'           => $tipoReversa,
                        'sub_tipo'       => $movimiento->sub_tipo,
                        'fecha'          => now()->toDateString(),
                        'monto'          => $movimiento->monto,
                        'beneficiario'   => $movimiento->beneficiario,
                        'descripcion'    => "Reversión cheque N° {$cheque->numero} ({$request->estado})",
                        'documento_tipo' => 'ANULACION_CHEQUE',
                        'documento_id'   => $cheque->id,
                        'asiento_id'     => $asientoReversaId,
                        'anulado'        => false,
                        'conciliado'     => false,
                        'created_by'     => Auth::id(),
                    ]);

                    $cheque->bancoCaja->actualizarSaldo((float) $cheque->monto, $tipoReversa);
                }
            }

            if (in_array($request->estado, ['protestado', 'anulado'])) {
                DB::table('log_cambios_criticos')->insert([
                    'usuario_id'     => Auth::id(),
                    'empresa_id'     => $cheque->empresa_id,
                    'tabla'          => 'cheques',
                    'registro_id'    => $cheque->id,
                    'campo'          => 'estado',
                    'valor_anterior' => 'emitido',
                    'valor_nuevo'    => "{$request->estado} — " . ($request->observacion ?? "Cheque {$request->estado}"),
                    'ip_address'     => $request->ip(),
                ]);
            }
        });

        $mensajes = [
            'cobrado'    => "Cheque N° {$cheque->numero} marcado como cobrado.",
            'protestado' => "Cheque N° {$cheque->numero} protestado. Saldo bancario revertido.",
            'anulado'    => "Cheque N° {$cheque->numero} anulado. Saldo bancario revertido.",
        ];

        return back()->with(
            $request->estado === 'protestado' ? 'warning' : 'success',
            $mensajes[$request->estado]
        );
    }
}
