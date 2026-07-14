<?php

namespace App\Jobs;

use App\Models\Colaborador;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AlertaAtrasosRecurrentes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Colaboradores con 3 o más atrasos en la semana en curso (lun→dom)
        $inicioSemana = now()->startOfWeek()->toDateString();
        $hoy          = now()->toDateString();

        $colsConAtrasos = DB::table('asistencias')
            ->join('colaboradores', 'colaboradores.id', '=', 'asistencias.colaborador_id')
            ->where('asistencias.fecha', '>=', $inicioSemana)
            ->where('asistencias.fecha', '<=', $hoy)
            ->where('asistencias.minutos_atraso', '>', 0)
            ->where('colaboradores.estado', true)
            ->select(
                'asistencias.colaborador_id',
                'colaboradores.nombres',
                'colaboradores.apellidos',
                'colaboradores.empresa_id',
                DB::raw('COUNT(*) as num_atrasos'),
                DB::raw('SUM(asistencias.minutos_atraso) as total_minutos'),
            )
            ->groupBy(
                'asistencias.colaborador_id',
                'colaboradores.nombres',
                'colaboradores.apellidos',
                'colaboradores.empresa_id',
            )
            ->having('num_atrasos', '>=', 3)
            ->get();

        if ($colsConAtrasos->isEmpty()) {
            return;
        }

        $usuariosAlerta = Usuario::whereHas('perfil', fn($q) =>
            $q->whereIn('nombre', ['super_admin', 'admin', 'Super Admin', 'Administrador'])
        )->where('estado', true)->get();

        if ($usuariosAlerta->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($colsConAtrasos, $usuariosAlerta) {
            foreach ($usuariosAlerta as $usuario) {
                $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                    ->where('tipo', 'atrasos_recurrentes_semana')
                    ->whereDate('created_at', today())
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                $lista = $colsConAtrasos->map(fn($c) =>
                    "{$c->apellidos} {$c->nombres} ({$c->num_atrasos} atrasos, {$c->total_minutos} min)"
                )->implode(' · ');

                Notificacion::create([
                    'usuario_id' => $usuario->id,
                    'tipo'       => 'atrasos_recurrentes_semana',
                    'titulo'     => '🕐 Colaboradores con atrasos recurrentes esta semana',
                    'mensaje'    => $colsConAtrasos->count() . ' colaborador(es) con 3+ atrasos: ' . $lista,
                    'leida'      => false,
                ]);
            }
        });
    }
}
