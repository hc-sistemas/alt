<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('log_documentos')) {
            return;
        }

        Schema::table('log_documentos', function (Blueprint $table) {
            if (!Schema::hasColumn('log_documentos', 'username')) {
                $table->string('username', 50)->nullable()->after('usuario_id');
            }
            if (!Schema::hasColumn('log_documentos', 'fecha')) {
                $table->timestamp('fecha')->nullable()->after('ip_address');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('log_documentos')) {
            return;
        }

        Schema::table('log_documentos', function (Blueprint $table) {
            if (Schema::hasColumn('log_documentos', 'username')) {
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('log_documentos', 'fecha')) {
                $table->dropColumn('fecha');
            }
        });
    }
};
