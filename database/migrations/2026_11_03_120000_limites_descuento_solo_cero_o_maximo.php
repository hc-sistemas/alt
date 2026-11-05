<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-035: «vendedor y bodeguero solo pueden dar 0 u el máximo, sin valores intermedios». Esa regla estaba fija
 * por nombre de perfil en el código; ahora es una opción del perfil en Configuración → Permisos → Límites de
 * descuento. Valor inicial: activa para vendedor y bodeguero (lo que ya se aplicaba). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('limites_descuento')) {
            return;
        }
        if (!Schema::hasColumn('limites_descuento', 'solo_cero_o_maximo')) {
            Schema::table('limites_descuento', function (Blueprint $table) {
                $table->boolean('solo_cero_o_maximo')->default(false);
            });
            $ids = DB::table('perfiles')->whereIn('nombre', ['vendedor', 'bodeguero'])->pluck('id');
            DB::table('limites_descuento')->whereIn('perfil_id', $ids)->update(['solo_cero_o_maximo' => true]);
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
