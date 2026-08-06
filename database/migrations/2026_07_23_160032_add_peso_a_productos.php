<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * peso (kg) por producto: base para el método de prorrateo por peso al
     * liquidar una importación.
     */
    public function up(): void
    {
        if (Schema::hasTable('productos') && !Schema::hasColumn('productos', 'peso')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->decimal('peso', 10, 4)->nullable()->default(0)->after('unidad');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('productos') && Schema::hasColumn('productos', 'peso')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('peso');
            });
        }
    }
};
