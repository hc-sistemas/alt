<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('bancos_cajas', 'centro_costo_id')) {
            Schema::table('bancos_cajas', function (Blueprint $table) {
                $table->foreignId('centro_costo_id')->nullable()->after('cuenta_id')
                    ->constrained('centros_costo')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('bancos_cajas', 'centro_costo_id')) {
            Schema::table('bancos_cajas', function (Blueprint $table) {
                $table->dropConstrainedForeignId('centro_costo_id');
            });
        }
    }
};
