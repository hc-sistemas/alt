<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuarios')) {
                    Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('perfil_id')->constrained('perfiles');
            $table->foreignId('centro_costo_id')->nullable()->constrained('centros_costo')->nullOnDelete();
            $table->string('nombre');
            $table->string('email')->unique();
            $table->string('username')->unique();
            $table->string('telefono', 20)->nullable();
            $table->string('password');
            $table->string('codigo_aprobacion')->nullable()->comment('PIN hasheado para aprobaciones especiales');
            $table->string('avatar')->nullable();
            $table->boolean('estado')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamp('ultimo_acceso')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('empresa_usuario', function (Blueprint $table) {
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->cascadeOnDelete();
            $table->primary(['empresa_id', 'usuario_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('empresa_usuario');
        Schema::dropIfExists('usuarios');
    }
};
