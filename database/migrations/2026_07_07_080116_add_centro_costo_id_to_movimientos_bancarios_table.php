<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('movimientos_bancarios', 'centro_costo_id')) {
            Schema::table('movimientos_bancarios', function (Blueprint $table) {
                $table->foreignId('centro_costo_id')->nullable()
                    ->after('cuenta_contrapartida_id')
                    ->constrained('centros_costo')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('movimientos_bancarios', 'centro_costo_id')) {
            Schema::table('movimientos_bancarios', function (Blueprint $table) {
                $table->dropConstrainedForeignId('centro_costo_id');
            });
        }
    }
};
