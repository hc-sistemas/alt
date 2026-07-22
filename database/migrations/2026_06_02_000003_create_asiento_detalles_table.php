<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('asiento_detalles')) {
            Schema::create('asiento_detalles', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asiento_id');
                $table->foreign('asiento_id')
                      ->references('id')
                      ->on('asientos_contables')
                      ->onDelete('cascade');
                $table->unsignedBigInteger('cuenta_id');
                $table->foreign('cuenta_id')->references('id')->on('plan_cuentas');
                $table->unsignedBigInteger('centro_costo_id')->nullable();
                $table->foreign('centro_costo_id')->references('id')->on('centros_costo');
                $table->string('descripcion', 300)->nullable();
                $table->decimal('debe', 14, 4)->default(0);
                $table->decimal('haber', 14, 4)->default(0);
                // SIN timestamps — líneas de asiento son inmutables
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asiento_detalles');
    }
};
