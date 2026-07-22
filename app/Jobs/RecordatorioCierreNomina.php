<?php

namespace App\Jobs;

use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RecordatorioCierreNomina implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $usuariosAlerta = Usuario::whereHas('perfil', fn($q) =>
            $q->whereIn('nombre', ['super_admin', 'admin', 'Super Admin', 'Administrador'])
        )->where('estado', true)->get();

        if ($usuariosAlerta->isEmpty()) {
            return;
        }

        $mes    = now()->format('F Y');
        $mesNum = now()->format('m/Y');

        DB::transaction(function () use ($usuariosAlerta, $mes, $mesNum) {
            foreach ($usuariosAlerta as $usuario) {
                $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                    ->where('tipo', 'recordatorio_cierre_nomina')
                    ->whereDate('created_at', today())
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                Notificacion::create([
                    'usuario_id' => $usuario->id,
                    'tipo'       => 'recordatorio_cierre_nomina',
                    'titulo'     => '📋 Recordatorio: Cierre de Nómina',
                    'mensaje'    => "Hoy es día 28. Procese la nómina de {$mes} antes del fin de mes. "
                        . "Verifique asistencias, horas extras y novedades del período {$mesNum} "
                        . 'antes de aprobar la nómina.',
                    'leida'      => false,
                ]);
            }
        });
    }
}
