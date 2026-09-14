<?php

namespace App\Jobs;

use App\Models\CuentaCobrar;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AlertaVencimientoCxC implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // CxC que vencen hoy (día 0)
        $cxcVenceHoy = CuentaCobrar::with(['cliente', 'factura'])
            ->whereDate('fecha_vencimiento', now()->toDateString())
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('saldo', '>', 0)
            ->get();

        // CxC vencidas hace exactamente 15 días (fecha puntual, no rango, para
        // no reenviar la misma alerta día tras día)
        $cxcVencida15 = CuentaCobrar::with(['cliente', 'factura'])
            ->whereDate('fecha_vencimiento', now()->subDays(15)->toDateString())
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('saldo', '>', 0)
            ->get();

        // CxC vencidas hace exactamente 30 días
        $cxcVencida30 = CuentaCobrar::with(['cliente', 'factura'])
            ->whereDate('fecha_vencimiento', now()->subDays(30)->toDateString())
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->where('saldo', '>', 0)
            ->get();

        if ($cxcVenceHoy->isEmpty() && $cxcVencida15->isEmpty() && $cxcVencida30->isEmpty()) {
            return;
        }

        // Usuarios con perfil admin que deben recibir la alerta — mismo
        // criterio que AlertaVencimientoCxP (Cliente no tiene vínculo a un
        // vendedor, así que no se notifica por vendedor).
        $usuariosAlerta = Usuario::whereHas('perfil', function ($q) {
            $q->whereIn('nombre', ['super_admin', 'admin', 'Super Admin', 'Administrador']);
        })->where('estado', true)->get();

        if ($usuariosAlerta->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($cxcVenceHoy, $cxcVencida15, $cxcVencida30, $usuariosAlerta) {
            foreach ($usuariosAlerta as $usuario) {
                $this->notificarVentana($usuario, $cxcVenceHoy, 'cxc_vence_hoy', '⚠️ Factura vence hoy', function ($cliente, $numDoc, $fecha, $saldo) {
                    return "La factura {$numDoc} de {$cliente} vence hoy ({$fecha}). "
                        . 'Saldo pendiente: $' . number_format($saldo, 2);
                });

                $this->notificarVentana($usuario, $cxcVencida15, 'cxc_vencida_15', '🟠 CxC vencida hace 15 días', function ($cliente, $numDoc, $fecha, $saldo) {
                    return "La factura {$numDoc} de {$cliente} está vencida desde el {$fecha} (15 días). "
                        . 'Saldo pendiente: $' . number_format($saldo, 2);
                });

                $this->notificarVentana($usuario, $cxcVencida30, 'cxc_vencida_30', '🔴 CxC vencida hace 30 días', function ($cliente, $numDoc, $fecha, $saldo) {
                    return "La factura {$numDoc} de {$cliente} está vencida desde el {$fecha} (30 días). "
                        . 'Saldo pendiente: $' . number_format($saldo, 2);
                });
            }
        });
    }

    /**
     * Crea una notificación por cada CxC de la ventana dada, salteando las
     * que ya se notificaron hoy para este usuario (mismo patrón $yaExiste
     * de AlertaVencimientoCxP).
     */
    private function notificarVentana(Usuario $usuario, $cuentas, string $tipo, string $titulo, callable $mensaje): void
    {
        foreach ($cuentas as $cxc) {
            $numDoc  = $cxc->factura?->numero_completo ?? "CXC-{$cxc->id}";
            $cliente = $cxc->cliente?->razon_social ?? 'Cliente';
            $fecha   = $cxc->fecha_vencimiento instanceof \Carbon\Carbon
                ? $cxc->fecha_vencimiento->format('d/m/Y')
                : $cxc->fecha_vencimiento;

            $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                ->where('tipo', $tipo)
                ->where('mensaje', 'like', "%{$numDoc}%")
                ->whereDate('created_at', today())
                ->exists();

            if ($yaExiste) {
                continue;
            }

            Notificacion::create([
                'usuario_id' => $usuario->id,
                'tipo'       => $tipo,
                'titulo'     => $titulo,
                'mensaje'    => $mensaje($cliente, $numDoc, $fecha, (float) $cxc->saldo),
                'leida'      => false,
            ]);
        }
    }
}
