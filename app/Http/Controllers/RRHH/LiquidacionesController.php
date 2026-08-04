<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Colaborador;
use App\Models\Liquidacion;
use App\Models\PrestamoEmpleado;
use App\Models\Usuario;
use App\Services\AsientoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LiquidacionesController extends Controller
{
    private const SBU_2026 = 460.0;

    public function __construct(private AsientoService $asientoService) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $liquidaciones = null;

        if ($request->boolean('buscado')) {
            $query = Liquidacion::with(['colaborador', 'creadoPor:id,nombre'])
                ->whereHas('colaborador', fn($q) => $q->where('empresa_id', $empresaId));

            if ($request->filled('colaborador_id')) {
                $query->where('colaborador_id', $request->colaborador_id);
            }
            if ($request->filled('motivo')) {
                $query->where('motivo', $request->motivo);
            }
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('buscar')) {
                $q = $request->buscar;
                $query->whereHas('colaborador', fn($qc) => $qc
                    ->where('apellidos', 'ilike', "%{$q}%")
                    ->orWhere('nombres', 'ilike', "%{$q}%")
                    ->orWhere('cedula_ruc', 'ilike', "%{$q}%")
                );
            }

            $liquidaciones = $query->orderByDesc('id')->paginate(20)->withQueryString();
        }

        $colaboradores = Colaborador::where('empresa_id', $empresaId)
            ->activos()->orderBy('apellidos')->orderBy('nombres')
            ->get(['id', 'cedula_ruc', 'apellidos', 'nombres', 'sueldo_base',
                   'fecha_ingreso', 'cargo', 'decimo_tercero', 'decimo_cuarto', 'fondos_reserva']);

        return Inertia::render('RRHH/Liquidaciones/Index', [
            'liquidaciones' => $liquidaciones,
            'colaboradores' => $colaboradores,
            'filtros'       => $request->only(['colaborador_id', 'motivo', 'estado', 'buscar']),
        ]);
    }

    // ── Calcular valores de liquidación (API call del wizard PASO 1) ──────────

    public function calcular(Request $request): JsonResponse
    {
        $data = $request->validate([
            'colaborador_id' => 'required|integer|exists:colaboradores,id',
            'motivo'         => 'required|in:renuncia,despido,fin_contrato',
            'fecha_salida'   => 'required|date',
        ]);

        try {
            $col = Colaborador::findOrFail($data['colaborador_id']);

            if (empty($col->fecha_ingreso)) {
                return response()->json(['error' => 'El colaborador no tiene fecha de ingreso registrada.'], 422);
            }
            if ((float)$col->sueldo_base <= 0) {
                return response()->json(['error' => 'El colaborador no tiene sueldo base registrado.'], 422);
            }

            $fechaIngreso = \Carbon\Carbon::parse($col->fecha_ingreso);
            $fechaSalida  = \Carbon\Carbon::parse($data['fecha_salida']);

            if ($fechaSalida->lt($fechaIngreso)) {
                return response()->json(['error' => 'La fecha de salida no puede ser anterior a la fecha de ingreso ('.$fechaIngreso->format('d/m/Y').').'], 422);
            }

            // Carbon 3 cambió diffInMonths() para devolver meses con fracción
            // decimal (ej. 38.806451612903) en vez del entero truncado que
            // devolvía Carbon 2 — el (int) explícito restaura el comportamiento
            // original con el que se calibró esta fórmula de décimos/fondos de
            // reserva (proporción por MES completo, no por fracción de mes).
            $mesesLaborados = (int)$fechaIngreso->diffInMonths($fechaSalida);
            $diasLaborados  = (int)$fechaIngreso->diffInDays($fechaSalida);

            $decimoTercero = 0.0;
            if ($col->decimo_tercero === 'acumula') {
                $decimoTercero = round((float)$col->sueldo_base * $mesesLaborados / 12, 2);
            }

            $decimoCuarto = 0.0;
            if ($col->decimo_cuarto === 'acumula') {
                $decimoCuarto = round(self::SBU_2026 * $mesesLaborados / 12, 2);
            }

            $decimosAcumulados = round($decimoTercero + $decimoCuarto, 2);

            $vacaciones = round((float)$col->sueldo_base * $diasLaborados / 720, 2);

            $fondosReserva = 0.0;
            if ($col->fondos_reserva === 'acumula' && $mesesLaborados >= 13) {
                $fondosReserva = round((float)$col->sueldo_base * $mesesLaborados / 12 * 0.0833, 2);
            }

            $anticiposDescontar = round(
                (float)PrestamoEmpleado::where('colaborador_id', $col->id)
                    ->where('estado', 'activo')->sum('saldo'),
                2
            );

            $totalLiquidacion = max(0, round($decimosAcumulados + $vacaciones + $fondosReserva - $anticiposDescontar, 2));

            return response()->json([
                'colaborador'        => $col->only(['id','apellidos','nombres','cargo','sueldo_base','fecha_ingreso']),
                'meses_laborados'    => $mesesLaborados,
                'dias_laborados'     => $diasLaborados,
                'decimos_acumulados' => $decimosAcumulados,
                'vacaciones'         => $vacaciones,
                'fondos_reserva'     => $fondosReserva,
                'anticipos_descontar'=> $anticiposDescontar,
                'total_liquidacion'  => $totalLiquidacion,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error al procesar el cálculo: '.$e->getMessage()], 500);
        }
    }

    // ── Guardar borrador (axios desde wizard — retorna JSON) ─────────────────

    public function store(Request $request): JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $data = $request->validate([
            'colaborador_id'     => 'required|integer|exists:colaboradores,id',
            'motivo'             => 'required|in:renuncia,despido,fin_contrato',
            'fecha_salida'       => 'required|date',
            'decimos_acumulados' => 'required|numeric|min:0',
            'vacaciones'         => 'required|numeric|min:0',
            'fondos_reserva'     => 'required|numeric|min:0',
            'anticipos_descontar'=> 'required|numeric|min:0',
            'total_liquidacion'  => 'required|numeric|min:0',
        ]);

        Colaborador::where('id', $data['colaborador_id'])
            ->where('empresa_id', $empresaId)->firstOrFail();

        $existe = Liquidacion::where('colaborador_id', $data['colaborador_id'])
            ->where('estado', 'borrador')->exists();

        if ($existe) {
            return response()->json(['error' => 'Ya existe una liquidación en borrador para este colaborador.'], 422);
        }

        $liq = Liquidacion::create(array_merge($data, [
            'estado'     => 'borrador',
            'created_by' => Auth::id(),
        ]));

        return response()->json(['liquidacion_id' => $liq->id]);
    }

    // ── Actualizar borrador (edición manual — Contador/Super Admin) ───────────

    public function update(Request $request, int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $liq = Liquidacion::whereHas('colaborador',
            fn($q) => $q->where('empresa_id', $empresaId)
        )->findOrFail($id);

        if ($liq->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden editar liquidaciones en borrador.');
        }

        $data = $request->validate([
            'decimos_acumulados' => 'required|numeric|min:0',
            'vacaciones'         => 'required|numeric|min:0',
            'fondos_reserva'     => 'required|numeric|min:0',
            'anticipos_descontar'=> 'required|numeric|min:0',
            'total_liquidacion'  => 'required|numeric|min:0',
        ]);

        $liq->update(array_merge($data, ['modificado_manualmente' => true]));

        return back()->with('success', 'Valores actualizados. La liquidación quedó marcada como modificada manualmente.');
    }

    // ── Aprobar liquidación (solo Super Admin) ───────────────────────────────

    public function aprobar(int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $liq = Liquidacion::with('colaborador.usuario')
            ->whereHas('colaborador', fn($q) => $q->where('empresa_id', $empresaId))
            ->findOrFail($id);

        if ($liq->estado !== 'borrador') {
            return back()->with('error', 'Esta liquidación ya fue procesada.');
        }

        if ((float)$liq->total_liquidacion <= 0) {
            return back()->with('error', 'El total de liquidación debe ser mayor a cero.');
        }

        try {
            DB::transaction(function () use ($liq, $empresaId) {
                // 1. Cambiar estado de la liquidación
                $liq->update(['estado' => 'aprobada']);

                // 2. Marcar colaborador como inactivo
                $col = $liq->colaborador;
                $col->update([
                    'estado'       => false,
                    'fecha_salida' => $liq->fecha_salida,
                ]);

                // 3. Bloquear usuario vinculado si existe
                if ($col->usuario_id) {
                    Usuario::where('id', $col->usuario_id)->update(['estado' => false]);
                }

                // 4. Cancelar préstamos/anticipos activos del colaborador
                PrestamoEmpleado::where('colaborador_id', $col->id)
                    ->where('estado', 'activo')
                    ->update(['estado' => 'pagado', 'saldo' => 0]);

                // 5. Generar asiento contable
                $liq->load('colaborador');
                $asiento = $this->asientoService->liquidacionEmpleado($liq, $empresaId);
                $liq->update(['asiento_id' => $asiento->id]);
            });
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al aprobar: ' . $e->getMessage());
        }

        return back()->with('success', 'Liquidación aprobada. Colaborador desactivado y asiento generado.');
    }

    // ── PDF Acta de Finiquito ────────────────────────────────────────────────

    public function pdf(int $id)
    {
        $empresaId = session('empresa_activa_id');

        $liq = Liquidacion::with(['colaborador.puesto', 'creadoPor'])
            ->whereHas('colaborador', fn($q) => $q->where('empresa_id', $empresaId))
            ->findOrFail($id);

        $empresa = \App\Models\Empresa::findOrFail($empresaId);

        $pdf = Pdf::loadView('pdf.finiquito', [
            'liquidacion' => $liq,
            'colaborador' => $liq->colaborador,
            'empresa'     => $empresa,
        ])->setPaper('letter', 'portrait');

        $nombre = str_replace(' ', '-', $liq->colaborador->apellidos ?? 'colaborador');
        return $pdf->stream("finiquito-{$nombre}-{$liq->id}.pdf");
    }

    public function destroy(int $id): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');

        $liq = Liquidacion::whereHas('colaborador',
            fn($q) => $q->where('empresa_id', $empresaId)
        )->findOrFail($id);

        if ($liq->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden eliminar liquidaciones en borrador.');
        }

        $liq->delete();
        return back()->with('success', 'Liquidación eliminada.');
    }
}
