<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D1 (CHECKLIST_ERRORES_COMPLICACIONES.md): un ingreso de Taller solo puede
 * tener UNA orden de trabajo — decisión del cliente para poder fusionar
 * Ingresos y Órdenes en una sola pantalla (ver OrdenesTrabajo/Show.tsx e
 * IngresoController::show(), que ahora redirige directo a la orden). Antes el
 * modelo permitía 1:N sin que nada lo impidiera; en la práctica
 * IngresoController::store() siempre había creado una sola orden por ingreso,
 * así que este candado solo formaliza lo que ya pasaba.
 *
 * Si algún ingreso ya tuviera más de una orden (no debería, pero por si un
 * dato viejo lo tiene), no se pierde ninguna: se deja la más reciente con el
 * ingreso_id y a las demás se les pone NULL, para no bloquear la migración.
 * Quedan huérfanas pero visibles en Órdenes de Trabajo; no se borran.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('taller_ordenes_trabajo')) {
            return;
        }

        $duplicados = DB::table('taller_ordenes_trabajo')
            ->select('ingreso_id')
            ->groupBy('ingreso_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('ingreso_id');

        foreach ($duplicados as $ingresoId) {
            $idsAConservar = DB::table('taller_ordenes_trabajo')
                ->where('ingreso_id', $ingresoId)
                ->orderByDesc('id')
                ->value('id');

            DB::table('taller_ordenes_trabajo')
                ->where('ingreso_id', $ingresoId)
                ->where('id', '!=', $idsAConservar)
                ->update(['ingreso_id' => null]);
        }

        if (!$this->tieneIndice('taller_ordenes_trabajo', 'taller_ordenes_trabajo_ingreso_id_unique')) {
            Schema::table('taller_ordenes_trabajo', function (Blueprint $table) {
                $table->unique('ingreso_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('taller_ordenes_trabajo')
            && $this->tieneIndice('taller_ordenes_trabajo', 'taller_ordenes_trabajo_ingreso_id_unique')) {
            Schema::table('taller_ordenes_trabajo', function (Blueprint $table) {
                $table->dropUnique('taller_ordenes_trabajo_ingreso_id_unique');
            });
        }
    }

    private function tieneIndice(string $tabla, string $indice): bool
    {
        return DB::table('pg_indexes')
            ->where('tablename', $tabla)
            ->where('indexname', $indice)
            ->exists();
    }
};
