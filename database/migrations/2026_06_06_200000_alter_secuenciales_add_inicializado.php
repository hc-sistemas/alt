<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('secuenciales') && !Schema::hasColumn('secuenciales', 'inicializado_desde_migracion')) {
            Schema::table('secuenciales', function (Blueprint $table) {
                $table->boolean('inicializado_desde_migracion')->default(false);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('secuenciales') && Schema::hasColumn('secuenciales', 'inicializado_desde_migracion')) {
            Schema::table('secuenciales', function (Blueprint $table) {
                $table->dropColumn('inicializado_desde_migracion');
            });
        }
    }
};
