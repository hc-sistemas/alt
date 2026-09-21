<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índice único (empresa_id, numero) en asientos_contables.
 *
 * La numeración se generaba con MAX()+1 sin ningún candado, así que dos
 * usuarios guardando a la vez obtenían el MISMO número de asiento y la base
 * lo aceptaba sin protestar. El índice convierte esa carrera en un error
 * atrapable, y AsientoService::crear() reintenta con el siguiente número.
 *
 * También se agregan los índices que faltaban para los reportes contables,
 * que agrupan asiento_detalles por cuenta_id filtrando por asiento.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('asientos_contables')) {
            return;
        }

        // Si hubiera duplicados previos, el índice no se puede crear: se
        // renumeran primero para que la migración sea aplicable siempre.
        $duplicados = DB::table('asientos_contables')
            ->select('empresa_id', 'numero')
            ->groupBy('empresa_id', 'numero')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicados as $dup) {
            $ids = DB::table('asientos_contables')
                ->where('empresa_id', $dup->empresa_id)
                ->where('numero', $dup->numero)
                ->orderBy('id')
                ->pluck('id')
                ->slice(1);

            foreach ($ids as $i => $id) {
                DB::table('asientos_contables')
                    ->where('id', $id)
                    ->update(['numero' => substr($dup->numero . '-D' . ($i + 1), 0, 20)]);
            }
        }

        $existe = DB::selectOne(
            "SELECT 1 FROM pg_indexes WHERE tablename = 'asientos_contables' AND indexname = 'uq_asientos_empresa_numero'"
        );

        if (!$existe) {
            DB::statement(
                'CREATE UNIQUE INDEX uq_asientos_empresa_numero ON asientos_contables (empresa_id, numero)'
            );
        }

        if (Schema::hasTable('asiento_detalles')) {
            $idx = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE tablename = 'asiento_detalles' AND indexname = 'idx_detalles_cuenta'"
            );
            if (!$idx) {
                DB::statement('CREATE INDEX idx_detalles_cuenta ON asiento_detalles (cuenta_id)');
            }

            $idx2 = DB::selectOne(
                "SELECT 1 FROM pg_indexes WHERE tablename = 'asiento_detalles' AND indexname = 'idx_detalles_asiento'"
            );
            if (!$idx2) {
                DB::statement('CREATE INDEX idx_detalles_asiento ON asiento_detalles (asiento_id)');
            }
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_asientos_empresa_numero');
        DB::statement('DROP INDEX IF EXISTS idx_detalles_cuenta');
        DB::statement('DROP INDEX IF EXISTS idx_detalles_asiento');
    }
};
