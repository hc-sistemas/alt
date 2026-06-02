<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('importaciones')) {
            Schema::create('importaciones', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->foreign('empresa_id')->references('id')->on('empresas');
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->foreign('proveedor_id')->references('id')->on('proveedores');
                $table->string('nombre', 200);
                $table->string('num_invoice', 100)->nullable();
                $table->string('agente_aduanero', 200)->nullable();
                $table->string('pais_embarque', 100)->nullable();
                $table->decimal('costo_fob',           14, 4)->default(0);
                $table->string('divisa', 10)->default('USD');
                $table->date('fecha_partida')->nullable();
                $table->date('fecha_llegada')->nullable();
                $table->date('fecha_liquidacion')->nullable();
                $table->decimal('total_costos_extra',  14, 4)->default(0);
                $table->decimal('costo_total',         14, 4)->default(0);
                $table->string('metodo_prorrateo', 20)->default('cantidad');
                $table->string('estado', 20)->default('en_transito');
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->foreign('created_by')->references('id')->on('usuarios');
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            });

            // FK diferida: compras.importacion_id → importaciones
            Schema::table('compras', function (Blueprint $table) {
                $table->foreign('importacion_id')->references('id')->on('importaciones');
            });
        }
    }

    public function down(): void
    {
        Schema::table('compras', function (Blueprint $table) {
            $table->dropForeign(['importacion_id']);
        });
        Schema::dropIfExists('importaciones');
    }
};
