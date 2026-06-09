<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prefactura_abonos')) {
            Schema::create('prefactura_abonos', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('prefactura_id');
                $table->date('fecha')->useCurrent();
                $table->decimal('valor', 14, 4);
                $table->string('forma_pago', 30)->nullable();
                $table->string('banco', 100)->nullable();
                $table->string('num_comprobante', 50)->nullable();
                $table->unsignedInteger('asiento_id')->nullable();
                $table->unsignedInteger('usuario_id')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('prefactura_id')->references('id')->on('prefacturas');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('prefactura_abonos');
    }
};
