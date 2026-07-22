<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('cuentas_cobrar_cobros')) return;

        Schema::create('cuentas_cobrar_cobros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuenta_cobrar_id')->constrained('cuentas_cobrar')->onDelete('cascade');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->onDelete('set null');
            $table->date('fecha');
            $table->decimal('valor', 12, 4);
            $table->string('forma_pago', 50);
            $table->string('observacion', 300)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuentas_cobrar_cobros');
    }
};
