<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('empresas') && !Schema::hasColumn('empresas', 'firma_electronica_vence')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->date('firma_electronica_vence')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'firma_electronica_vence')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn('firma_electronica_vence');
            });
        }
    }
};
