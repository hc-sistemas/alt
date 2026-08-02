<?php

namespace App\Http\Controllers\RRHH;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Models\Colaborador;
use App\Models\FeriadoNacional;
use App\Models\HorasExtrasAprobacion;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AsistenciaController extends Controller
{
    public function index(): Response
    {
        $empresaId   = session('empresa_activa_id');
        $ahora       = now();
        $hoy         = $ahora->toDateString();

        // Colaborador vinculado al usuario logueado
        $colaborador = Colaborador::with('horario')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', Auth::id())
            ->where('estado', true)
            ->first();

        $asistenciaHoy = null;

        if ($colaborador) {
            $asistenciaHoy = Asistencia::with('horasExtras:id,asistencia_id,estado,horas_aprobadas')
                ->where('colaborador_id', $colaborador->id)
                ->where('fecha', $hoy)
                ->first();
        }

        // Historial del mes actual
        // NOM-06: las horas extra quedan "pendiente" al timbrar y solo cuentan como
        // aprobadas cuando un Administrador las revisa en Horas Extras — por eso se
        // trae el estado real de HorasExtrasAprobacion en vez de confiar en el valor
        // de asistencias.horas_extra a secas (ese campo se llena de inmediato al
        // marcar salida, sin importar si luego se aprueba o se rechaza).
        $historial = null;
        if ($colaborador) {
            $historial = Asistencia::with('horasExtras:id,asistencia_id,estado,horas_aprobadas')
                ->where('colaborador_id', $colaborador->id)
                ->whereBetween('fecha', [$ahora->copy()->startOfMonth(), $ahora->copy()->endOfMonth()])
                ->orderByDesc('fecha')
                ->get();
        }

        // Si es admin/super_admin → ver todas las asistencias del día
        $resumenDia = null;
        if (in_array(Auth::user()?->perfil?->nombre, ['super_admin', 'admin'], true)) {
            $resumenDia = Asistencia::with(['colaborador', 'horasExtras:id,asistencia_id,estado,horas_aprobadas'])
                ->whereHas('colaborador', fn($q) => $q->where('empresa_id', $empresaId))
                ->where('fecha', $hoy)
                ->orderBy('hora_entrada')
                ->get();
        }

        return Inertia::render('RRHH/Asistencia/Index', [
            'colaborador'    => $colaborador,
            'asistenciaHoy'  => $asistenciaHoy,
            'historial'      => $historial,
            'resumenDia'     => $resumenDia,
            'server_time'    => $ahora->toIso8601String(),
            'es_admin'       => in_array(Auth::user()?->perfil?->nombre, ['super_admin', 'admin'], true) ?? false,
        ]);
    }

    public function registrarEntrada(Request $request): RedirectResponse
    {
        $empresaId   = session('empresa_activa_id');
        $ahora       = now();
        $hoy         = $ahora->toDateString();

        $colaborador = Colaborador::with('horario')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', Auth::id())
            ->where('estado', true)
            ->firstOrFail();

        // Verificar que no haya ya entrada hoy
        $existe = Asistencia::where('colaborador_id', $colaborador->id)
            ->where('fecha', $hoy)
            ->whereNotNull('hora_entrada')
            ->exists();

        if ($existe) {
            return back()->with('error', 'Ya registraste tu entrada hoy.');
        }

        // Calcular minutos de atraso según horario
        $minutosAtraso = 0;
        $horario = $colaborador->horario;

        if ($horario) {
            $horaOficial  = Carbon::parse("{$hoy} {$horario->hora_entrada}");
            $tolerancia   = $horario->tolerancia_minutos ?? 5;
            $limiteEntrada = $horaOficial->copy()->addMinutes($tolerancia);

            if ($ahora->greaterThan($limiteEntrada)) {
                // diffInMinutes() en Carbon 3 es firmado (negativo si $ahora es posterior a $horaOficial);
                // se necesita el valor absoluto de minutos de atraso.
                $minutosAtraso = (int) abs($ahora->diffInMinutes($horaOficial));
            }
        }

        Asistencia::create([
            'colaborador_id' => $colaborador->id,
            'fecha'          => $hoy,
            'hora_entrada'   => $ahora,
            'minutos_atraso' => $minutosAtraso,
            'ip_entrada'     => $request->ip(),
        ]);

        $msg = "Entrada registrada a las " . $ahora->format('H:i:s');
        if ($minutosAtraso > 0) {
            $msg .= " — {$minutosAtraso} minutos de atraso.";
        }

        return back()->with('success', $msg);
    }

    public function registrarSalida(Request $request): RedirectResponse
    {
        $empresaId = session('empresa_activa_id');
        $ahora     = now();
        $hoy       = $ahora->toDateString();

        $colaborador = Colaborador::with('horario')
            ->where('empresa_id', $empresaId)
            ->where('usuario_id', Auth::id())
            ->where('estado', true)
            ->firstOrFail();

        $asistencia = Asistencia::where('colaborador_id', $colaborador->id)
            ->where('fecha', $hoy)
            ->whereNotNull('hora_entrada')
            ->whereNull('hora_salida')
            ->firstOrFail();

        // Calcular horas extra
        $horasExtra = 0;
        $tipoExtra  = null;
        $horario    = $colaborador->horario;

        if ($horario) {
            $horaSalida = Carbon::parse("{$hoy} {$horario->hora_salida}");

            if ($ahora->greaterThan($horaSalida)) {
                // diffInMinutes() en Carbon 3 es firmado (negativo si $ahora es posterior a $horaSalida);
                // se necesita el valor absoluto de minutos extra.
                $horasExtra = round(abs($ahora->diffInMinutes($horaSalida)) / 60, 2);

                // Regla NOM-06 Ecuador: extraordinaria (recargo 100%) = sábado,
                // domingo, feriado nacional o madrugada (00:00-06:00); el resto
                // (día laborable, fuera de madrugada) = suplementaria (recargo 50%).
                $esFindeSemana = $ahora->isWeekend();
                $esMadrugada   = $ahora->hour >= 0 && $ahora->hour < 6;
                $esFeriado     = FeriadoNacional::esFeriado($hoy);

                $tipoExtra = ($esFindeSemana || $esMadrugada || $esFeriado)
                    ? 'extraordinaria'
                    : 'suplementaria';
            }
        }

        DB::transaction(function () use ($asistencia, $ahora, $horasExtra, $tipoExtra, $colaborador, $hoy, $request) {
            $asistencia->update([
                'hora_salida' => $ahora,
                'horas_extra' => $horasExtra,
                'tipo_extra'  => $tipoExtra,
                'ip_salida'   => $request->ip(),
            ]);

            if ($horasExtra > 0 && $tipoExtra) {
                $sueldo         = $colaborador->sueldo_base;
                $valorHora      = $sueldo / 240;
                $factor         = $tipoExtra === 'extraordinaria' ? 2.0 : 1.5;
                $valorCalculado = round($horasExtra * $valorHora * $factor, 2);

                HorasExtrasAprobacion::firstOrCreate(
                    [
                        'colaborador_id' => $colaborador->id,
                        'asistencia_id'  => $asistencia->id,
                        'fecha'          => $hoy,
                    ],
                    [
                        'horas_solicitadas' => $horasExtra,
                        'tipo'              => $tipoExtra,
                        'valor_calculado'   => $valorCalculado,
                        'estado'            => 'pendiente',
                    ]
                );
            }
        });

        $msg = "Salida registrada a las " . $ahora->format('H:i:s');
        if ($horasExtra > 0) {
            $tipoLabel = $tipoExtra === 'extraordinaria' ? 'extraordinarias' : 'suplementarias';
            $msg .= " — {$horasExtra}h extras {$tipoLabel} enviadas a aprobación.";
        }

        return back()->with('success', $msg);
    }
}
