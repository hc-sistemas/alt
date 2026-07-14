<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\TallerEquipo;
use App\Models\TallerIngreso;
use App\Models\TallerOrdenTrabajo;
use App\Models\TallerTipoEquipo;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class IngresoController extends Controller
{
    public function __construct(private AuditoriaService $auditoria) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = TallerIngreso::with(['cliente', 'equipo', 'ordenesTrabajo'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->whereHas('cliente', fn($q) => $q
                ->where('razon_social', 'ilike', "%{$request->search}%")
                ->orWhere('identificacion', 'ilike', "%{$request->search}%"));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        return Inertia::render('Taller/Ingresos/Index', [
            'ingresos' => $query->paginate(15)->withQueryString(),
            'filtros'  => $request->only(['search', 'estado']),
        ]);
    }

    public function create(): Response
    {
        $empresaId = session('empresa_activa_id');

        $clientes = Cliente::where('empresa_id', $empresaId)
            ->select('id', 'identificacion', 'razon_social', 'tipo_identificacion', 'email', 'telefono', 'direccion', 'ciudad')
            ->orderBy('razon_social')
            ->get();

        $tiposEquipo = TallerTipoEquipo::where('estado', true)
            ->select('id', 'descripcion')
            ->orderBy('descripcion')
            ->get();

        return Inertia::render('Taller/Ingresos/Form', [
            'clientes'       => $clientes,
            'tiposEquipo'    => $tiposEquipo,
            'empresa_activa' => Empresa::findOrFail($empresaId),
        ]);
    }

    public function buscarEquipo(Request $request): JsonResponse
    {
        $serie = trim($request->get('serie', ''));
        if (!$serie) {
            return response()->json(null);
        }

        $equipo = TallerEquipo::with('tipo')
            ->where('numero_serie', $serie)
            ->first();

        return response()->json(['found' => (bool)$equipo, 'equipo' => $equipo]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'cliente_id'                     => 'required|integer|exists:clientes,id',
            'equipo.equipo_id'               => 'nullable|integer|exists:taller_equipos,id',
            'equipo.tipo_id'                 => 'nullable|integer|exists:taller_tipos_equipo,id',
            'equipo.marca'                   => 'nullable|string|max:100',
            'equipo.modelo'                  => 'nullable|string|max:100',
            'equipo.numero_serie'            => 'nullable|string|max:100',
            'equipo.color'                   => 'nullable|string|max:50',
            'equipo.medida'                  => 'nullable|string|max:50',
            'equipo.adicional'               => 'nullable|string|max:200',
            'equipo.observaciones'           => 'nullable|string',
            'diagnostico_inicial'            => 'required|string',
            'observaciones'                  => 'nullable|string',
            'imagen'                         => 'nullable|string',
        ]);

        $ingreso = DB::transaction(function () use ($data, $empresaId) {
            $equipoData = $data['equipo'] ?? [];

            $equipo = null;
            if (!empty($equipoData['numero_serie'])) {
                $equipo = TallerEquipo::where('numero_serie', $equipoData['numero_serie'])->first();
            }

            if (!$equipo) {
                $equipo = TallerEquipo::create([
                    'tipo_id'       => $equipoData['tipo_id'] ?? null,
                    'marca'         => $equipoData['marca'] ?? null,
                    'modelo'        => $equipoData['modelo'] ?? null,
                    'numero_serie'  => $equipoData['numero_serie'] ?? null,
                    'color'         => $equipoData['color'] ?? null,
                    'medida'        => $equipoData['medida'] ?? null,
                    'adicional'     => $equipoData['adicional'] ?? null,
                    'observaciones' => $equipoData['observaciones'] ?? null,
                ]);
            }

            $ingreso = TallerIngreso::create([
                'empresa_id'          => $empresaId,
                'cliente_id'          => $data['cliente_id'],
                'equipo_id'           => $equipo->id,
                'usuario_id'          => Auth::id(),
                'fecha'               => now()->toDateString(),
                'hora'                => now()->toTimeString(),
                'diagnostico_inicial' => $data['diagnostico_inicial'] ?? null,
                'observaciones'       => $data['observaciones'] ?? null,
                'imagen'              => $data['imagen'] ?? null,
                'estado'              => 0,
            ]);

            TallerOrdenTrabajo::create([
                'empresa_id'   => $empresaId,
                'ingreso_id'   => $ingreso->id,
                'numero'       => sprintf('OT-%s-%06d', now()->year, $ingreso->id),
                'fecha_inicio' => now()->toDateString(),
                'hora_inicio'  => now()->toTimeString(),
                'tipo_orden'   => 1,
                'estado'       => 'pendiente',
            ]);

            return $ingreso;
        });

        $this->auditoria->documento('crear', 'taller', 'ingresos', $ingreso->id, "Ingreso de taller #{$ingreso->id} creado");

        return redirect()->route('taller.ingresos.show', $ingreso->id)
            ->with('success', 'Ingreso registrado correctamente.');
    }

    public function show(TallerIngreso $ingreso): Response
    {
        abort_if((int) $ingreso->empresa_id !== (int) session('empresa_activa_id'), 403);

        $ingreso->load(['cliente', 'equipo.tipo', 'usuario', 'ordenesTrabajo']);

        return Inertia::render('Taller/Ingresos/Show', [
            'ingreso' => $ingreso,
        ]);
    }

    public function pdfOrdenTrabajo(Request $request, TallerIngreso $ingreso): HttpResponse
    {
        $ingreso->load(['cliente', 'equipo.tipo', 'ordenesTrabajo.tecnico']);

        // Hoy siempre hay exactamente una OT por ingreso (se crean juntas en
        // store()), pero el modelo permite varias a futuro — se imprime la
        // más reciente.
        $ot = $ingreso->ordenesTrabajo->sortByDesc('id')->first();

        // Nota: no se habilita isRemoteEnabled — el campo imagen del ingreso
        // es una URL libre sin validar, y activar fetch remoto en dompdf
        // sería una superficie de SSRF innecesaria (nadie lo pidió y hoy
        // ningún registro usa ese campo). El logo se sirve desde disco local,
        // que dompdf sí puede leer sin esa opción.
        $pdf = Pdf::loadView('pdf.taller.orden-trabajo', [
            'ingreso'  => $ingreso,
            'ot'       => $ot,
            'logoPath' => public_path('images/logo-altamira.png'),
        ])->setPaper('a4', 'portrait');

        $nombreArchivo = 'orden-trabajo-' . ($ot?->id ?? $ingreso->id) . '.pdf';

        return $request->boolean('download')
            ? $pdf->download($nombreArchivo)
            : $pdf->stream($nombreArchivo);
    }
}
