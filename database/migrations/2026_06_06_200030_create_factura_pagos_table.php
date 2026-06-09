<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('factura_pagos')) {
            Schema::create('factura_pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('factura_id');
                $table->string('forma_pago', 30);
                $table->decimal('valor', 14, 4)->default(0);
                $table->smallInteger('dias_credito')->default(0);
                $table->date('fecha_vencimiento')->nullable();
                $table->string('banco', 100)->nullable();
                $table->string('num_cheque', 50)->nullable();
                $table->string('num_voucher', 50)->nullable();
                $table->string('estado', 20)->default('pendiente');

                $table->foreign('factura_id')->references('id')->on('facturas')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_pagos');
    }
};
