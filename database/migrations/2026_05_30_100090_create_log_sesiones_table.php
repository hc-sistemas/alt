<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('log_sesiones')) {
                    Schema::create('log_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->string('email')->nullable();
            $table->enum('tipo', ['login_ok', 'login_fail', 'logout', 'forzado'])->default('login_ok');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('log_sesiones');
    }
};
