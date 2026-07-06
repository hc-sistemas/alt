<?php

namespace App\Jobs;

use App\Models\DatafastLote;
use App\Models\Notificacion;
use App\Models\Usuario;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class AlertaVouchersNoLiquidados implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        // Lotes Datafast pendientes con más de 72 horas sin liquidar
        $limite = now()->subHours(72);

        $lotes = DatafastLote::with('bancoCaja')
            ->where('estado', 'pendiente')
            ->where('created_at', '<', $limite)
            ->get();

        if ($lotes->isEmpty()) {
            return;
        }

        $usuariosAlerta = Usuario::whereHas('perfil', fn($q) =>
            $q->whereIn('nombre', ['super_admin', 'admin', 'Super Admin', 'Administrador'])
        )->where('estado', true)->get();

        if ($usuariosAlerta->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($lotes, $usuariosAlerta) {
            foreach ($usuariosAlerta as $usuario) {
                foreach ($lotes as $lote) {
                    $banco  = $lote->bancoCaja?->nombre ?? "Banco #{$lote->banco_caja_id}";
                    $horas  = (int) now()->diffInHours($lote->created_at);
                    $fecha  = $lote->fecha?->format('d/m/Y') ?? '—';

                    $yaExiste = Notificacion::where('usuario_id', $usuario->id)
                        ->where('tipo', 'voucher_no_liquidado')
                        ->where('mensaje', 'like', "%lote {$lote->numero_lote}%")
                        ->whereDate('created_at', today())
                        ->exists();

                    if ($yaExiste) {
                        continue;
                    }

                    Notificacion::create([
                        'usuario_id' => $usuario->id,
                        'tipo'       => 'voucher_no_liquidado',
                        'titulo'     => '⚠️ Lote Datafast sin liquidar',
                        'mensaje'    => "El lote {$lote->numero_lote} del {$fecha} ({$banco}) "
                            . "lleva {$horas}h sin liquidar. "
                            . 'Total vouchers: $' . number_format((float)$lote->total_vouchers, 2),
                        'leida'      => false,
                    ]);
                }
            }
        });
    }
}
