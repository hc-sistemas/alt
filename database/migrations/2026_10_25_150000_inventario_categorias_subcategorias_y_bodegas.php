<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo F, Inventario (02-02 §2.3, §2.4, §2.5 y 01 §6):
 *  - categorías y subcategorías oficiales (PC-CAT-01 a PC-CAT-20) en `categorias_producto`;
 *  - cada subcategoría queda enlazada a sus tres cuentas (tabla subcategoria_cuentas, módulo B);
 *  - marca de «exige número de serie» en la categoría (no en `productos`);
 *  - las mismas bodegas en los dos RUC (a Import le faltaban Cuarentena e Importaciones).
 *
 * NO toca la tabla `productos`: los productos siguen apuntando a su `categoria_id` actual. Las
 * categorías existentes conservan su id (solo cambia el nombre) y «Accesorios» queda marcada
 * «por reclasificar» hasta repartir sus productos. Idempotente.
 */
return new class extends Migration
{
    /** Nombre anterior (en mayúsculas) => nombre oficial. */
    private const RENOMBRES = [
        'DJS'          => 'Artículos y Equipos de DJ',
        'ILUMINACION'  => 'Iluminación Profesional',
        'CABLES'       => 'Cables, Conectores y Estructuras',
        'SONIDO'       => 'Parlantes y sistemas de sonido',
        'VIDEO'        => 'Pantallas LED y Video',
        'PISOS LED'    => 'PISOS LED (descontinuada)',
        'COMPUTADORAS' => 'Computadoras, partes y repuestos',
        'REPUESTOS'    => 'Repuestos e Insumos de Taller',
        'ACCESORIOS'   => 'ACCESORIOS (por reclasificar)', // ya no es categoría (PC-CAT-R05)
    ];

    /** Categoría => subcategorías (02-02 §2.3). Las demás categorías no tienen subcategorías. */
    private const SUBCATEGORIAS = [
        'Parlantes y sistemas de sonido' => ['Parlantes Activos (Amplificados)', 'Parlantes Pasivos', 'Subwoofers / Bajos', 'Sistemas Line Array', 'Parlantes de Instalación Comercial'],
        'Artículos y Equipos de DJ'      => ['Controladores de DJ', 'Mezcladoras / Mixers', 'Consolas análogas y digitales', 'Tornamesas / Turntables'],
        'Iluminación Profesional'        => ['Luces Robóticas, Beams, Spots', 'Tachos LED / Iluminación Estática', 'Controladores DMX alámbricos e inalámbricos'],
        'Pantallas LED y Video'          => ['Módulos y Gabinetes LED', 'Procesadores y Equipos de Control de Video'],
        'Regalos'                        => ['Camisetas de regalo General', 'Demás regalos'],
    ];

    /** Categorías cuyos productos exigen número de serie: DJ / controladores, computadoras y pantallas LED. */
    private const EXIGEN_SERIE = ['Artículos y Equipos de DJ', 'Computadoras, partes y repuestos', 'Pantallas LED y Video'];

    /** PC-CAT => [categoría, subcategoría o null si la cuenta cuelga de la categoría]. */
    private const MAPA_CAT = [
        'PC-CAT-01' => ['Parlantes y sistemas de sonido', 'Parlantes Activos (Amplificados)'],
        'PC-CAT-02' => ['Parlantes y sistemas de sonido', 'Parlantes Pasivos'],
        'PC-CAT-03' => ['Parlantes y sistemas de sonido', 'Subwoofers / Bajos'],
        'PC-CAT-04' => ['Parlantes y sistemas de sonido', 'Sistemas Line Array'],
        'PC-CAT-05' => ['Parlantes y sistemas de sonido', 'Parlantes de Instalación Comercial'],
        'PC-CAT-06' => ['Artículos y Equipos de DJ', 'Controladores de DJ'],
        'PC-CAT-07' => ['Artículos y Equipos de DJ', 'Mezcladoras / Mixers'],
        'PC-CAT-08' => ['Artículos y Equipos de DJ', 'Consolas análogas y digitales'],
        'PC-CAT-09' => ['Artículos y Equipos de DJ', 'Tornamesas / Turntables'],
        'PC-CAT-10' => ['Iluminación Profesional', 'Luces Robóticas, Beams, Spots'],
        'PC-CAT-11' => ['Iluminación Profesional', 'Tachos LED / Iluminación Estática'],
        'PC-CAT-12' => ['Iluminación Profesional', 'Controladores DMX alámbricos e inalámbricos'],
        'PC-CAT-13' => ['Pantallas LED y Video', 'Módulos y Gabinetes LED'],
        'PC-CAT-14' => ['Pantallas LED y Video', 'Procesadores y Equipos de Control de Video'],
        'PC-CAT-15' => ['Cables, Conectores y Estructuras', null],
        'PC-CAT-16' => ['Repuestos e Insumos de Taller', null],
        'PC-CAT-17' => ['Regalos', 'Camisetas de regalo General'],
        'PC-CAT-18' => ['Regalos', 'Demás regalos'],
        'PC-CAT-19' => ['Computadoras, partes y repuestos', null],
        'PC-CAT-20' => ['PISOS LED (descontinuada)', null],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('categorias_producto')) {
            return;
        }

        DB::transaction(function () {
            if (!Schema::hasColumn('categorias_producto', 'requiere_serie')) {
                Schema::table('categorias_producto', function (Blueprint $table) {
                    $table->boolean('requiere_serie')->default(false);
                });
            }

            $this->categorias();
            $this->enlazarCuentas();
            $this->bodegasImport();
        });
    }

    private function raiz(string $nombre): int
    {
        $id = DB::table('categorias_producto')->whereNull('categoria_padre_id')->where('nombre', $nombre)->value('id');

        return $id ?: DB::table('categorias_producto')->insertGetId(
            ['nombre' => $nombre, 'categoria_padre_id' => null, 'estado' => true]
        );
    }

    private function categorias(): void
    {
        // 1. Renombrar las existentes sin cambiar su id (los productos siguen enlazados)
        $raices = DB::table('categorias_producto')->whereNull('categoria_padre_id')->get(['id', 'nombre']);
        foreach ($raices as $r) {
            $nuevo = self::RENOMBRES[mb_strtoupper(trim($r->nombre))] ?? null;
            if ($nuevo && !DB::table('categorias_producto')->whereNull('categoria_padre_id')->where('nombre', $nuevo)->exists()) {
                DB::table('categorias_producto')->where('id', $r->id)->update(['nombre' => $nuevo]);
            }
        }

        // 2. Categorías oficiales que no existan, y «Regalos»
        foreach (self::RENOMBRES as $anterior => $oficial) {
            if ($anterior !== 'ACCESORIOS') {
                $this->raiz($oficial);
            }
        }
        $this->raiz('Regalos');

        // 3. Subcategorías
        foreach (self::SUBCATEGORIAS as $categoria => $subs) {
            $padreId = $this->raiz($categoria);
            foreach ($subs as $sub) {
                $existe = DB::table('categorias_producto')->where('categoria_padre_id', $padreId)->where('nombre', $sub)->exists();
                if (!$existe) {
                    DB::table('categorias_producto')->insert(['nombre' => $sub, 'categoria_padre_id' => $padreId, 'estado' => true]);
                }
            }
        }

        // 4. Exige número de serie (la categoría y sus subcategorías)
        foreach (self::EXIGEN_SERIE as $categoria) {
            $id = $this->raiz($categoria);
            DB::table('categorias_producto')->where(fn ($q) => $q->where('id', $id)->orWhere('categoria_padre_id', $id))
                ->update(['requiere_serie' => true]);
        }
    }

    private function enlazarCuentas(): void
    {
        if (!Schema::hasTable('subcategoria_cuentas')) {
            return;
        }
        foreach (self::MAPA_CAT as $codigo => [$categoria, $sub]) {
            $padreId = $this->raiz($categoria);
            $id = $sub === null
                ? $padreId
                : DB::table('categorias_producto')->where('categoria_padre_id', $padreId)->where('nombre', $sub)->value('id');

            DB::table('subcategoria_cuentas')->where('codigo', $codigo)->update(['categoria_producto_id' => $id]);
        }
    }

    /** Las mismas bodegas en los dos RUC (02-02 §2.5): se copian las que falten de la empresa 1 a la 2. */
    private function bodegasImport(): void
    {
        if (!Schema::hasTable('bodegas') || !DB::table('empresas')->where('id', 2)->exists()) {
            return;
        }
        $centroImport = DB::table('centros_costo')->where('empresa_id', 2)->where('tipo', 'empresa')->value('id');

        foreach (DB::table('bodegas')->where('empresa_id', 1)->get() as $b) {
            $existe = DB::table('bodegas')->where('empresa_id', 2)->where('nombre', $b->nombre)->exists();
            if (!$existe) {
                DB::table('bodegas')->insert([
                    'empresa_id'      => 2,
                    'centro_costo_id' => $b->centro_costo_id ? $centroImport : null,
                    'nombre'          => $b->nombre,
                    'tipo'            => $b->tipo,
                    'es_virtual'      => $b->es_virtual,
                    'estado'          => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Sin reversa.
    }
};
