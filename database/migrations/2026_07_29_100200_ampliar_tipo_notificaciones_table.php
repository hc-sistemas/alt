<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `notificaciones.tipo` se creó como varchar(20) pensado para valores
 * cortos como 'info'/'success'/'warning'/'danger', pero desde entonces
 * cada módulo con exportación en segundo plano (Asientos, Compras,
 * Proveedores, Cuentas por Pagar, Reportes Contables) lo reutiliza con
 * tags descriptivos mucho más largos — 'exportacion_proveedores' (23),
 * 'exportacion_asientos_error' (26), etc. Postgres no trunca en
 * silencio: cualquier insert con un tipo de más de 20 caracteres lanza
 * un QueryException real (confirmado: así falló
 * ExportarLibroDiarioJob::failed() al intentar registrar su propia
 * notificación de error). Se amplía a 50 para dar margen holgado a los
 * tipos actuales y futuros.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notificaciones')) {
            return;
        }

        $longitudActual = DB::selectOne(
            "SELECT character_maximum_length AS len FROM information_schema.columns WHERE table_name = 'notificaciones' AND column_name = 'tipo'"
        )->len ?? null;

        if ($longitudActual !== null && (int) $longitudActual < 50) {
            DB::statement('ALTER TABLE notificaciones ALTER COLUMN tipo TYPE VARCHAR(50)');
        }
    }

    public function down(): void
    {
        // No revertir a 20: reduciría un límite ya usado en producción
        // por tipos más largos y rompería inserts existentes.
    }
};
