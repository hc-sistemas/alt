<?php

namespace App\Console\Commands;

use App\Models\Compra;
use App\Models\CuentaPagar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Comando de USO EXCLUSIVO EN DESARROLLO.
 * Revierte todas las facturas de compra en estado 'activa' a 'pendiente',
 * limpia inventario_movimientos, cuentas_pagar, asientos y etiquetas.
 *
 * NUNCA registrar como ruta web ni exponer en frontend.
 * Hacer backup ANTES de ejecutar.
 */
class ResetFacturasPendiente extends Command
{
    protected $signature   = 'altamira:reset-facturas-pendiente {--force : Omitir confirmación interactiva}';
    protected $description = '[DEV ONLY] Revierte facturas activas a pendiente y limpia etiquetas';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<fg=red;options=bold>════════════════════════════════════════════════════</fg=red;options=bold>');
        $this->line('<fg=red;options=bold>  COMANDO DE SOLO DESARROLLO — NO USAR EN PRODUCCIÓN  </fg=red;options=bold>');
        $this->line('<fg=red;options=bold>════════════════════════════════════════════════════</fg=red;options=bold>');
        $this->newLine();
        $this->line('Este comando revertirá:');
        $this->line('  • Todas las facturas de compra activas → pendiente');
        $this->line('  • Movimientos de inventario tipo COMPRA (se eliminan)');
        $this->line('  • Saldos de inventario (se recalculan)');
        $this->line('  • Cuentas por pagar vinculadas (se eliminan)');
        $this->line('  • Asientos contables de compras (se eliminan)');
        $this->line('  • Tabla etiquetas_productos (TRUNCATE)');
        $this->newLine();

        if (!$this->option('force') &&
            !$this->confirm('¿Confirmas ejecutar el reset de datos de prueba?', false)) {
            $this->line('Cancelado.');
            return self::FAILURE;
        }

        $facturasActivas = Compra::where('estado', 'activa')
            ->with(['detalles:id,compra_id,producto_id,cantidad'])
            ->get();

        $count = $facturasActivas->count();

        if ($count === 0) {
            $this->info('No hay facturas en estado activa. Nada que revertir.');
            // Vaciamos etiquetas igualmente
            DB::table('etiquetas_productos')->truncate();
            $this->info('✓ Tabla etiquetas_productos vaciada — correlativos en 0');
            return self::SUCCESS;
        }

        $this->info("Encontradas {$count} facturas activas. Iniciando transacción...");

        try {
            DB::transaction(function () use ($facturasActivas, $count) {

                $compraIds = $facturasActivas->pluck('id')->toArray();

                // ── 1. Identificar (producto_id, bodega_id) afectados ──────────

                $paresAfectados = [];

                foreach ($facturasActivas as $compra) {
                    // Bodega efectiva: la registrada en compra, o la primera general de la empresa
                    $bodegaId = $compra->bodega_id
                        ?? DB::table('bodegas')
                            ->where('empresa_id', $compra->empresa_id)
                            ->where('tipo', 'general')
                            ->value('id');

                    if (!$bodegaId) continue;

                    foreach ($compra->detalles as $d) {
                        if (!$d->producto_id) continue;
                        $clave = $d->producto_id . '_' . $bodegaId;
                        $paresAfectados[$clave] = [
                            'producto_id' => (int) $d->producto_id,
                            'bodega_id'   => (int) $bodegaId,
                        ];
                    }
                }

                // ── 2. Eliminar movimientos de inventario de estas compras ─────

                $eliminados = DB::table('inventario_movimientos')
                    ->where('doc_tipo', 'COMPRA')
                    ->whereIn('doc_id', $compraIds)
                    ->delete();

                $this->line("  → {$eliminados} movimientos de inventario eliminados");

                // ── 3. Recalcular saldos para los pares afectados ─────────────
                //
                // Se recalcula desde los movimientos RESTANTES (los que no eran COMPRA).
                // Si no quedan movimientos, la cantidad queda en 0.

                $saldosActualizados = 0;

                foreach ($paresAfectados as $par) {
                    $productoId = $par['producto_id'];
                    $bodegaId   = $par['bodega_id'];

                    // Cantidad neta de los movimientos restantes
                    $nuevaCantidad = (float) DB::table('inventario_movimientos')
                        ->where('producto_id', $productoId)
                        ->where('bodega_id', $bodegaId)
                        ->selectRaw("
                            COALESCE(SUM(
                                CASE
                                    WHEN tipo IN ('entrada', 'ajuste') THEN cantidad
                                    ELSE -cantidad
                                END
                            ), 0) AS neto
                        ")
                        ->value('neto');

                    $nuevaCantidad = max(0, $nuevaCantidad);

                    // Costo promedio: último costo de entrada disponible
                    $costoPromedio = (float) DB::table('inventario_movimientos')
                        ->where('producto_id', $productoId)
                        ->where('bodega_id', $bodegaId)
                        ->where('tipo', 'entrada')
                        ->orderByDesc('created_at')
                        ->value('costo_unitario') ?? 0;

                    DB::table('inventario_saldos')
                        ->where('producto_id', $productoId)
                        ->where('bodega_id', $bodegaId)
                        ->update([
                            'stock_actual'   => $nuevaCantidad,
                            'costo_promedio' => $costoPromedio,
                            'updated_at'     => now(),
                        ]);

                    $saldosActualizados++;
                }

                $this->line("  → {$saldosActualizados} saldos de inventario recalculados");

                // ── 4. Eliminar cuentas por pagar vinculadas ──────────────────

                $cxpEliminadas = CuentaPagar::whereIn('compra_id', $compraIds)->delete();
                $this->line("  → {$cxpEliminadas} cuentas por pagar eliminadas");

                // ── 5. Desligar y eliminar asientos contables ─────────────────
                //
                // Orden obligatorio:
                //   a) Nulificar FK en compras ANTES de borrar el asiento
                //      (la FK es nullable pero PostgreSQL valida al borrar)
                //   b) Borrar asientos → ON DELETE CASCADE elimina asiento_detalles

                DB::table('compras')
                    ->whereIn('id', $compraIds)
                    ->update(['asiento_id' => null, 'updated_at' => now()]);

                $asientosEliminados = DB::table('asientos_contables')
                    ->where('documento_tipo', 'COMPRA')
                    ->whereIn('documento_id', $compraIds)
                    ->delete();

                $this->line("  → {$asientosEliminados} asientos contables eliminados (con sus líneas)");

                // ── 6. Revertir estado de compras a pendiente ─────────────────

                DB::table('compras')
                    ->whereIn('id', $compraIds)
                    ->update(['estado' => 'pendiente', 'updated_at' => now()]);

                $this->line("  → {$count} facturas revertidas a estado 'pendiente'");

                // ── 7. Vaciar tabla de etiquetas ──────────────────────────────

                DB::table('etiquetas_productos')->truncate();
                $this->line('  → Tabla etiquetas_productos vaciada');
            });

            $this->newLine();
            $this->line('<fg=green>✓ ' . $count . ' facturas revertidas a estado pendiente</fg=green>');
            $this->line('<fg=green>✓ Inventario revertido</fg=green>');
            $this->line('<fg=green>✓ Cuentas por pagar eliminadas</fg=green>');
            $this->line('<fg=green>✓ Tabla etiquetas_productos vaciada — correlativos en 0</fg=green>');
            $this->newLine();

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->newLine();
            $this->error('Error durante el reset: ' . $e->getMessage());
            $this->error('La transacción fue revertida. La base de datos NO fue modificada.');
            \Log::error('altamira:reset-facturas-pendiente falló: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return self::FAILURE;
        }
    }
}
