<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plan_cuentas') && !Schema::hasColumn('plan_cuentas', 'total_asientos')) {
            Schema::table('plan_cuentas', function (Blueprint $table) {
                $table->unsignedInteger('total_asientos')->default(0)->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('plan_cuentas') && Schema::hasColumn('plan_cuentas', 'total_asientos')) {
            Schema::table('plan_cuentas', function (Blueprint $table) {
                $table->dropColumn('total_asientos');
            });
        }
    }
};
