<?php

namespace App\Jobs;

use App\Http\Controllers\Bancos\CierreCajaController;
use App\Models\CierreCaja;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Una caja se supone que abre y cierra el mismo día. Si sigue en estado
 * 'abierto' desde el día anterior (o antes), avisa a los administradores —
 * mismo patrón que AlertaVencimientoCxP/AlertaVouchersNoLiquidados. Se
 * detectó este caso real en producción: "Caja Chica Administración" quedó
 * abierta el 29/06/2026 y seguía sin cerrar más de una semana después,
 * mientras se abrían cierres nuevos para la misma caja por encima (el
 * candado de CierreCajaController::abrir() solo compara contra la fecha de
 * HOY, no contra cualquier apertura anterior sin cerrar — bug reportado,
 * no corregido en este Job).
 */
class AlertaCajasAbiertas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $umbral = CierreCajaController::UMBRAL_DIAS_SOSPECHOSA;

        $cajasAbiertas = CierreCaja::with('bancoCaja')
            ->where('estado', 'abierto')
            ->where('fecha', '<=', now()->subDays($umbral)->toDateString())
            ->get();

        if ($cajasAbiertas->isEmpty()) {
            return;
        }

        $usuariosAlerta = Usuario::whereHas('perfil', function ($q) {
            $q->whereIn('nombre', ['super_admin', 'admin', 'Super Admin', 'Administrador']);
        })->where('estado', true)->get();

        if ($usuariosAlerta->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($cajasAbiertas, $usuariosAlerta) {
            foreach ($usuariosAlerta as $usuario) {
                foreach ($cajasAbiertas as $cierre) {
                    $nombreCaja  = $cierre->bancoCaja?->nombre ?? "Caja #{$cierre->banco_caja_id}";
                    // Ver CierreCajaController::index() para la nota sobre el orden
                    // de diffInDays() — invertido aquí da negativo con datos reales.
                    $diasAbierta = (int) abs($cierre->fecha->diffInDays(now()));
                    $fechaApertura = $cierre->fecha->format('d/m/Y');

                    $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                        ->where('tipo', 'caja_abierta_prolongada')
                        ->where('mensaje', 'like', "%cierre #{$cierre->id}%")
                        ->whereDate('created_at', today())
                        ->exists();

                    if ($yaExiste) {
                        continue;
                    }

                    Notificacion::create([
                        'usuario_id' => $usuario->id,
                        'tipo'       => 'caja_abierta_prolongada',
                        'titulo'     => '🔴 Caja abierta sin cerrar',
                        'mensaje'    => "{$nombreCaja} sigue abierta desde el {$fechaApertura} "
                            . "({$diasAbierta} día(s), cierre #{$cierre->id}). Revisa si falta cerrarla.",
                        'leida'      => false,
                    ]);
                }
            }
        });
    }
}
