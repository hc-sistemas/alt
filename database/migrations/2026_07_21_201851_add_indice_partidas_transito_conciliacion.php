<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * partidas_transito.conciliacion_id sin índice: cada llamada a
     * autoMatchPartidas() (Conciliación Bancaria) filtra por esta columna
     * y hacía full table scan, detectado en pruebas de volumen (FASE 4).
     */
    public function up(): void
    {
        if (!Schema::hasTable('partidas_transito')) return;
        if ($this->indiceExiste('partidas_transito', 'idx_partidas_transito_conciliacion')) return;

        Schema::table('partidas_transito', function ($table) {
            $table->index('conciliacion_id', 'idx_partidas_transito_conciliacion');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('partidas_transito')) return;
        if (!$this->indiceExiste('partidas_transito', 'idx_partidas_transito_conciliacion')) return;

        Schema::table('partidas_transito', function ($table) {
            $table->dropIndex('idx_partidas_transito_conciliacion');
        });
    }

    private function indiceExiste(string $tabla, string $nombreIndice): bool
    {
        $fila = DB::selectOne(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$tabla, $nombreIndice]
        );
        return $fila !== null;
    }
};
