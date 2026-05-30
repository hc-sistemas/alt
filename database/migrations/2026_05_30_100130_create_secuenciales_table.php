<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('secuenciales')) {
                    Schema::create('secuenciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('tipo_documento', 30)->comment('factura, nota_credito, proforma, retencion, etc.');
            $table->string('establecimiento', 3)->default('001');
            $table->string('punto_emision', 3)->default('001');
            $table->unsignedBigInteger('siguiente')->default(1);
            $table->timestamps();

            $table->unique(['empresa_id', 'tipo_documento', 'establecimiento', 'punto_emision']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('secuenciales');
    }
};
