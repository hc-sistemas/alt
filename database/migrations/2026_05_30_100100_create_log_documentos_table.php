<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('log_documentos')) {
                    Schema::create('log_documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('empresa_id')->nullable()->constrained('empresas')->nullOnDelete();
            $table->string('accion', 30)->comment('crear, editar, anular, eliminar, imprimir');
            $table->string('modulo', 50);
            $table->string('tabla');
            $table->unsignedBigInteger('registro_id')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('log_documentos');
    }
};
