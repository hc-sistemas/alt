<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usuarios', 'colaborador_id')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->foreignId('colaborador_id')->nullable()->unique()
                    ->after('centro_costo_id')
                    ->constrained('colaboradores')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('usuarios', 'colaborador_id')) {
            Schema::table('usuarios', function (Blueprint $table) {
                $table->dropConstrainedForeignId('colaborador_id');
            });
        }
    }
};
