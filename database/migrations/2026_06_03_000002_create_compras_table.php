<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('compras')) {
            Schema::create('compras', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->foreign('centro_costo_id')->references('id')->on('centros_costo');
                $table->unsignedBigInteger('proveedor_id');
                $table->foreign('proveedor_id')->references('id')->on('proveedores');
                $table->unsignedBigInteger('importacion_id')->nullable();
                // FK a importaciones se agrega en migración 2026_06_03_000005
                $table->unsignedBigInteger('bodega_id')->nullable();
                // FK a bodegas se agrega cuando se cree el módulo de inventario
                $table->string('tipo_documento', 10)->default('FAC');
                $table->string('num_documento', 30);
                $table->string('num_autorizacion', 49)->nullable();
                $table->date('fecha_emision');
                $table->date('fecha_registro')->default(DB::raw('CURRENT_DATE'));
                $table->date('fecha_vencimiento')->nullable();
                $table->smallInteger('dias_credito')->default(0);
                $table->decimal('subtotal_0',    14, 4)->default(0);
                $table->decimal('subtotal_iva',  14, 4)->default(0);
                $table->decimal('total_iva',     14, 4)->default(0);
                $table->decimal('total_ice',     14, 4)->default(0);
                $table->decimal('total',         14, 4)->default(0);
                $table->boolean('iva_asumido')->default(false);
                $table->boolean('gasto_no_deducible')->default(false);
                $table->smallInteger('sustento_tributario')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->foreign('asiento_id')->references('id')->on('asientos_contables');
                $table->boolean('tiene_pago')->default(false);
                $table->string('concepto', 500)->nullable();
                $table->string('estado', 20)->default('activa');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('compras');
    }
};
