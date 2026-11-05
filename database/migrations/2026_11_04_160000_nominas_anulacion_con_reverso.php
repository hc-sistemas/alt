<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nómina (02-09 §2.5): una nómina procesada no se modifica, se anula con reverso. Se guarda el estado de los
 * préstamos y anticipos que descontó (para restituirlos) y quién/cuándo/por qué la anuló. Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nominas')) {
            return;
        }

        Schema::table('nominas', function (Blueprint $t) {
            if (!Schema::hasColumn('nominas', 'snapshot_prestamos')) {
                $t->text('snapshot_prestamos')->nullable();
            }
            if (!Schema::hasColumn('nominas', 'motivo_anulacion')) {
                $t->text('motivo_anulacion')->nullable();
            }
            if (!Schema::hasColumn('nominas', 'anulada_por')) {
                $t->unsignedBigInteger('anulada_por')->nullable();
            }
            if (!Schema::hasColumn('nominas', 'fecha_anulacion')) {
                $t->timestamp('fecha_anulacion')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Sin reversa: no se descartan datos de auditoría.
    }
};
