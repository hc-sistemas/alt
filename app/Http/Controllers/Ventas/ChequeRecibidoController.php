<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\BancoCaja;
use App\Models\ChequeRecibido;
use App\Models\MovimientoBancario;
use App\Services\AsientoService;
use App\Services\CobroBancoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cheques recibidos de clientes (02-04 §2.7). Quedan en cartera (PC-VEN-13) y solo se vuelven movimiento bancario al
 * depositarlos (PC-VEN-14, DEC-001). Un post fechado no se puede depositar antes de su fecha.
 */
class ChequeRecibidoController extends Controller
{
    public function __construct(private AsientoService $asiento, private CobroBancoService $cobros) {}

    public function index(Request $request): Response
    {
        $empresaId = (int) session('empresa_activa_id');

        $cheques = ChequeRecibido::with(['cliente:id,razon_social', 'factura:id,numero_completo', 'bancoCaja:id,nombre'])
            ->where('empresa_id', $empresaId)
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
            ->when($request->filled('buscar'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('numero', 'ilike', "%{$request->buscar}%")
                  ->orWhereHas('cliente', fn ($c) => $c->where('razon_social', 'ilike', "%{$request->buscar}%"));
            }))
            ->orderByRaw("case estado when 'en_cartera' then 0 else 1 end")
            ->orderBy('fecha_cheque')->orderBy('id')
            ->paginate(30)->withQueryString()
            ->through(fn (ChequeRecibido $c) => [
                'id' => $c->id, 'numero' => $c->numero, 'cliente' => $c->cliente?->razon_social, 'factura' => $c->factura?->numero_completo,
                'banco_emisor' => $c->banco_emisor, 'monto' => (float) $c->monto, 'estado' => $c->estado,
                'fecha_recepcion' => $c->fecha_recepcion?->format('d/m/Y'), 'fecha_cheque' => $c->fecha_cheque?->format('d/m/Y'),
                'fecha_cheque_iso' => $c->fecha_cheque?->toDateString(),
                'postfechado' => $c->esPostfechado(), 'depositable' => $c->estado === 'en_cartera' && !$c->fecha_cheque?->isFuture(),
                'deposito' => $c->estado === 'depositado' ? ($c->bancoCaja?->nombre . ' · ' . $c->fecha_deposito?->format('d/m/Y')) : null,
            ]);

        return Inertia::render('Ventas/ChequesRecibidos/Index', [
            'cheques' => $cheques,
            'bancos'  => BancoCaja::where('empresa_id', $empresaId)->activos()->where('tipo', 'banco')->orderBy('nombre')->get(['id', 'nombre']),
            // Banco de depósito sugerido (Bancos → Configuración de cobros); solo pre-selecciona, siempre se puede cambiar
            'bancoSugerido' => $this->cobros->config($empresaId)['cobro_banco_cheque'],
            'totales' => [
                'en_cartera' => (float) ChequeRecibido::where('empresa_id', $empresaId)->where('estado', 'en_cartera')->sum('monto'),
                'cantidad'   => ChequeRecibido::where('empresa_id', $empresaId)->where('estado', 'en_cartera')->count(),
            ],
            'filtros' => $request->only(['estado', 'buscar']),
        ]);
    }

    public function depositar(Request $request, ChequeRecibido $cheque): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        $request->validate([
            'banco_caja_id'  => ['required', \Illuminate\Validation\Rule::exists('bancos_cajas', 'id')->where('empresa_id', $empresaId)],
            'fecha_deposito' => 'required|date',
            'referencia'     => 'required|string|max:50',
        ], [
            'banco_caja_id.required' => 'Seleccione el banco donde se deposita el cheque.',
            'referencia.required'    => 'Ingrese la referencia (N° de papeleta de depósito).',
        ]);

        if ($cheque->estado !== 'en_cartera') {
            return back()->with('error', "El cheque N° {$cheque->numero} ya no está en cartera ({$cheque->estado}).");
        }
        if ($cheque->fecha_cheque && $cheque->fecha_cheque->gt(\Carbon\Carbon::parse($request->fecha_deposito))) {
            return back()->with('error', "El cheque N° {$cheque->numero} es post fechado: no se puede depositar antes del {$cheque->fecha_cheque->format('d/m/Y')}.");
        }

        $banco = BancoCaja::where('empresa_id', $empresaId)->findOrFail($request->banco_caja_id);

        try {
            $this->asiento->validarConfiguracion($empresaId, ['cta_cheques_cartera'], $request->fecha_deposito);
            $this->asiento->cuentaBancoElegida($banco->cuenta_id ? (int) $banco->cuenta_id : null, "depositar en «{$banco->nombre}» (sin cuenta contable asignada)");

            DB::transaction(function () use ($request, $cheque, $banco, $empresaId) {
                $mov = MovimientoBancario::create([
                    'empresa_id' => $empresaId, 'banco_caja_id' => $banco->id, 'tipo' => 'ingreso', 'sub_tipo' => 'cheque',
                    'fecha' => $request->fecha_deposito, 'monto' => $cheque->monto, 'persona_tipo' => 'cliente', 'persona_id' => $cheque->cliente_id,
                    'beneficiario' => $cheque->cliente?->razon_social, 'num_documento' => $request->referencia, 'num_cheque' => $cheque->numero,
                    'fecha_cheque' => $cheque->fecha_cheque, 'es_postfechado' => $cheque->esPostfechado(),
                    'descripcion' => "Depósito cheque N° {$cheque->numero} — " . ($cheque->factura?->numero_completo ?? 'cobro'),
                    'documento_tipo' => 'CHEQUE_RECIBIDO', 'documento_id' => $cheque->id,
                    'anulado' => false, 'conciliado' => false, 'created_by' => Auth::id(),
                ]);
                $banco->actualizarSaldo((float) $cheque->monto, 'ingreso');

                $asiento = $this->asiento->depositoChequeCartera(
                    $empresaId, $cheque->id, "CHQ-{$cheque->numero}", (float) $cheque->monto, (int) $banco->cuenta_id, $request->fecha_deposito
                );
                $mov->update(['asiento_id' => $asiento->id]);
                $cheque->update([
                    'estado' => 'depositado', 'fecha_deposito' => $request->fecha_deposito, 'banco_caja_id' => $banco->id,
                    'movimiento_id' => $mov->id, 'asiento_id' => $asiento->id,
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'No se pudo depositar el cheque: ' . $e->getMessage());
        }

        return back()->with('success', "Cheque N° {$cheque->numero} depositado en {$banco->nombre}.");
    }
}
