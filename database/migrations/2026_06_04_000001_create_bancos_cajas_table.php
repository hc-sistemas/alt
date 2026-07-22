<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bancos_cajas')) {
            Schema::create('bancos_cajas', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('cuenta_id')->nullable();
                $table->foreign('cuenta_id')->references('id')->on('plan_cuentas');
                $table->string('tipo', 20);
                // 'banco','caja','caja_chica','tarjeta'
                $table->string('nombre', 150);
                $table->string('num_cuenta', 30)->nullable();
                $table->string('tipo_cuenta', 20)->nullable();
                // 'ahorros','corriente'
                $table->decimal('saldo_inicial', 14, 4)->default(0);
                $table->decimal('saldo_actual',  14, 4)->default(0);
                $table->boolean('estado')->default(true);
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bancos_cajas');
    }
};
