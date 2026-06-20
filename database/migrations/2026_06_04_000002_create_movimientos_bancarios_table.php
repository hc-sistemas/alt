<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('movimientos_bancarios')) {
            Schema::create('movimientos_bancarios', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('banco_caja_id');
                $table->foreign('banco_caja_id')->references('id')->on('bancos_cajas');
                $table->string('tipo', 20);
                // 'ingreso','egreso'
                $table->string('sub_tipo', 30)->nullable();
                // 'transferencia','cheque','efectivo','deposito'
                $table->date('fecha')->default(DB::raw('CURRENT_DATE'));
                $table->decimal('monto', 14, 4);
                // Beneficiario
                $table->string('persona_tipo', 20)->nullable();
                // 'cliente','proveedor','empleado','otro'
                $table->integer('persona_id')->nullable();
                $table->string('beneficiario', 200)->nullable();
                // Referencia
                $table->string('num_documento', 50)->nullable();
                $table->string('num_cheque', 50)->nullable();
                $table->date('fecha_cheque')->nullable();
                $table->string('descripcion', 500)->nullable();
                // Documento origen
                $table->string('documento_tipo', 20)->nullable();
                $table->integer('documento_id')->nullable();
                // Contabilidad
                $table->unsignedBigInteger('cuenta_contrapartida_id')->nullable();
                $table->foreign('cuenta_contrapartida_id')
                      ->references('id')->on('plan_cuentas');
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->foreign('asiento_id')->references('id')->on('asientos_contables');
                // Control
                $table->boolean('conciliado')->default(false);
                $table->boolean('es_postfechado')->default(false);
                $table->boolean('anulado')->default(false);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });

            Schema::table('movimientos_bancarios', function (Blueprint $table) {
                $table->index('banco_caja_id', 'idx_mov_banco');
                $table->index('fecha',         'idx_mov_fecha');
                $table->index('empresa_id',    'idx_mov_empresa');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_bancarios');
    }
};
