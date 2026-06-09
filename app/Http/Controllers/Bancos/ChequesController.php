<?php
namespace App\Http\Controllers\Bancos;

use App\Http\Controllers\Controller;
use App\Models\Cheque;
use App\Models\BancoCaja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ChequesController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

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

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()->bancos()->orderBy('nombre')
            ->get(['id','nombre','num_cuenta']);

        return Inertia::render('Bancos/Cheques/Index', [
            'cheques'  => $cheques,
            'bancos'   => $bancos,
            'filtros'  => $request->only(['estado','banco_caja_id','buscar']),
            'stats'    => [
                'total'       => $cheques->count(),
                'emitidos'    => $cheques->where('estado','emitido')->count(),
                'cobrados'    => $cheques->where('estado','cobrado')->count(),
                'protestados' => $cheques->where('estado','protestado')->count(),
                'monto_total' => $cheques->where('estado','emitido')->sum('monto'),
            ],
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

        Cheque::create([
            'empresa_id'    => $empresaId,
            'banco_caja_id' => $request->banco_caja_id,
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

        return back()->with('success',
            "Cheque N° {$request->numero} registrado correctamente.");
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

        $cheque->update([
            'estado'      => $request->estado,
            'fecha_cobro' => $request->fecha_cobro ?? now()->toDateString(),
            'observacion' => $request->observacion,
        ]);

        if ($request->estado === 'protestado') {
            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'username'       => Auth::user()?->email ?? '',
                'tabla'          => 'cheques',
                'registro_id'    => $cheque->id,
                'campo'          => 'estado',
                'valor_anterior' => 'emitido',
                'valor_nuevo'    => 'protestado',
                'motivo'         => $request->observacion ?? 'Cheque protestado',
                'ip'             => $request->ip(),
                'fecha'          => now(),
            ]);
        }

        $mensajes = [
            'cobrado'    => "Cheque N° {$cheque->numero} marcado como cobrado.",
            'protestado' => "Cheque N° {$cheque->numero} marcado como protestado. Registra el cargo bancario.",
            'anulado'    => "Cheque N° {$cheque->numero} anulado.",
        ];

        return back()->with(
            $request->estado === 'protestado' ? 'warning' : 'success',
            $mensajes[$request->estado]
        );
    }
}
