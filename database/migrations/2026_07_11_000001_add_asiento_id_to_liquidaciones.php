<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('liquidaciones') && !Schema::hasColumn('liquidaciones', 'asiento_id')) {
            Schema::table('liquidaciones', function (Blueprint $table) {
                $table->foreignId('asiento_id')->nullable()->after('total_liquidacion')
                    ->constrained('asientos_contables')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('liquidaciones', 'asiento_id')) {
            Schema::table('liquidaciones', function (Blueprint $table) {
                $table->dropConstrainedForeignId('asiento_id');
            });
        }
    }
};
