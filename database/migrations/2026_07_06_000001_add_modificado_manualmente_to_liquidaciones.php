<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('liquidaciones') && !Schema::hasColumn('liquidaciones', 'modificado_manualmente')) {
            Schema::table('liquidaciones', function (Blueprint $table) {
                $table->boolean('modificado_manualmente')->default(false)->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('liquidaciones', 'modificado_manualmente')) {
            Schema::table('liquidaciones', function (Blueprint $table) {
                $table->dropColumn('modificado_manualmente');
            });
        }
    }
};
