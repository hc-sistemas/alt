<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrarInventarioLegado extends Command
{
    protected $signature = 'inventario:migrar-legado {--dry-run : Solo muestra el resumen, no inserta nada} {--solo-saldos : No migra el historial de movimientos, solo siembra inventario_saldos desde erp_i_movpt_total}';

    protected $description = 'Migra los movimientos de inventario de altamira2.erp_i_mov_inv_pt a inventario_movimientos e inventario_saldos';

    // bod_id legacy => [bodega_id nueva, empresa_id nueva]
    private const MAPA_BODEGAS = [
        1 => ['bodega_id' => 1, 'empresa_id' => 1],  // Bodega UIO Matriz
        2 => ['bodega_id' => 15, 'empresa_id' => 1], // Bodega Muestra Matriz
        3 => ['bodega_id' => 10, 'empresa_id' => 2], // Bodega general Import
        4 => ['bodega_id' => 9, 'empresa_id' => 1],  // Bodega Reserva Matriz
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($this->option('solo-saldos')) {
            return $this->migrarSoloSaldos($dryRun);
        }

        if (DB::table('inventario_movimientos')->exists() && !$dryRun) {
            $this->error('inventario_movimientos ya tiene datos. Aborta para evitar duplicados. Usa --dry-run para revisar primero.');
            return self::FAILURE;
        }

        // Mapa código legacy (erp_mp.mp_c) => id de producto nuevo
        $productos = DB::table('productos')->pluck('id', 'codigo');

        // Mapa trs_id => fila de erp_transacciones (operacion, descripcion)
        $transacciones = DB::connection('pgsql_legacy')
            ->table('erp_transacciones')
            ->get()
            ->keyBy('trs_id');

        $costoActual = DB::table('productos')->pluck('costo', 'id');

        $total = DB::connection('pgsql_legacy')->table('erp_i_mov_inv_pt')->count();
        $this->info("Movimientos legacy a procesar: {$total}");

        $insertados = 0;
        $omitidosSinProducto = 0;
        $omitidosSinBodega = 0;
        $lote = [];
        $ahora = now();

        // Saldo corriente por "producto_id-bodega_id" para poblar stock_anterior/stock_nuevo
        $saldoCorriente = [];

        // erp_mp.id => mp_c, para resolver pro_id -> codigo -> producto_id nuevo
        $legacyProductos = DB::connection('pgsql_legacy')->table('erp_mp')->pluck('mp_c', 'id');

        DB::connection('pgsql_legacy')
            ->table('erp_i_mov_inv_pt')
            ->orderBy('mov_id')
            ->chunkById(2000, function ($filas) use (
                &$lote, &$insertados, &$omitidosSinProducto, &$omitidosSinBodega, &$saldoCorriente,
                $productos, $legacyProductos, $transacciones, $ahora, $dryRun
            ) {
                foreach ($filas as $fila) {
                    $codigo = $legacyProductos[$fila->pro_id] ?? null;
                    $productoId = $codigo ? ($productos[$codigo] ?? null) : null;

                    if (!$productoId) {
                        $omitidosSinProducto++;
                        continue;
                    }

                    $mapaBod = self::MAPA_BODEGAS[$fila->bod_id] ?? null;
                    if (!$mapaBod) {
                        $omitidosSinBodega++;
                        continue;
                    }

                    $trans = $transacciones[$fila->trs_id] ?? null;
                    $esEntrada = $trans ? ((int) $trans->trs_operacion === 0) : true;

                    $cantidad = (float) $fila->mov_cantidad;
                    $costoUnitario = (float) ($fila->mov_val_unit ?? 0);
                    $costoTotal = $costoUnitario > 0
                        ? round($costoUnitario * $cantidad, 4)
                        : (float) ($fila->mov_val_tot ?? 0);

                    $fecha = $fila->mov_fecha_trans;
                    if (!$fecha || (substr((string) $fecha, 0, 1) === '0' && (int) substr((string) $fecha, 0, 4) < 1900)) {
                        $fecha = $fila->mov_fecha_registro;
                    }
                    $hora = $fila->mov_hora_registro ?: '00:00:00';
                    $creadoEn = $fecha ? "{$fecha} {$hora}" : $ahora;

                    $notas = trim(implode(' | ', array_filter([
                        $trans->trs_descripcion ?? null,
                        $fila->mov_usuario ? "usuario legacy: {$fila->mov_usuario}" : null,
                        $fila->mov_serie ? "serie: {$fila->mov_serie}" : null,
                        $fila->mov_documento ?: null,
                        $fila->mov_obs ?: null,
                    ])));

                    $clave = $productoId.'-'.$mapaBod['bodega_id'];
                    $stockAnterior = $saldoCorriente[$clave] ?? 0.0;
                    $stockNuevo = $esEntrada ? $stockAnterior + $cantidad : $stockAnterior - $cantidad;
                    $saldoCorriente[$clave] = $stockNuevo;

                    $lote[] = [
                        'empresa_id' => $mapaBod['empresa_id'],
                        'producto_id' => $productoId,
                        'bodega_id' => $mapaBod['bodega_id'],
                        'tipo' => $esEntrada ? 'entrada' : 'salida',
                        'doc_tipo' => $trans->trs_descripcion ?? null,
                        'doc_id' => null,
                        'cantidad' => $cantidad,
                        'costo_unitario' => $costoUnitario,
                        'costo_total' => $costoTotal,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $stockNuevo,
                        'usuario_id' => null,
                        'notas' => mb_substr($notas, 0, 300),
                        'created_at' => $creadoEn,
                    ];

                    if (count($lote) >= 1000) {
                        if (!$dryRun) {
                            DB::table('inventario_movimientos')->insert($lote);
                        }
                        $insertados += count($lote);
                        $lote = [];
                    }
                }
            }, 'mov_id', 'mov_id');

        if (count($lote) > 0) {
            if (!$dryRun) {
                DB::table('inventario_movimientos')->insert($lote);
            }
            $insertados += count($lote);
        }

        $this->info("Insertados: {$insertados}");
        $this->warn("Omitidos sin producto mapeable: {$omitidosSinProducto}");
        $this->warn("Omitidos sin bodega mapeable: {$omitidosSinBodega}");

        if ($dryRun) {
            $this->info('Dry-run: no se escribió nada en inventario_movimientos ni inventario_saldos.');
            return self::SUCCESS;
        }

        $this->info('Recalculando inventario_saldos...');

        $saldos = DB::table('inventario_movimientos')
            ->selectRaw("
                producto_id,
                bodega_id,
                SUM(CASE WHEN tipo = 'entrada' THEN cantidad ELSE -cantidad END) as saldo
            ")
            ->groupBy('producto_id', 'bodega_id')
            ->get();

        foreach ($saldos as $saldo) {
            DB::table('inventario_saldos')->updateOrInsert(
                ['producto_id' => $saldo->producto_id, 'bodega_id' => $saldo->bodega_id],
                [
                    'stock_actual' => $saldo->saldo,
                    'costo_promedio' => $costoActual[$saldo->producto_id] ?? 0,
                    'updated_at' => $ahora,
                ]
            );
        }

        $this->info("Saldos recalculados: {$saldos->count()}");

        return self::SUCCESS;
    }

    /**
     * Siembra inventario_saldos directamente desde erp_i_movpt_total (el saldo
     * "oficial" que el sistema legacy mantenía por producto+bodega), sin migrar
     * el historial de movimientos.
     */
    private function migrarSoloSaldos(bool $dryRun): int
    {
        if (DB::table('inventario_saldos')->exists() && !$dryRun) {
            $this->error('inventario_saldos ya tiene datos. Aborta para evitar duplicados. Usa --dry-run para revisar primero.');
            return self::FAILURE;
        }

        $productos = DB::table('productos')->pluck('id', 'codigo');
        $legacyProductos = DB::connection('pgsql_legacy')->table('erp_mp')->pluck('mp_c', 'id');
        $costoActual = DB::table('productos')->pluck('costo', 'id');

        $filas = DB::connection('pgsql_legacy')->select("
            SELECT DISTINCT ON (pro_id, cod_punto_emision) pro_id, cod_punto_emision, mvt_cant
            FROM erp_i_movpt_total
            ORDER BY pro_id, cod_punto_emision, mvt_id DESC
        ");

        $this->info('Combinaciones producto+bodega en erp_i_movpt_total: '.count($filas));

        $insertados = 0;
        $omitidosSinProducto = 0;
        $omitidosSinBodega = 0;
        $ahora = now();

        foreach ($filas as $fila) {
            $codigo = $legacyProductos[$fila->pro_id] ?? null;
            $productoId = $codigo ? ($productos[$codigo] ?? null) : null;

            if (!$productoId) {
                $omitidosSinProducto++;
                continue;
            }

            $mapaBod = self::MAPA_BODEGAS[$fila->cod_punto_emision] ?? null;
            if (!$mapaBod) {
                $omitidosSinBodega++;
                continue;
            }

            if (!$dryRun) {
                DB::table('inventario_saldos')->updateOrInsert(
                    ['producto_id' => $productoId, 'bodega_id' => $mapaBod['bodega_id']],
                    [
                        'stock_actual' => (float) $fila->mvt_cant,
                        'costo_promedio' => $costoActual[$productoId] ?? 0,
                        'updated_at' => $ahora,
                    ]
                );
            }

            $insertados++;
        }

        $this->info("Saldos ".($dryRun ? 'a insertar' : 'insertados').": {$insertados}");
        $this->warn("Omitidos sin producto mapeable: {$omitidosSinProducto}");
        $this->warn("Omitidos sin bodega mapeable: {$omitidosSinBodega}");

        if ($dryRun) {
            $this->info('Dry-run: no se escribió nada en inventario_saldos.');
        }

        return self::SUCCESS;
    }
}
