<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('listas_precio') && !Schema::hasColumn('listas_precio', 'descuento_max_promo')) {
            Schema::table('listas_precio', function (Blueprint $table) {
                $table->decimal('descuento_max_promo', 5, 2)->nullable()->after('descuento_max');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('listas_precio', 'descuento_max_promo')) {
            Schema::table('listas_precio', function (Blueprint $table) {
                $table->dropColumn('descuento_max_promo');
            });
        }
    }
};
