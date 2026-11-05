<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo E, Tarjetas (02-07 §2.3 y §2.2): catálogo de procesadores con comisión por tipo
 * de tarjeta, retenciones por defecto y plazo de liquidación; el lote guarda su procesador
 * y tipo; la liquidación guarda el IVA de la comisión, la diferencia y su observación.
 * Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tarjeta_procesadores')) {
            Schema::create('tarjeta_procesadores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('nombre', 100);
                $table->unsignedBigInteger('banco_destino_id')->nullable();
                // Porcentajes por defecto: solo pre-llenan; el reporte real del procesador manda.
                $table->decimal('comision_credito', 6, 3)->default(0);
                $table->decimal('comision_debito', 6, 3)->default(0);
                $table->decimal('comision_diferido', 6, 3)->default(0);
                $table->decimal('retencion_iva_pct', 6, 3)->default(0);
                $table->decimal('retencion_ir_pct', 6, 3)->default(0);
                $table->unsignedSmallInteger('plazo_dias')->default(3); // días esperados entre venta y depósito
                $table->boolean('estado')->default(true);
                $table->timestamps();
                $table->index('empresa_id');
            });
        }

        if (Schema::hasTable('datafast_lotes')) {
            Schema::table('datafast_lotes', function (Blueprint $table) {
                if (!Schema::hasColumn('datafast_lotes', 'procesador_id')) {
                    $table->unsignedBigInteger('procesador_id')->nullable();
                }
                if (!Schema::hasColumn('datafast_lotes', 'tipo_tarjeta')) {
                    $table->string('tipo_tarjeta', 10)->nullable(); // credito | debito | diferido
                }
            });
        }

        if (Schema::hasTable('datafast_liquidaciones')) {
            Schema::table('datafast_liquidaciones', function (Blueprint $table) {
                if (!Schema::hasColumn('datafast_liquidaciones', 'iva_comision')) {
                    $table->decimal('iva_comision', 14, 4)->default(0);
                }
                if (!Schema::hasColumn('datafast_liquidaciones', 'diferencia')) {
                    $table->decimal('diferencia', 14, 4)->default(0);
                }
                if (!Schema::hasColumn('datafast_liquidaciones', 'observacion')) {
                    $table->text('observacion')->nullable();
                }
                if (!Schema::hasColumn('datafast_liquidaciones', 'requiere_revision')) {
                    $table->boolean('requiere_revision')->default(false);
                }
            });
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
