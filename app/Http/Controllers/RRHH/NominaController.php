<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Jobs\ExportarNominaZipJob;
use App\Models\Asistencia;
use App\Models\Colaborador;
use App\Models\Nomina;
use App\Models\NominaDetalle;
use App\Models\PrestamoEmpleado;
use App\Models\HorasExtrasAprobacion;
use App\Services\AsientoService;
use App\Services\NominaCalculoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NominaController extends Controller
{
    public function __construct(
        private AsientoService $asientoService,
        private NominaCalculoService $nominaCalculoService,
    ) {}

    // ── Listado de nóminas ────────────────────────────────────────────────────

    private const MESES_ES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
        5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
        9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $nominas = null;

        if ($request->boolean('buscado')) {
            $query = Nomina::where('empresa_id', $empresaId)
                ->with(['generadoPor:id,nombre', 'procesadoPor:id,nombre', 'pagadoPor:id,nombre'])
                ->withCount('detalles');

            if ($request->filled('anio')) {
                $query->where('anio', $request->anio);
            }
            if ($request->filled('mes')) {
                $query->where('mes', $request->mes);
            }
            if ($request->filled('tipo')) {
                $query->where('periodo_tipo', $request->tipo);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('buscar')) {
                // No hay un campo de texto libre en "nominas" (es un período, no un
                // documento con nombre) — se busca por año exacto, nombre del mes en
                // español, o tipo/estado, para que la lupa tenga sentido con un dato
                // real que el usuario reconozca (ej. escribir "agosto" o "2026").
                $q = mb_strtolower(trim($request->buscar));
                $mesMatch = array_search($q, self::MESES_ES, true);
                if ($mesMatch === false) {
                    $mesMatch = collect(self::MESES_ES)->search(fn($nombre) => str_contains($nombre, $q));
                    $mesMatch = $mesMatch === false ? null : $mesMatch;
                }
                $query->where(function ($qb) use ($q, $mesMatch) {
                    if (is_numeric($q)) {
                        $qb->orWhere('anio', $q);
                    }
                    if ($mesMatch) {
                        $qb->orWhere('mes', $mesMatch);
                    }
                    $qb->orWhere('periodo_tipo', 'ilike', "%{$q}%")
                       ->orWhere('estado', 'ilike', "%{$q}%");
                });
            }

            $nominas = $query->orderByDesc('anio')->orderByDesc('mes')
                ->orderByDesc('quincena')->orderByDesc('id')
                ->paginate(20)->withQueryString();
        }

        return Inertia::render('RRHH/Nomina/Index', [
            'nominas' => $nominas,
            'filtros' => $request->only(['anio', 'mes', 'tipo', 'estado', 'buscar']),
            'anios'   => range(now()->year, now()->year - 3),
        ]);
    }

    // ── Generar nómina en borrador ────────────────────────────────────────────

    public function generar(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'periodo_tipo' => 'required|in:mensual,quincenal',
            'anio'         => 'required|integer|min:2020|max:2099',
            'mes'          => 'required|integer|min:1|max:12',
            'quincena'     => 'nullable|integer|in:1,2',
        ]);

        if ($data['periodo_tipo'] === 'quincenal' && empty($data['quincena'])) {
            return back()->with('error', 'Seleccione 1ª o 2ª quincena.');
        }

        // Verificar que no exista una nómina para el mismo período
        $existe = Nomina::where('empresa_id', $empresaId)
            ->where('anio', $data['anio'])
            ->where('mes', $data['mes'])
            ->where('periodo_tipo', $data['periodo_tipo'])
            ->when($data['periodo_tipo'] === 'quincenal',
                fn($q) => $q->where('quincena', $data['quincena']))
            ->exists();

        if ($existe) {
            $q = $data['periodo_tipo'] === 'quincenal' ? " ({$data['quincena']}ª quincena)" : '';
            return back()->with('error', "Ya existe una nómina para {$data['mes']}/{$data['anio']}{$q}.");
        }

        $colaboradores = Colaborador::where('empresa_id', $empresaId)
            ->activos()->orderBy('apellidos')->orderBy('nombres')
            ->get();

        if ($colaboradores->isEmpty()) {
            return back()->with('error', 'No hay colaboradores activos en esta empresa.');
        }

        try {
            DB::transaction(function () use ($data, $empresaId, $colaboradores) {
                $nomina = Nomina::create([
                    'empresa_id'   => $empresaId,
                    'periodo_tipo' => $data['periodo_tipo'],
                    'anio'         => $data['anio'],
                    'mes'          => $data['mes'],
                    'quincena'     => $data['quincena'] ?? null,
                    'fecha_emision'=> now()->toDateString(),
                    'estado'       => 'borrador',
                    'generado_por' => Auth::id(),
                    'created_at'   => now(),
                ]);

                $totalIngresos = 0;
                $totalEgresos  = 0;

                foreach ($colaboradores as $col) {
                    $detalle = $this->nominaCalculoService->calcularDetalle($col, $data, $nomina->id);
                    NominaDetalle::create($detalle);
                    $totalIngresos += (float)$detalle['total_ingresos'];
                    $totalEgresos  += (float)$detalle['total_egresos'];
                }

                $nomina->update([
                    'total_ingresos' => round($totalIngresos, 2),
                    'total_egresos'  => round($totalEgresos, 2),
                    'total_neto'     => round($totalIngresos - $totalEgresos, 2),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al generar nómina: ' . $e->getMessage());
        }

        return redirect()->route('rrhh.nomina.index')
            ->with('success', 'Nómina generada en borrador correctamente.');
    }

    // ── Detalle de una nómina ─────────────────────────────────────────────────

    public function show(int $id): Response
    {
        $empresaId = session('empresa_activa_id');

        $nomina = Nomina::where('empresa_id', $empresaId)
            ->with([
                'detalles.colaborador:id,apellidos,nombres,cedula_ruc,cargo,banco,tipo_cuenta,numero_cuenta,sueldo_base',
                'generadoPor:id,nombre',
                'procesadoPor:id,nombre',
                'pagadoPor:id,nombre',
            ])
            ->findOrFail($id);

        return Inertia::render('RRHH/Nomina/Show', [
            'nomina' => $nomina->append('periodo_label'),
        ]);
    }

    // ── Edición manual de una fila ────────────────────────────────────────────

    // Edición manual de contingencia (faltas, atrasos, comisiones puntuales,
    // egresos no asignados): el cliente pide que SOLO Contador o Súper
    // Administrador puedan hacerlo, más estricto que el permiso genérico
    // "rrhh,editar" del middleware de la ruta (ese permiso también lo puede
    // tener, por ejemplo, un perfil de RRHH sin ser Contador ni Súper Admin —
    // ver PERFILES_ACCESO en ColaboradorController). Se valida el rol real
    // aquí, no solo ocultando el botón en el frontend.
    private const PERFILES_EDICION_MANUAL = ['super_admin', 'contador'];

    public function update(Request $request, int $id, int $did): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        if (!in_array(Auth::user()?->perfil?->nombre, self::PERFILES_EDICION_MANUAL, true)) {
            throw ValidationException::withMessages([
                'error' => 'Solo un Contador o Súper Administrador puede editar manualmente un rol de pago.',
            ]);
        }

        $nomina  = Nomina::where('empresa_id', $empresaId)->findOrFail($id);
        $detalle = NominaDetalle::where('nomina_id', $nomina->id)->findOrFail($did);

        if ($nomina->estado !== 'borrador') {
            throw ValidationException::withMessages([
                'error' => 'Solo se puede editar nóminas en borrador.',
            ]);
        }

        $data = $request->validate([
            'sueldo_base'          => 'required|numeric|min:0',
            'horas_extras_50'      => 'required|numeric|min:0',
            'horas_extras_100'     => 'required|numeric|min:0',
            'comisiones'           => 'required|numeric|min:0',
            'otros_ingresos'       => 'required|numeric|min:0',
            'aporte_personal_iess' => 'required|numeric|min:0',
            'descuento_atrasos'    => 'required|numeric|min:0',
            'descuento_prestamos'  => 'required|numeric|min:0',
            'descuento_anticipos'  => 'required|numeric|min:0',
            'otros_egresos'        => 'required|numeric|min:0',
            'tipo_pago'            => 'nullable|in:transferencia,cheque,efectivo',
            'num_cuenta'           => 'nullable|string|max:50',
            'banco'                => 'nullable|string|max:100',
        ]);

        $totalIngresos = round(
            $data['sueldo_base'] + $data['horas_extras_50'] + $data['horas_extras_100'] +
            $data['comisiones'] + $data['otros_ingresos'],
            2
        );
        $totalEgresos = round(
            $data['aporte_personal_iess'] + $data['descuento_atrasos'] +
            $data['descuento_prestamos'] + $data['descuento_anticipos'] + $data['otros_egresos'],
            2
        );

        DB::transaction(function () use ($detalle, $data, $totalIngresos, $totalEgresos, $nomina) {
            $detalle->update(array_merge($data, [
                'total_ingresos'       => $totalIngresos,
                'total_egresos'        => $totalEgresos,
                'neto_pagar'           => $totalIngresos - $totalEgresos,
                'modificado_manualmente' => true,
            ]));

            // Recalcular totales de la nómina
            $totales = NominaDetalle::where('nomina_id', $nomina->id)
                ->selectRaw('SUM(total_ingresos) as ing, SUM(total_egresos) as egr')
                ->first();

            $nomina->update([
                'total_ingresos' => round((float)$totales->ing, 2),
                'total_egresos'  => round((float)$totales->egr, 2),
                'total_neto'     => round((float)$totales->ing - (float)$totales->egr, 2),
            ]);
        });

        return back()->with('success', 'Rol de pago actualizado manualmente.');
    }

    // ── Procesar (borrador → procesado + asiento contable) ───────────────────

    public function procesar(int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $nomina    = Nomina::where('empresa_id', $empresaId)
            ->with('detalles.colaborador')->findOrFail($id);

        if ($nomina->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden procesar nóminas en borrador.');
        }

        if ($nomina->detalles->isEmpty()) {
            return back()->with('error', 'La nómina no tiene detalles.');
        }

        try {
            DB::transaction(function () use ($nomina) {
                $asiento = $this->asientoService->nomina($nomina);

                $nomina->update([
                    'estado'       => 'procesado',
                    'asiento_id'   => $asiento->id,
                    'procesado_por'=> Auth::id(),
                ]);

                $nomina->detalles()->update(['estado' => 'procesado']);

                // Descontar saldo de préstamos/anticipos activos AQUÍ, en el mismo momento
                // en que se genera el asiento con el cruce HABER 1.1.3.4 (Regla NOM-02) —
                // no al pagar. De lo contrario el asiento ya refleja la cuota descontada
                // mientras prestamos_empleados.saldo sigue mostrando el valor viejo hasta
                // que se registre el pago, una inconsistencia real entre el libro contable
                // y la ficha del préstamo.
                foreach ($nomina->detalles as $det) {
                    if ((float)$det->descuento_prestamos > 0) {
                        $prestamos = PrestamoEmpleado::where('colaborador_id', $det->colaborador_id)
                            ->where('tipo', 'prestamo')->activos()->get();

                        $pendiente = (float)$det->descuento_prestamos;
                        foreach ($prestamos as $pr) {
                            if ($pendiente <= 0) break;
                            $descuento = min((float)$pr->cuota, $pendiente);
                            $nuevoSaldo = max(0, (float)$pr->saldo - $descuento);
                            $pr->update([
                                'saldo'  => $nuevoSaldo,
                                'estado' => $nuevoSaldo <= 0 ? 'pagado' : 'activo',
                            ]);
                            $pendiente -= $descuento;
                        }
                    }

                    if ((float)$det->descuento_anticipos > 0) {
                        PrestamoEmpleado::where('colaborador_id', $det->colaborador_id)
                            ->where('tipo', 'anticipo')->where('estado', 'activo')
                            ->update(['saldo' => 0, 'estado' => 'pagado']);
                    }
                }
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al procesar: ' . $e->getMessage());
        }

        return back()->with('success', 'Nómina procesada y asiento contable generado.');
    }

    // ── Registrar pago (procesado → pagado) ──────────────────────────────────

    public function pagar(Request $request, int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $nomina    = Nomina::where('empresa_id', $empresaId)->findOrFail($id);

        if ($nomina->estado !== 'procesado') {
            return back()->with('error', 'Solo se pueden pagar nóminas procesadas.');
        }

        $data = $request->validate([
            'fecha_pago'         => 'required|date',
            'tipo_comprobante'   => 'required|in:transferencia_masiva,individual',
            'num_comprobante'    => 'required|string|max:100',
        ]);

        try {
            DB::transaction(function () use ($nomina, $data) {
                $nomina->update([
                    'estado'    => 'pagado',
                    'pagado_por'=> Auth::id(),
                ]);

                $nomina->detalles()->update(['estado' => 'pagado']);

                // El descuento de préstamos/anticipos ya se aplicó en procesar() — el saldo
                // de prestamos_empleados debe quedar consistente con el asiento contable
                // desde ese momento, no al registrar el pago.
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al registrar pago: ' . $e->getMessage());
        }

        return back()->with('success', 'Pago registrado. Nómina marcada como pagada.');
    }

    // ── Eliminar (solo borrador) ──────────────────────────────────────────────

    public function destroy(int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $nomina    = Nomina::where('empresa_id', $empresaId)->findOrFail($id);

        if ($nomina->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden eliminar nóminas en borrador.');
        }

        $nomina->delete(); // detalles se eliminan por CASCADE

        return redirect()->route('rrhh.nomina.index')
            ->with('success', 'Nómina eliminada.');
    }

    // ── PDF individual de un colaborador ─────────────────────────────────────

    public function pdfIndividual(int $id, int $did)
    {
        $empresaId = session('empresa_activa_id');

        $nomina  = Nomina::where('empresa_id', $empresaId)
            ->with('empresa')->findOrFail($id);
        $detalle = NominaDetalle::where('nomina_id', $nomina->id)
            ->with('colaborador')->findOrFail($did);

        $pdf = Pdf::loadView('pdf.nomina-individual', [
            'nomina'  => $nomina->append('periodo_label'),
            'detalle' => $detalle,
            'empresa' => $nomina->empresa,
        ])->setPaper('a4', 'portrait');

        $nombre = str_replace(' ', '-', $detalle->colaborador->apellidos);
        return $pdf->stream("rol-{$nombre}-{$nomina->anio}-{$nomina->mes}.pdf");
    }

    // ── ZIP con todos los PDFs de la nómina — en segundo plano ────────────────
    // Antes generaba el ZIP de forma síncrona dentro del propio request HTTP
    // (un PDF por colaborador, uno por uno, con DomPDF), bloqueando la UI en
    // nóminas con muchos colaboradores. Ahora solo despacha el Job y notifica
    // — mismo patrón que Libro Diario/Mayor en Reportes Contables.
    public function pdfMasivo(Request $request, int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $nomina = Nomina::where('empresa_id', $empresaId)->findOrFail($id);

        ExportarNominaZipJob::dispatch($nomina->id, (int) Auth::id(), $request->getSchemeAndHttpHost());

        return back()->with('success',
            'El ZIP con los roles de pago se está generando en segundo plano. Te avisaremos por notificación cuando esté listo para descargar.');
    }

    // ── Descarga del ZIP ya generado por ExportarNominaZipJob ─────────────────
    public function descargarExportacion(string $archivo): \Symfony\Component\HttpFoundation\Response
    {
        $archivo = basename($archivo);

        if (!str_starts_with($archivo, Auth::id() . '_')) {
            abort(403, 'No tienes acceso a este archivo.');
        }

        $ruta = ExportarNominaZipJob::CARPETA . '/' . $archivo;
        if (!Storage::disk('local')->exists($ruta)) {
            abort(404, 'El archivo expiró o ya no está disponible (las exportaciones se conservan 48 horas). Genera el ZIP nuevamente.');
        }

        $nombreDescarga = preg_replace('/^\d+_/', '', $archivo);

        return Storage::disk('local')->download($ruta, $nombreDescarga);
    }

}
