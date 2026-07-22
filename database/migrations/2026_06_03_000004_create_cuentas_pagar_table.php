<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cuentas_pagar')) {
            Schema::create('cuentas_pagar', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('proveedor_id');
                $table->foreign('proveedor_id')->references('id')->on('proveedores');
                $table->unsignedBigInteger('compra_id')->nullable();
                $table->decimal('monto', 14, 4);
                $table->decimal('saldo', 14, 4);
                $table->date('fecha_emision')->default(DB::raw('CURRENT_DATE'));
                $table->date('fecha_vencimiento');
                $table->boolean('aprobada')->default(false);
                $table->string('estado', 20)->default('pendiente');
                $table->unsignedBigInteger('asiento_pago_id')->nullable();
                $table->foreign('asiento_pago_id')
                      ->references('id')->on('asientos_contables');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });

            // FK diferida: compra_id → compras (ya creada en migración 000002)
            Schema::table('cuentas_pagar', function (Blueprint $table) {
                $table->foreign('compra_id')->references('id')->on('compras');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_pagar');
    }
};
