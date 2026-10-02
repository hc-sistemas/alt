<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\GuiaRemision;
use App\Models\GuiaRemisionDetalle;
use App\Models\Transportista;
use App\Services\AuditoriaService;
use App\Services\SecuencialService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GuiaRemisionController extends Controller
{
    public function __construct(
        private AuditoriaService  $auditoria,
        private SecuencialService $secuencial,
    ) {}

    public function index(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        // Primera visita: fechas por defecto = hoy (si se borran, llegan vacías y no se fuerzan)
        $hoy = now()->toDateString();
        $request->merge([
            'fecha_desde' => $request->has('fecha_desde') ? $request->fecha_desde : $hoy,
            'fecha_hasta' => $request->has('fecha_hasta') ? $request->fecha_hasta : $hoy,
        ]);

        $query = GuiaRemision::with(['factura', 'transportista'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_emision')
            ->orderByDesc('id');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha_emision', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha_emision', '<=', $request->fecha_hasta);
        }

        $guias = $query->paginate(25)->withQueryString();

        return Inertia::render('Ventas/GuiasRemision/Index', [
            'guias'  => $guias,
            'filtros'=> $request->only(['estado', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $transportistas = Transportista::where('estado', true)
            ->orderBy('razon_social')
            ->get();

        $factura = null;
        if ($request->filled('factura_id')) {
            $factura = Factura::with(['cliente', 'detalles.producto'])
                ->where('empresa_id', $empresaId)
                ->where('estado', 'activa')
                ->find($request->factura_id);
        }

        return Inertia::render('Ventas/GuiasRemision/Form', [
            'transportistas' => $transportistas,
            'factura'        => $factura,
        ]);
    }

    public function store(Request $request)
    {
        $empresaId = session('empresa_activa_id');

        $request->validate([
            // Antes sin validar del todo: se guardaba el factura_id tal cual
            // llegara, sin comprobar que existiera ni que la factura siguiera
            // activa (CHECKLIST_ERRORES_COMPLICACIONES.md, "NC/Retención/Guía
            // sin revalidar estado"). La guía puede seguir emitiéndose sin
            // factura (transporte de mercadería que no es una venta).
            'factura_id'              => 'nullable|integer|exists:facturas,id',
            'transportista_id'        => 'required|integer|exists:transportistas,id',
            'direccion_partida'       => 'required|string|max:300',
            'direccion_destino'       => 'required|string|max:300',
            'fecha_ini_transporte'    => 'required|date',
            'fecha_fin_transporte'    => 'required|date|after_or_equal:fecha_ini_transporte',
            'detalles'                => 'required|array|min:1',
            'detalles.*.descripcion'  => 'required|string',
            'detalles.*.cantidad'     => 'required|numeric|min:0.01',
            'detalles.*.unidad'       => 'required|string',
        ]);

        if ($request->filled('factura_id')) {
            $facturaEstado = Factura::where('id', $request->factura_id)
                ->where('empresa_id', $empresaId)
                ->value('estado');

            if ($facturaEstado !== 'activa') {
                return back()->withErrors(['error' => 'No se puede generar una guía de remisión sobre una factura anulada.'])->withInput();
            }
        }

        $guia = DB::transaction(function () use ($request, $empresaId) {
            $numero = $this->secuencial->siguiente($empresaId, 'GR');
            [$est, $pe, $sec] = explode('-', $numero);

            $guia = GuiaRemision::create([
                'empresa_id'              => $empresaId,
                'factura_id'              => $request->factura_id,
                'transportista_id'        => $request->transportista_id,
                'numero_completo'         => $numero,
                'establecimiento'         => $est,
                'punto_emision'           => $pe,
                'secuencial'              => ltrim($sec, '0') ?: '1',
                'fecha_emision'           => now()->toDateString(),
                'origen'                  => $request->direccion_partida,
                'destino'                 => $request->direccion_destino,
                'fecha_inicio_transporte' => $request->fecha_ini_transporte,
                'fecha_fin_transporte'    => $request->fecha_fin_transporte,
                'motivo'                  => $request->observaciones,
                'estado_sri'              => 'pendiente',
                'estado'                  => 'activa',
            ]);

            foreach ($request->detalles as $det) {
                GuiaRemisionDetalle::create([
                    'guia_id'     => $guia->id,
                    'producto_id' => $det['producto_id'] ?? null,
                    'descripcion' => $det['descripcion'],
                    'cantidad'    => $det['cantidad'],
                ]);
            }

            return $guia;
        });

        $this->auditoria->documento('crear', 'ventas', 'guias_remision', $guia->id, "Guía de remisión {$guia->numero} creada");

        return redirect()->route('ventas.guias-remision.show', $guia->id)
            ->with('flash', ['tipo' => 'exito', 'mensaje' => "Guía de remisión {$guia->numero} creada correctamente."]);
    }

    public function show(GuiaRemision $guiaRemision)
    {
        $guiaRemision->load(['factura', 'transportista', 'usuario', 'detalles', 'empresa']);

        return Inertia::render('Ventas/GuiasRemision/Show', [
            'guia' => $guiaRemision,
        ]);
    }

    public function enviarSri(GuiaRemision $guiaRemision)
    {
        // TODO: implementar ciclo SRI (XML + firma + webservice)
        // Pendiente — commit separado
        return response()->json(['message' => 'Funcionalidad SRI pendiente']);
    }
}
