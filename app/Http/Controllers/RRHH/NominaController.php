<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Colaborador;
use App\Models\Nomina;
use App\Models\NominaDetalle;
use App\Models\PrestamoEmpleado;
use App\Models\HorasExtrasAprobacion;
use App\Services\AsientoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NominaController extends Controller
{
    public function __construct(private AsientoService $asientoService) {}

    // ── Listado de nóminas ────────────────────────────────────────────────────

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

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

        $nominas = $query->orderByDesc('anio')->orderByDesc('mes')
            ->orderByDesc('quincena')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return Inertia::render('RRHH/Nomina/Index', [
            'nominas' => $nominas,
            'filtros' => $request->only(['anio', 'mes', 'tipo', 'estado']),
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
                    $detalle = $this->calcularDetalle($col, $data, $nomina->id);
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

    public function update(Request $request, int $id, int $did): JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $nomina  = Nomina::where('empresa_id', $empresaId)->findOrFail($id);
        $detalle = NominaDetalle::where('nomina_id', $nomina->id)->findOrFail($did);

        if ($nomina->estado !== 'borrador') {
            return response()->json(['error' => 'Solo se puede editar nóminas en borrador.'], 422);
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

        return response()->json([
            'success' => true,
            'detalle' => $detalle->fresh()->load('colaborador'),
            'nomina'  => $nomina->fresh(),
        ]);
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

    // ── ZIP con todos los PDFs de la nómina ──────────────────────────────────

    public function pdfMasivo(int $id): BinaryFileResponse
    {
        $empresaId = session('empresa_activa_id');

        $nomina = Nomina::where('empresa_id', $empresaId)
            ->with(['empresa', 'detalles.colaborador'])->findOrFail($id);

        $tmpDir  = sys_get_temp_dir() . '/nomina_' . $nomina->id . '_' . time();
        $zipPath = $tmpDir . '.zip';
        mkdir($tmpDir, 0755, true);

        foreach ($nomina->detalles as $det) {
            $pdf = Pdf::loadView('pdf.nomina-individual', [
                'nomina'  => $nomina->append('periodo_label'),
                'detalle' => $det,
                'empresa' => $nomina->empresa,
            ])->setPaper('a4', 'portrait');

            $nombre = str_replace(' ', '-', $det->colaborador->apellidos);
            file_put_contents("{$tmpDir}/{$nombre}.pdf", $pdf->output());
        }

        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        foreach (glob("{$tmpDir}/*.pdf") as $file) {
            $zip->addFile($file, basename($file));
        }
        $zip->close();

        // Limpiar directorio temporal
        array_map('unlink', glob("{$tmpDir}/*.pdf"));
        rmdir($tmpDir);

        return response()->download($zipPath, "nomina-{$nomina->anio}-{$nomina->mes}.zip")
            ->deleteFileAfterSend(true);
    }

    // ── Lógica de cálculo por colaborador ────────────────────────────────────

    private function calcularDetalle(Colaborador $col, array $data, int $nominaId): array
    {
        $esQuincenal = $data['periodo_tipo'] === 'quincenal';
        $anio        = (int)$data['anio'];
        $mes         = (int)$data['mes'];

        // Sueldo base (dividido a la mitad si es quincenal)
        $sueldo = $esQuincenal
            ? round((float)$col->sueldo_base / 2, 2)
            : (float)$col->sueldo_base;

        // Horas extras aprobadas del período
        $baseQuery = HorasExtrasAprobacion::where('colaborador_id', $col->id)
            ->where('estado', 'aprobado')
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes);

        // Si es quincenal filtramos por días de la quincena
        if ($esQuincenal) {
            $diaInicio = $data['quincena'] === 1 ? 1  : 16;
            $diaFin    = $data['quincena'] === 1 ? 15 : (int)date('t', mktime(0, 0, 0, $mes, 1, $anio));
            $baseQuery->whereDay('fecha', '>=', $diaInicio)
                      ->whereDay('fecha', '<=', $diaFin);
        }

        $extras50  = (float)(clone $baseQuery)->where('tipo', 'suplementaria')->sum('valor_calculado');
        $extras100 = (float)(clone $baseQuery)->where('tipo', 'extraordinaria')->sum('valor_calculado');

        // Décimos mensualizados (solo nómina mensual)
        $otrosIngresos = 0.0;
        if (!$esQuincenal) {
            $SBU = 460.0; // Salario Básico Unificado Ecuador 2026
            if ($col->decimo_tercero === 'mensualiza') {
                $otrosIngresos += round((float)$col->sueldo_base / 12, 2);
            }
            if ($col->decimo_cuarto === 'mensualiza') {
                $otrosIngresos += round($SBU / 12, 2);
            }
            if ($col->fondos_reserva === 'mensualiza') {
                // Fondos de reserva: aplica desde mes 13 de contrato.
                // abs(): diffInMonths() en Carbon 3 es firmado (negativo porque
                // fecha_ingreso siempre es anterior a now()); sin abs() esta condición
                // nunca se cumplía para ningún colaborador, sin importar su antigüedad.
                $mesesContrato = (int) abs(now()->diffInMonths($col->fecha_ingreso));
                if ($mesesContrato >= 13) {
                    $otrosIngresos += round((float)$col->sueldo_base * 0.0833, 2);
                }
            }
        }

        $totalIngresos = round($sueldo + $extras50 + $extras100 + $otrosIngresos, 2);

        // Aporte personal IESS 9.45%
        $aportePersonal = round($totalIngresos * 0.0945, 2);

        // Descuento por atrasos (minutos_atraso del período)
        $minutosAtraso = Asistencia::where('colaborador_id', $col->id)
            ->whereYear('fecha', $anio)->whereMonth('fecha', $mes)->sum('minutos_atraso');

        // Valor por minuto = sueldo_base / (30 días * 8h * 60min)
        $descuentoAtraso = round((float)$minutosAtraso * ((float)$col->sueldo_base / 14400), 2);

        // Préstamos (cuota mensual o mitad si quincenal)
        $descuentoPrestamos = PrestamoEmpleado::cuotasMes($col->id, $data['periodo_tipo']);

        // Anticipos
        $descuentoAnticipos = 0.0; // Se descuentan en nómina mensual completa únicamente
        if (!$esQuincenal) {
            $descuentoAnticipos = PrestamoEmpleado::anticiposPendientes($col->id);
        }

        $totalEgresos = round($aportePersonal + $descuentoAtraso + $descuentoPrestamos + $descuentoAnticipos, 2);
        $netoPagar    = round($totalIngresos - $totalEgresos, 2);

        return [
            'nomina_id'            => $nominaId,
            'colaborador_id'       => $col->id,
            'sueldo_base'          => $sueldo,
            'horas_extras_50'      => $extras50,
            'horas_extras_100'     => $extras100,
            'comisiones'           => 0,
            'otros_ingresos'       => $otrosIngresos,
            'total_ingresos'       => $totalIngresos,
            'aporte_personal_iess' => $aportePersonal,
            'descuento_atrasos'    => $descuentoAtraso,
            'descuento_prestamos'  => $descuentoPrestamos,
            'descuento_anticipos'  => $descuentoAnticipos,
            'otros_egresos'        => 0,
            'total_egresos'        => $totalEgresos,
            'neto_pagar'           => $netoPagar,
            'tipo_pago'            => $col->tipo_cuenta ? 'transferencia' : null,
            'num_cuenta'           => $col->numero_cuenta,
            'banco'                => $col->banco,
            'estado'               => 'borrador',
            'modificado_manualmente' => false,
            'created_at'           => now(),
        ];
    }
}
