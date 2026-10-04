<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Firma electrónica por empresa: ruta del .p12 y su clave (cifrada con Crypt, por eso
    // no cabe en VARCHAR(200)). En producción las columnas ya existen (schema legacy);
    // en entornos creados desde las migraciones faltan, así que se agregan.
    public function up(): void
    {
        if (!Schema::hasTable('empresas')) {
            return;
        }

        if (!Schema::hasColumn('empresas', 'firma_electronica_path')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->string('firma_electronica_path', 500)->nullable();
            });
        }

        if (!Schema::hasColumn('empresas', 'firma_electronica_pass')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->string('firma_electronica_pass', 1000)->nullable();
            });
        } else {
            DB::statement('ALTER TABLE empresas ALTER COLUMN firma_electronica_pass TYPE VARCHAR(1000)');
        }
    }

    public function down(): void {}
};
