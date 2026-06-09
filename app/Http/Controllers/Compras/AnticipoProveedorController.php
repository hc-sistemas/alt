<?php
namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\AnticipoProveedor;
use App\Models\BancoCaja;
use App\Models\CuentaPagar;
use App\Models\Importacion;
use App\Models\MovimientoBancario;
use App\Models\Proveedor;
use App\Services\AsientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnticipoProveedorController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = AnticipoProveedor::with(['proveedor', 'importacion'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('proveedor_id')) {
            $query->where('proveedor_id', $request->proveedor_id);
        }
        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(function ($qb) use ($q) {
                $qb->whereHas('proveedor', fn($p) =>
                    $p->where('razon_social', 'ilike', "%{$q}%")
                )->orWhere('num_transferencia', 'ilike', "%{$q}%");
            });
        }

        $anticipos = $query->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get()
            ->map(fn($a) => [
                'id'               => $a->id,
                'proveedor_id'     => $a->proveedor_id,
                'proveedor'        => $a->proveedor?->razon_social,
                'importacion'      => $a->importacion?->nombre,
                'importacion_id'   => $a->importacion_id,
                'fecha'            => $a->fecha?->format('d/m/Y'),
                'monto'            => $a->monto,
                'saldo'            => $a->saldo,
                'num_transferencia'=> $a->num_transferencia,
                'estado'           => $a->estado,
                'asiento_id'       => $a->asiento_id,
            ]);

        $proveedores = Proveedor::where('empresa_id', $empresaId)
            ->activos()->orderBy('razon_social')
            ->get(['id', 'razon_social', 'tipo']);

        $importaciones = Importacion::where('empresa_id', $empresaId)
            ->whereIn('estado', ['en_transito', 'en_aduana'])
            ->orderByDesc('created_at')
            ->get(['id', 'nombre', 'estado', 'costo_fob']);

        $bancos = BancoCaja::where('empresa_id', $empresaId)
            ->activos()
            ->whereIn('tipo', ['banco', 'caja', 'caja_chica'])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo', 'saldo_actual']);

        return Inertia::render('Compras/Anticipos/Index', [
            'anticipos'     => $anticipos,
            'proveedores'   => $proveedores,
            'importaciones' => $importaciones,
            'bancos'        => $bancos,
            'filtros'       => $request->only(['estado', 'proveedor_id', 'buscar']),
            'stats' => [
                'total'      => $anticipos->count(),
                'pendientes' => $anticipos->where('estado', 'pendiente')->count(),
                'cruzados'   => $anticipos->where('estado', 'cruzado')->count(),
                'monto_total'=> (float) $anticipos->where('estado', 'pendiente')->sum('saldo'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'proveedor_id'     => 'required|exists:proveedores,id',
            'importacion_id'   => 'nullable|exists:importaciones,id',
            'fecha'            => 'required|date',
            'monto'            => 'required|numeric|min:0.01',
            'banco_id'         => 'required|exists:bancos_cajas,id',
            'num_transferencia'=> 'nullable|string|max:50',
        ], [
            'monto.min'            => 'El monto debe ser mayor a $0.',
            'banco_id.required'    => 'Selecciona el banco desde donde se pagó.',
            'proveedor_id.required'=> 'El proveedor es obligatorio.',
        ]);

        $banco = BancoCaja::findOrFail($request->banco_id);

        if ($banco->saldo_actual < $request->monto) {
            return back()->with('error',
                "Saldo insuficiente en {$banco->nombre}. " .
                'Disponible: $' . number_format((float) $banco->saldo_actual, 2)
            );
        }

        try {
            DB::transaction(function () use ($request, $empresaId, $banco) {
                $anticipo = AnticipoProveedor::create([
                    'empresa_id'       => $empresaId,
                    'proveedor_id'     => $request->proveedor_id,
                    'importacion_id'   => $request->importacion_id ?: null,
                    'fecha'            => $request->fecha,
                    'monto'            => $request->monto,
                    'saldo'            => $request->monto,
                    'banco_id'         => $request->banco_id,
                    'num_transferencia'=> $request->num_transferencia,
                    'estado'           => 'pendiente',
                    'created_at'       => now(),
                ]);

                $banco->actualizarSaldo((float) $request->monto, 'egreso');

                $proveedor = Proveedor::find($request->proveedor_id);

                MovimientoBancario::create([
                    'empresa_id'     => $empresaId,
                    'banco_caja_id'  => $banco->id,
                    'tipo'           => 'egreso',
                    'sub_tipo'       => 'transferencia',
                    'fecha'          => $request->fecha,
                    'monto'          => $request->monto,
                    'beneficiario'   => $proveedor?->razon_social,
                    'num_documento'  => $request->num_transferencia,
                    'descripcion'    => 'Anticipo proveedor — ' . ($proveedor?->razon_social ?? ''),
                    'documento_tipo' => 'ANTICIPO',
                    'documento_id'   => $anticipo->id,
                    'anulado'        => false,
                    'conciliado'     => false,
                    'created_by'     => Auth::id(),
                ]);

                try {
                    $asiento = $this->asientoService->pagoProveedor(
                        empresaId:   $empresaId,
                        documentoId: $anticipo->id,
                        referencia:  'ANT-' . str_pad($anticipo->id, 4, '0', STR_PAD_LEFT),
                        monto:       (float) $request->monto,
                    );
                    $anticipo->update(['asiento_id' => $asiento->id]);
                } catch (\Exception) {
                    // No bloquear si período cerrado
                }
            });

            return back()->with('success',
                'Anticipo de $' . number_format((float) $request->monto, 2) .
                ' registrado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cruzar(Request $request, AnticipoProveedor $anticipo): RedirectResponse
    {
        if ($anticipo->estaCruzado()) {
            return back()->with('error', 'Este anticipo ya fue cruzado.');
        }

        $request->validate([
            'compra_id' => 'required|exists:compras,id',
            'monto'     => 'required|numeric|min:0.01',
        ], [
            'compra_id.required' => 'Selecciona la factura a cruzar.',
        ]);

        if ((float) $request->monto > (float) $anticipo->saldo) {
            return back()->with('error',
                'El monto ($' . number_format((float) $request->monto, 2) .
                ') supera el saldo del anticipo ($' .
                number_format((float) $anticipo->saldo, 2) . ').'
            );
        }

        DB::transaction(function () use ($request, $anticipo) {
            $nuevoSaldo  = (float) $anticipo->saldo - (float) $request->monto;
            $nuevoEstado = $nuevoSaldo <= 0.001 ? 'cruzado' : 'pendiente';

            $anticipo->update([
                'saldo'  => max(0, $nuevoSaldo),
                'estado' => $nuevoEstado,
            ]);

            $cxp = CuentaPagar::where('compra_id', $request->compra_id)->first();

            if ($cxp) {
                $nuevoSaldoCxP = max(0, (float) $cxp->saldo - (float) $request->monto);
                $cxp->update([
                    'saldo'  => $nuevoSaldoCxP,
                    'estado' => $nuevoSaldoCxP <= 0.001 ? 'pagada' : 'parcial',
                ]);
            }

            DB::table('log_documentos')->insert([
                'usuario_id'  => Auth::id(),
                'username'    => Auth::user()?->email ?? '',
                'accion'      => 'cruzar',
                'modulo'      => 'compras',
                'tabla'       => 'anticipos_proveedores',
                'registro_id' => $anticipo->id,
                'descripcion' => "Cruce anticipo \${$request->monto} con compra ID {$request->compra_id}",
                'ip'          => $request->ip(),
                'empresa_id'  => $anticipo->empresa_id,
                'fecha'       => now(),
            ]);
        });

        return back()->with('success',
            'Anticipo cruzado correctamente por $' .
            number_format((float) $request->monto, 2) . '.');
    }

    public function anular(Request $request, AnticipoProveedor $anticipo): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:300',
        ]);

        if ($anticipo->estaCruzado()) {
            return back()->with('error', 'No se puede anular un anticipo ya cruzado.');
        }

        DB::transaction(function () use ($anticipo, $request) {
            if ($anticipo->banco_id) {
                $banco = BancoCaja::find($anticipo->banco_id);
                $banco?->actualizarSaldo((float) $anticipo->saldo, 'ingreso');
            }

            $anticipo->update(['saldo' => 0, 'estado' => 'cruzado']);

            DB::table('log_cambios_criticos')->insert([
                'usuario_id'     => Auth::id(),
                'username'       => Auth::user()?->email ?? '',
                'tabla'          => 'anticipos_proveedores',
                'registro_id'    => $anticipo->id,
                'campo'          => 'estado',
                'valor_anterior' => 'pendiente',
                'valor_nuevo'    => 'anulado',
                'motivo'         => $request->motivo,
                'ip'             => $request->ip(),
                'fecha'          => now(),
            ]);
        });

        return back()->with('success', 'Anticipo anulado y saldo revertido al banco.');
    }
}
