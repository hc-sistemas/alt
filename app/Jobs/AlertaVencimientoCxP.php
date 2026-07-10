<?php

namespace App\Jobs;

use App\Models\CuentaPagar;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AlertaVencimientoCxP implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // CxP que vencen en las próximas 48 horas (ventana ±2h de tolerancia)
        $desde = now()->addHours(46)->toDateString();
        $hasta = now()->addHours(50)->toDateString();

        $cxpPorVencer = CuentaPagar::with(['proveedor', 'compra'])
            ->whereBetween('fecha_vencimiento', [$desde, $hasta])
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('saldo', '>', 0)
            ->get();

        // CxP ya vencidas (fecha_vencimiento < hoy)
        $cxpVencidas = CuentaPagar::with('proveedor')
            ->where('fecha_vencimiento', '<', now()->toDateString())
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('saldo', '>', 0)
            ->get();

        if ($cxpPorVencer->isEmpty() && $cxpVencidas->isEmpty()) {
            return;
        }

        // Usuarios con perfil admin que deben recibir la alerta
        $usuariosAlerta = Usuario::whereHas('perfil', function ($q) {
            $q->whereIn('nombre', ['super_admin', 'admin', 'Super Admin', 'Administrador']);
        })->where('estado', true)->get();

        if ($usuariosAlerta->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($cxpPorVencer, $cxpVencidas, $usuariosAlerta) {
            foreach ($usuariosAlerta as $usuario) {

                // Alertas de CxP por vencer en 48h (una por factura)
                foreach ($cxpPorVencer as $cxp) {
                    $numDoc      = $cxp->compra?->num_documento ?? "CxP #{$cxp->id}";
                    $proveedor   = $cxp->proveedor?->razon_social ?? 'Proveedor';
                    $vencimiento = $cxp->fecha_vencimiento instanceof \Carbon\Carbon
                        ? $cxp->fecha_vencimiento->format('d/m/Y')
                        : $cxp->fecha_vencimiento;

                    $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                        ->where('tipo', 'cxp_por_vencer')
                        ->where('mensaje', 'like', "%{$numDoc}%")
                        ->whereDate('created_at', today())
                        ->exists();

                    if ($yaExiste) {
                        continue;
                    }

                    Notificacion::create([
                        'usuario_id' => $usuario->id,
                        'tipo'       => 'cxp_por_vencer',
                        'titulo'     => '⚠️ CxP por vencer en 48 horas',
                        'mensaje'    => "La factura {$numDoc} de {$proveedor} vence el {$vencimiento}. "
                            . 'Saldo pendiente: $' . number_format((float)$cxp->saldo, 2),
                        'leida'      => false,
                    ]);
                }

                // Resumen diario de CxP vencidas
                if ($cxpVencidas->isNotEmpty()) {
                    $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                        ->where('tipo', 'cxp_vencidas_resumen')
                        ->whereDate('created_at', today())
                        ->exists();

                    if (!$yaExiste) {
                        $totalVencido = $cxpVencidas->sum('saldo');

                        Notificacion::create([
                            'usuario_id' => $usuario->id,
                            'tipo'       => 'cxp_vencidas_resumen',
                            'titulo'     => '🔴 Tienes CxP vencidas',
                            'mensaje'    => $cxpVencidas->count() . ' factura(s) de proveedor vencida(s). '
                                . 'Total en mora: $' . number_format((float)$totalVencido, 2),
                            'leida'      => false,
                        ]);
                    }
                }
            }
        });
    }
}
