<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-035 (02-01 §2.4): límites de descuento por rol.
 *
 *   Admin     10 % sin aprobación · aprueba hasta 100 %
 *   Vendedor   8 % sin aprobación · NO aprueba · tope 20 %
 *   Bodeguero  8 % sin aprobación · NO aprueba · tope 20 %
 *
 * Super Admin, Contador y Técnico no figuran en la tabla: no se tocan.
 * Idempotente y tolerante a las dos variantes del schema (nombres de
 * columnas de la tabla legacy según CLAUDE.md, y empresa_id opcional).
 */
return new class extends Migration
{
    private const LIMITES = [
        'admin'     => ['sin' => 10, 'puede' => true,  'con' => 100],
        'vendedor'  => ['sin' => 8,  'puede' => false, 'con' => 20],
        'bodeguero' => ['sin' => 8,  'puede' => false, 'con' => 20],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('limites_descuento') || !Schema::hasTable('perfiles')) {
            return;
        }

        $colSin = Schema::hasColumn('limites_descuento', 'porcentaje_maximo')
            ? 'porcentaje_maximo' : 'descuento_maximo_pct';
        $colCon = Schema::hasColumn('limites_descuento', 'porcentaje_aprobacion_max')
            ? 'porcentaje_aprobacion_max' : 'descuento_aprobacion_max_pct';
        $porEmpresa = Schema::hasColumn('limites_descuento', 'empresa_id');

        if (!Schema::hasColumn('limites_descuento', $colSin)
            || !Schema::hasColumn('limites_descuento', $colCon)) {
            return;
        }

        $empresas = $porEmpresa ? DB::table('empresas')->pluck('id')->all() : [null];
        $ahora = now();

        foreach (self::LIMITES as $nombre => $l) {
            $perfilId = DB::table('perfiles')->where('nombre', $nombre)->value('id');
            if (!$perfilId) {
                continue;
            }

            foreach ($empresas as $empresaId) {
                $clave = ['perfil_id' => $perfilId];
                if ($porEmpresa) {
                    $clave['empresa_id'] = $empresaId;
                }

                $valores = [
                    $colSin           => $l['sin'],
                    'puede_aprobar'   => $l['puede'],
                    $colCon           => $l['con'],
                ];
                if (Schema::hasColumn('limites_descuento', 'updated_at')) {
                    $valores['updated_at'] = $ahora;
                }

                if (DB::table('limites_descuento')->where($clave)->exists()) {
                    DB::table('limites_descuento')->where($clave)->update($valores);
                } else {
                    if (Schema::hasColumn('limites_descuento', 'created_at')) {
                        $valores['created_at'] = $ahora;
                    }
                    DB::table('limites_descuento')->insert($clave + $valores);
                }
            }
        }
    }

    public function down(): void
    {
        // Los valores anteriores no se conocen: no se revierte.
    }
};
