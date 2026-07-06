<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('retencion_detalles') && !Schema::hasColumn('retencion_detalles', 'descripcion')) {
            Schema::table('retencion_detalles', function (Blueprint $table) {
                $table->string('descripcion')->nullable()->after('codigo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('retencion_detalles') && Schema::hasColumn('retencion_detalles', 'descripcion')) {
            Schema::table('retencion_detalles', function (Blueprint $table) {
                $table->dropColumn('descripcion');
            });
        }
    }
};
