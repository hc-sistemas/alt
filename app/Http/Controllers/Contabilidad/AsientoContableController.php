<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\EjercicioContable;
use App\Models\Empresa;
use App\Models\PlanCuenta;
use App\Services\AsientoService;
use App\Exports\AsientosExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AsientoContableController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = AsientoContable::with(['ejercicio','creadoPor'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('buscar')) {
            $q = $request->buscar;
            $query->where(fn($qb) =>
                $qb->where('numero',         'ilike', "%{$q}%")
                   ->orWhere('concepto',     'ilike', "%{$q}%")
                   ->orWhere('documento_ref','ilike', "%{$q}%")
            );
        }
        if ($request->filled('tipo')) {
            $query->where('es_automatico', $request->tipo === 'automatico');
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado === 'activo' ? 1 : 0);
        }
        if ($request->filled('ejercicio_id')) {
            $query->where('ejercicio_id', $request->ejercicio_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $asientos   = $query->orderByDesc('created_at')->paginate(25)->withQueryString();
        $ejercicios = EjercicioContable::where('empresa_id', $empresaId)
                        ->orderByDesc('anio')->orderByDesc('mes')->get();

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id','codigo','nombre','tipo']);

        return Inertia::render('Contabilidad/Asientos/Index', [
            'asientos'      => $asientos,
            'ejercicios'    => $ejercicios,
            'cuentas'       => $cuentas,
            'periodoActivo' => $empresaId
                ? EjercicioContable::periodoActivo((int)$empresaId)
                : null,
            'filtros'       => $request->only([
                'buscar','tipo','estado','ejercicio_id','fecha_desde','fecha_hasta'
            ]),
            'stats' => [
                'total'    => AsientoContable::where('empresa_id', $empresaId)->count(),
                'activos'  => AsientoContable::where('empresa_id', $empresaId)->where('estado',1)->count(),
                'anulados' => AsientoContable::where('empresa_id', $empresaId)->where('estado',0)->count(),
                'manuales' => AsientoContable::where('empresa_id', $empresaId)->where('es_automatico',false)->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        // CORRECCIÓN 4: verificar permiso
        $perfil = Auth::user()->perfil->nombre ?? '';
        if (!in_array($perfil, ['super_admin', 'admin', 'contador'])) {
            return back()->with('error',
                'No tienes permiso para crear asientos contables.');
        }

        $request->validate([
            'concepto'               => 'required|string|max:500',
            'fecha'                  => 'required|date',
            'partidas'               => 'required|array|min:2',
            'partidas.*.cuenta_id'   => 'required|exists:plan_cuentas,id',
            'partidas.*.debe'        => 'required|numeric|min:0',
            'partidas.*.haber'       => 'required|numeric|min:0',
            'partidas.*.descripcion' => 'nullable|string|max:300',
        ], [
            'concepto.required'             => 'El concepto es obligatorio.',
            'fecha.required'                => 'La fecha es obligatoria.',
            'partidas.min'                  => 'El asiento requiere mínimo 2 partidas.',
            'partidas.*.cuenta_id.required' => 'Selecciona una cuenta en cada partida.',
        ]);

        // CORRECCIÓN 4: validar que cada partida tenga solo DEBE o solo HABER
        foreach ($request->partidas as $idx => $partida) {
            $debe  = (float)($partida['debe']  ?? 0);
            $haber = (float)($partida['haber'] ?? 0);
            if ($debe > 0 && $haber > 0) {
                return back()->with('error',
                    "Partida #" . ($idx + 1) . ": una línea no puede tener DEBE y HABER al mismo tiempo.");
            }
            if ($debe === 0.0 && $haber === 0.0) {
                return back()->with('error',
                    "Partida #" . ($idx + 1) . ": debe tener un monto en DEBE o en HABER.");
            }
        }

        try {
            $this->asientoService->crear(
                empresaId:    $empresaId,
                concepto:     $request->concepto,
                partidas:     $request->partidas,
                documentoTipo:'MANUAL',
                esAutomatico: false,
                fecha:        $request->fecha,
            );

            return back()->with('success', 'Asiento manual creado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function show(AsientoContable $asiento): Response
    {
        $asiento->load([
            'ejercicio',
            'creadoPor',
            'detalles.cuenta',
            'detalles.centroCosto',
        ]);

        return Inertia::render('Contabilidad/Asientos/Show', [
            'asiento' => $asiento,
        ]);
    }

    public function anular(Request $request, AsientoContable $asiento): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:300',
        ], [
            'motivo.required' => 'El motivo de anulación es obligatorio.',
            'motivo.min'      => 'El motivo debe tener al menos 10 caracteres.',
        ]);

        try {
            $this->asientoService->anular($asiento, $request->motivo);
            return back()->with('success',
                "Asiento {$asiento->numero} anulado. Se generó el asiento de reversión automáticamente.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // CORRECCIÓN 4: eliminar físico (solo super_admin, máx 24 h)
    public function destroy(AsientoContable $asiento): RedirectResponse
    {
        $perfil = Auth::user()->perfil->nombre ?? '';
        if ($perfil !== 'super_admin') {
            return back()->with('error',
                'Solo el Super Administrador puede eliminar asientos. Usa Anular.');
        }

        if ($asiento->estaAnulado()) {
            return back()->with('error', 'El asiento ya está anulado. No es necesario eliminarlo.');
        }

        $horasCreado = now()->diffInHours($asiento->created_at);
        if ($horasCreado > 24) {
            return back()->with('error',
                "No se puede eliminar: el asiento tiene más de 24 horas. Usa la opción Anular.");
        }

        $numero = $asiento->numero;
        $asiento->detalles()->delete();
        $asiento->delete();

        return redirect()->route('contabilidad.asientos.index')
            ->with('success', "Asiento {$numero} eliminado permanentemente.");
    }

    // CORRECCIÓN 5: exportar a Excel
    public function exportarExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $empresaId = session('empresa_activa_id');
        return Excel::download(
            new AsientosExport((int)$empresaId, $request->only([
                'ejercicio_id','fecha_desde','fecha_hasta'
            ])),
            'asientos-' . now()->format('Y-m-d') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function reportePdf(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');

        $query = AsientoContable::with(['ejercicio', 'creadoPor', 'detalles.cuenta'])
            ->where('empresa_id', $empresaId);

        if ($request->filled('ejercicio_id')) {
            $query->where('ejercicio_id', $request->ejercicio_id);
        }
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }
        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        $asientos = $query->orderByDesc('fecha')->get();
        $empresa  = Empresa::find($empresaId);

        $totalDebe  = $asientos->sum('total_debe');
        $totalHaber = $asientos->sum('total_haber');

        $pdf = Pdf::loadView(
            'pdf.asientos-reporte',
            compact('asientos', 'empresa', 'totalDebe', 'totalHaber')
        )->setPaper('a4', 'landscape');

        return $pdf->stream('reporte-asientos-' . now()->format('Y-m-d') . '.pdf');
    }

    // CORRECCIÓN 5: imprimir PDF individual
    public function imprimirPdf(AsientoContable $asiento): \Illuminate\Http\Response
    {
        $asiento->load(['ejercicio','creadoPor','detalles.cuenta','detalles.centroCosto']);
        $empresa = Empresa::find(session('empresa_activa_id'));

        $pdf = Pdf::loadView('pdf.asiento', compact('asiento', 'empresa'))
                  ->setPaper('a4', 'portrait');

        return $pdf->stream("asiento-{$asiento->numero}.pdf");
    }
}
