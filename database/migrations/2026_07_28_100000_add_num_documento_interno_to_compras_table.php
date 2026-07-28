<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras') && !Schema::hasColumn('compras', 'num_documento_interno')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->string('num_documento_interno', 20)->nullable()->after('num_documento');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compras') && Schema::hasColumn('compras', 'num_documento_interno')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropColumn('num_documento_interno');
            });
        }
    }
};
