<?php
namespace App\Http\Controllers\Contabilidad;

use App\Http\Controllers\Controller;
use App\Models\AsientoContable;
use App\Models\CentroCosto;
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

        // Carga bajo demanda: con miles de asientos en producción, la query pesada
        // solo se ejecuta cuando el usuario dispara una búsqueda explícita (botón
        // lupa en el FilterToolbar), nunca en la carga inicial de la página.
        $asientos = null;

        if ($request->boolean('buscado')) {
            $query = AsientoContable::with(['ejercicio','creadoPor'])
                ->where('empresa_id', $empresaId);

            if ($request->filled('buscar')) {
                $q = trim($request->buscar);
                // Número, concepto, documento de respaldo, cuenta (código o nombre) y monto
                $monto = is_numeric(str_replace(',', '.', $q)) ? (float) str_replace(',', '.', $q) : null;
                $query->where(function ($qb) use ($q, $monto) {
                    $qb->where('numero',          'ilike', "%{$q}%")
                       ->orWhere('concepto',      'ilike', "%{$q}%")
                       ->orWhere('documento_ref', 'ilike', "%{$q}%")
                       ->orWhereHas('detalles.cuenta', fn ($c) => $c->where('codigo', 'ilike', "{$q}%")->orWhere('nombre', 'ilike', "%{$q}%"));
                    if ($monto !== null) {
                        $qb->orWhere('total_debe', $monto)
                           ->orWhereHas('detalles', fn ($d) => $d->where('debe', $monto)->orWhere('haber', $monto));
                    }
                });
            }
            if ($request->filled('diario')) {
                $query->diario($request->diario);
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

            $asientos = $query->orderByDesc('created_at')->paginate(25)->withQueryString();
        }

        $ejercicios = EjercicioContable::where('empresa_id', $empresaId)
                        ->orderByDesc('anio')->orderByDesc('mes')->get();

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->get(['id','codigo','nombre','tipo']);

        $centros = CentroCosto::where('empresa_id', $empresaId)
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('Contabilidad/Asientos/Index', [
            'asientos'      => $asientos,
            'ejercicios'    => $ejercicios,
            'cuentas'       => $cuentas,
            'centros'       => $centros,
            'periodoActivo' => $empresaId
                ? EjercicioContable::periodoActivo((int)$empresaId)
                : null,
            'filtros'       => $request->only([
                'buscar','tipo','estado','diario','ejercicio_id','fecha_desde','fecha_hasta'
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        // La autorización la resuelve el middleware `permiso:contabilidad,crear`
        // de la ruta. El chequeo por nombre de perfil que había aquí duplicaba
        // ese control con otro criterio distinto, así que la matriz de permisos
        // de Configuración no servía para nada en esta pantalla: un perfil con
        // el permiso concedido igual recibía "No tienes permiso".

        $request->validate([
            'concepto'               => 'required|string|max:500',
            // Documento de respaldo: número de factura, comprobante o recibo (02-08 §2.1)
            'documento_ref'          => 'required|string|max:100',
            'confirmar_naturaleza'   => 'nullable|boolean',
            // Un asiento no puede tener fecha futura: contabilizaría un hecho
            // económico que todavía no ocurrió y descuadraría cualquier corte.
            'fecha'                  => 'required|date|before_or_equal:today',
            'partidas'               => 'required|array|min:2',
            'partidas.*.cuenta_id'       => 'required|exists:plan_cuentas,id',
            'partidas.*.centro_costo_id' => 'nullable|integer|exists_empresa:centros_costo',
            'partidas.*.debe'            => 'required|numeric|min:0',
            'partidas.*.haber'           => 'required|numeric|min:0',
            'partidas.*.descripcion'     => 'nullable|string|max:300',
        ], [
            'concepto.required'             => 'El concepto es obligatorio.',
            'documento_ref.required'        => 'El documento de respaldo es obligatorio (número de factura, comprobante o recibo).',
            'fecha.required'                => 'La fecha es obligatoria.',
            'fecha.before_or_equal'         => 'No se pueden registrar asientos con fecha futura.',
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

        // Naturaleza invertida (PC-GEN-04): activos y gastos aumentan por el debe; pasivos, patrimonio e ingresos
        // por el haber. Si una línea va al lado contrario, se advierte y se pide confirmación. Las cuentas
        // regularizadoras «(-)» van al lado contrario por definición y no se advierten.
        if (!$request->boolean('confirmar_naturaleza')) {
            $cuentas = PlanCuenta::whereIn('id', collect($request->partidas)->pluck('cuenta_id'))->get()->keyBy('id');
            $invertidas = [];
            foreach ($request->partidas as $idx => $p) {
                $c = $cuentas->get($p['cuenta_id']);
                if (!$c || str_starts_with(trim($c->nombre), '(-)')) {
                    continue;
                }
                $debe  = (float) ($p['debe']  ?? 0);
                $haber = (float) ($p['haber'] ?? 0);
                $naturalDebe = in_array($c->tipo, ['activo', 'gasto'], true);
                if (($naturalDebe && $haber > 0) || (!$naturalDebe && $debe > 0)) {
                    $invertidas[] = "{$c->codigo} {$c->nombre} (" . ($haber > 0 ? 'haber' : 'debe') . ')';
                }
            }
            if ($invertidas) {
                return back()->withErrors([
                    'naturaleza_invertida' => 'Estas cuentas van al lado contrario de su naturaleza: ' . implode('; ', $invertidas) . '.',
                ]);
            }
        }

        try {
            $this->asientoService->crear(
                empresaId:    $empresaId,
                concepto:     $request->concepto,
                partidas:     $request->partidas,
                documentoTipo:'MANUAL',
                documentoRef: $request->documento_ref,
                esAutomatico: false,
                fecha:        $request->fecha,
            );

            return back()->with('success', 'Asiento manual creado correctamente.');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * El route-model binding resuelve el asiento por id sin mirar la empresa,
     * y AsientoContable no tiene global scope. Con Matriz activa se podía
     * abrir, imprimir, anular y ELIMINAR asientos de Altamira Import poniendo
     * el id en la URL. Todas las acciones que reciben un {asiento} pasan por
     * aquí.
     */
    private function verificarEmpresa(AsientoContable $asiento): void
    {
        if ((int) $asiento->empresa_id !== (int) session('empresa_activa_id')) {
            abort(404);
        }
    }

    public function show(AsientoContable $asiento): Response
    {
        $this->verificarEmpresa($asiento);

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
        $this->verificarEmpresa($asiento);

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

    /**
     * Los asientos no se borran (02-08 §2.1): se anulan con reverso y motivo, y quedan siempre en el libro.
     * Se conserva la ruta para no romper accesos viejos, pero ya no elimina nada.
     */
    public function destroy(AsientoContable $asiento): RedirectResponse
    {
        $this->verificarEmpresa($asiento);

        return back()->with('error',
            'Los asientos no se borran: use Anular, que genera el reverso y guarda el motivo.');
    }

    public function exportarExcel(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $empresaId = session('empresa_activa_id');

        $filtros = $request->only([
            'ejercicio_id', 'fecha_desde', 'fecha_hasta', 'tipo', 'estado',
        ]);

        // Sin filtro genera el histórico completo (~11 mil asientos / ~28 mil
        // líneas de detalle) — memory_limit elevado con el mismo margen que
        // antes corría en el worker de colas (ver max_execution_time también
        // subido para esta ruta en config/php.ini o el servidor web).
        ini_set('memory_limit', '1536M');

        $asientosExport = new AsientosExport((int)$empresaId, $filtros);

        return Excel::download(
            $asientosExport,
            'asientos-' . now()->format('Y-m-d') . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    public function reportePdf(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $empresaId = session('empresa_activa_id');

        $filtros = $request->only([
            'ejercicio_id', 'fecha_desde', 'fecha_hasta', 'tipo', 'estado',
        ]);

        // DomPDF (Cellmap::resolve_border) necesita muchísima más memoria por
        // fila que PhpSpreadsheet (exportarExcel() funciona con 1536M incluso
        // sin filtro, 11,177 asientos) — probado con datos reales: 1536M
        // revienta con un rango de apenas 6 meses (~2,100 asientos). 4096M
        // da margen para rangos grandes reales; el histórico completo sin
        // ningún filtro puede seguir sin alcanzar (mismo límite de DomPDF que
        // Mayor Contable — ver CLAUDE.md).
        ini_set('memory_limit', '4096M');

        // pdf.asientos-reporte solo usa $asiento->ejercicio (un renglón por
        // asiento, no por línea de detalle) — eager-cargar creadoPor/
        // detalles.cuenta aquí no se usa nunca en la vista y solo infla memoria
        // sin necesidad (encontrado al probar ExportarAsientosJob con ~1,100
        // asientos: dompdf agotó los 512MB del límite de memoria de PHP
        // procesando relaciones que la vista ni toca).
        $query = AsientoContable::with(['ejercicio'])
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
        if ($request->filled('tipo')) {
            $query->where('es_automatico', $request->tipo === 'automatico');
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado === 'activo' ? 1 : 0);
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

    // Este Libro Diario estaba duplicado carácter por carácter con el de
    // ReporteContableController, pero solo aquel imprime el período que cubre
    // y eleva el memory_limit. Se delega para que exista una sola versión.
    public function libroDiario(Request $request): \Illuminate\Http\Response
    {
        return app(ReporteContableController::class)->libroDiario($request);
    }

    public function mayorCuenta(Request $request): \Illuminate\Http\Response
    {
        $empresaId = session('empresa_activa_id');
        $request->validate(['cuenta_id' => 'required|exists:plan_cuentas,id']);

        // Se delega en ReporteContableController::mayor(), que es la
        // implementación buena y única.
        //
        // La copia que vivía aquí traía TODOS los detalles de la cuenta a
        // memoria y recién después filtraba las fechas con Collection::filter()
        // en PHP (en la cuenta más activa son 6.218 filas para imprimir un mes),
        // y además le faltaban el saldo anterior y el saldo corrido que sí tiene
        // la otra. Eran dos "Mayor Contable" distintos según desde qué pantalla
        // se entrara.
        return app(ReporteContableController::class)->mayor($request);
    }

    // CORRECCIÓN 5: imprimir PDF individual
    public function imprimirPdf(AsientoContable $asiento): \Illuminate\Http\Response
    {
        $this->verificarEmpresa($asiento);

        $asiento->load(['ejercicio','creadoPor','detalles.cuenta','detalles.centroCosto']);
        $empresa = Empresa::find(session('empresa_activa_id'));

        $pdf = Pdf::loadView('pdf.asiento', compact('asiento', 'empresa'))
                  ->setPaper('a4', 'portrait');

        return $pdf->stream("asiento-{$asiento->numero}.pdf");
    }
}
