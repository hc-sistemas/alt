<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\Retencion;
use App\Models\RetencionDetalle;
use App\Services\AuditoriaService;
use App\Services\SecuencialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class RetencionController extends Controller
{
    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $query = Retencion::with(['factura', 'cliente'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('cliente')) {
            $query->whereHas('cliente', fn($q) => $q
                ->where('razon_social', 'ilike', "%{$request->cliente}%")
                ->orWhere('identificacion', 'ilike', "%{$request->cliente}%"));
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $retenciones = $query->paginate(25)->withQueryString();

        return Inertia::render('Ventas/Retenciones/Index', [
            'retenciones' => $retenciones,
            'filtros'     => $request->only(['estado', 'cliente', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:facturas,id',
        ]);

        $factura = Factura::with(['cliente.empresa', 'detalles'])
            ->findOrFail($request->factura_id);

        // Solo para facturas de clientes con agente_retencion = true
        if (!$factura->cliente?->empresa?->agente_retencion) {
            return back()->withErrors(['error' => 'El cliente no es agente de retención.']);
        }

        return Inertia::render('Ventas/Retenciones/Form', [
            'factura' => $factura->load('cliente'),
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            'factura_id'             => 'required|integer|exists:facturas,id',
            'detalles'               => 'required|array|min:1',
            'detalles.*.codigo'      => 'required|string',
            'detalles.*.descripcion' => 'required|string',
            'detalles.*.base'        => 'required|numeric|min:0',
            'detalles.*.porcentaje'  => 'required|numeric|min:0',
        ]);

        $factura = Factura::findOrFail($request->factura_id);
        $total   = collect($request->detalles)->sum(fn($d) => $d['base'] * $d['porcentaje'] / 100);

        $retencion = DB::transaction(function () use ($request, $empresaId, $factura, $total) {
            $numero = $this->secuencial->siguiente($empresaId, 'RET');

            $retencion = Retencion::create([
                'empresa_id'   => $empresaId,
                'factura_id'   => $factura->id,
                'cliente_id'   => $factura->cliente_id,
                'usuario_id'   => Auth::id(),
                'numero'       => $numero,
                'fecha_emision'=> now()->toDateString(),
                'total'        => $total,
                'estado_sri'   => 'pendiente',
                'estado'       => 'activa',
            ]);

            foreach ($request->detalles as $det) {
                RetencionDetalle::create([
                    'retencion_id' => $retencion->id,
                    'codigo'       => $det['codigo'],
                    'descripcion'  => $det['descripcion'],
                    'base'         => $det['base'],
                    'porcentaje'   => $det['porcentaje'],
                    'valor'        => $det['base'] * $det['porcentaje'] / 100,
                ]);
            }

            return $retencion;
        });

        $this->auditoria->documento('crear', 'ventas', 'retenciones', $retencion->id, "Retención {$retencion->numero} creada");

        return redirect()->route('ventas.retenciones.show', $retencion->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Retención {$retencion->numero} creada correctamente."]);
    }

    public function show(Retencion $retencion)
    {
        $retencion->load(['factura', 'cliente', 'usuario', 'detalles', 'empresa']);

        return Inertia::render('Ventas/Retenciones/Show', [
            'retencion' => $retencion,
        ]);
    }

    public function enviarSri(Retencion $retencion)
    {
        // TODO: implementar ciclo SRI (XML + firma + webservice)
        // Pendiente — commit separado
        return response()->json(['message' => 'Funcionalidad SRI pendiente']);
    }
}
