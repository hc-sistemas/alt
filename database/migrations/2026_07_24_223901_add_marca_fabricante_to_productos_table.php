<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('productos', 'marca_fabricante')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->string('marca_fabricante', 150)->nullable()->after('marca_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('productos', 'marca_fabricante')) {
            Schema::table('productos', function (Blueprint $table) {
                $table->dropColumn('marca_fabricante');
            });
        }
    }
};
