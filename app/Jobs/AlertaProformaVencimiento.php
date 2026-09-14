<?php

namespace App\Jobs;

use App\Models\Notificacion;
use App\Models\Proforma;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AlertaProformaVencimiento implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Proformas pendientes cuyo vencimiento ya pasó: transicionan a
        // 'vencida'. convertirAFactura() y destroy() ya exigen
        // estado === 'pendiente', así que dejan de operar sobre estas
        // automáticamente una vez vencidas (comportamiento esperado).
        Proforma::where('estado', 'pendiente')
            ->whereDate('fecha_vencimiento', '<', now()->toDateString())
            ->update(['estado' => 'vencida']);

        // Proformas pendientes que vencen mañana (ventana puntual de 1 día,
        // igual que las alertas de CxC/CxP) — se notifica al vendedor que
        // la creó (usuario_id).
        $proformasPorVencer = Proforma::with('cliente')
            ->whereDate('fecha_vencimiento', now()->addDay()->toDateString())
            ->where('estado', 'pendiente')
            ->whereNotNull('usuario_id')
            ->get();

        if ($proformasPorVencer->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($proformasPorVencer) {
            foreach ($proformasPorVencer as $proforma) {
                $cliente = $proforma->cliente?->razon_social ?? 'Cliente';
                $fecha   = $proforma->fecha_vencimiento instanceof \Carbon\Carbon
                    ? $proforma->fecha_vencimiento->format('d/m/Y')
                    : $proforma->fecha_vencimiento;

                $yaExiste = Notificacion::where('usuario_id', $proforma->usuario_id)
                    ->where('tipo', 'proforma_por_vencer')
                    ->where('mensaje', 'like', "%{$proforma->numero}%")
                    ->whereDate('created_at', today())
                    ->exists();

                if ($yaExiste) {
                    continue;
                }

                Notificacion::create([
                    'usuario_id' => $proforma->usuario_id,
                    'tipo'       => 'proforma_por_vencer',
                    'titulo'     => '⚠️ Proforma por vencer mañana',
                    'mensaje'    => "La proforma {$proforma->numero} de {$cliente} vence el {$fecha}.",
                    'leida'      => false,
                ]);
            }
        });
    }
}
