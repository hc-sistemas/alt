<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('compras')) {
            Schema::table('compras', function (Blueprint $table) {
                if (!Schema::hasColumn('compras', 'retencion_ir')) {
                    $table->decimal('retencion_ir', 14, 4)->default(0)->after('total');
                }
                if (!Schema::hasColumn('compras', 'retencion_iva')) {
                    $table->decimal('retencion_iva', 14, 4)->default(0)->after('retencion_ir');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compras')) {
            Schema::table('compras', function (Blueprint $table) {
                $table->dropColumn(array_filter(['retencion_ir', 'retencion_iva'], fn($c) => Schema::hasColumn('compras', $c)));
            });
        }
    }
};
