<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('nominas')) {
            return;
        }

        Schema::table('nominas', function (Blueprint $table) {
            if (!Schema::hasColumn('nominas', 'fecha_pago')) {
                $table->date('fecha_pago')->nullable();
            }
            if (!Schema::hasColumn('nominas', 'tipo_comprobante')) {
                $table->string('tipo_comprobante', 30)->nullable();
            }
            if (!Schema::hasColumn('nominas', 'num_comprobante')) {
                $table->string('num_comprobante', 100)->nullable();
            }
            if (!Schema::hasColumn('nominas', 'asiento_pago_id')) {
                $table->unsignedBigInteger('asiento_pago_id')->nullable();
            }
        });
    }

    public function down(): void {}
};
