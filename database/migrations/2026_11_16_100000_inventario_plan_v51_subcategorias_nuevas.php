<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Habilitación del inventario (decisiones del contador y de Edwin, 05 al 12/11/2026; documentos de la carpeta «Corregidos»):
 *
 *  - Plan de cuentas V5.1: se agregan 7 cuentas (Micrófonos, Audífonos y la depreciación acumulada de herramientas de taller)
 *    y se elimina la 4.03.02 «Otros ingresos» (el parámetro `cta_otros_ingresos` pasa a 4.04, que está en el plan V5).
 *  - Subcategorías nuevas de producto con sus cuentas (PC-CAT-21 a 28), ampliación del documento 01 §6:
 *      Micrófonos y Audífonos con cuentas propias; Láseres, Máquinas de efectos e «Inventario sin clasificar» con las cuentas
 *      «sin clasificar» (excepción aprobada el 05/11); Amplificadores asimilada a Parlantes Activos (PC-CAT-01);
 *      Servicio técnico (4.01.08) y Eventos (4.04) como servicios, sin inventario ni costo.
 *
 * Idempotente: puede correr varias veces sin duplicar nada.
 */
return new class extends Migration
{
    /** código => [nombre, tipo, padre] (nombres exactos del plan V5.1) */
    private const CUENTAS_NUEVAS = [
        '1.01.04.01.06' => ['Micrófonos', 'activo', '1.01.04.01'],
        '1.01.04.02.05' => ['Audífonos', 'activo', '1.01.04.02'],
        '4.01.01.06'    => ['Venta de Micrófonos', 'ingreso', '4.01.01'],
        '4.01.02.05'    => ['Venta de Audífonos', 'ingreso', '4.01.02'],
        '5.01.01.06'    => ['Costo de Venta - Micrófonos', 'gasto', '5.01.01'],
        '5.01.02.05'    => ['Costo de Venta - Audífonos', 'gasto', '5.01.02'],
        '1.02.02.05'    => ['(-) Depreciación Acumulada Herramientas y Equipos de Taller', 'activo', '1.02.02'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('plan_cuentas') || !Schema::hasTable('subcategoria_cuentas') || !Schema::hasTable('categorias_producto')) {
            return;
        }

        DB::transaction(function () {
            // 1. Plan de cuentas V5.1
            foreach (self::CUENTAS_NUEVAS as $codigo => [$nombre, $tipo, $padreCodigo]) {
                if (DB::table('plan_cuentas')->where('codigo', $codigo)->exists()) {
                    continue;
                }
                $padre = DB::table('plan_cuentas')->where('codigo', $padreCodigo)->first();
                DB::table('plan_cuentas')->insert([
                    'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $nombre, 'tipo' => $tipo,
                    'padre_id' => $padre?->id, 'nivel' => substr_count($codigo, '.') + 1,
                    'permite_asientos' => true, 'estado' => true, 'total_asientos' => 0,
                ]);
            }

            // 2. Otros ingresos: 4.03.02 desaparece y los parámetros pasan a 4.04
            $otros = DB::table('plan_cuentas')->where('codigo', '4.04')->value('id');
            $vieja = DB::table('plan_cuentas')->where('codigo', '4.03.02')->first();
            if ($otros && $vieja) {
                DB::table('parametros_contables')->where('cuenta_id', $vieja->id)->update(['cuenta_id' => $otros]);
                $conMovimientos = DB::table('asiento_detalles')->where('cuenta_id', $vieja->id)->exists();
                if ($conMovimientos) {
                    DB::table('plan_cuentas')->where('id', $vieja->id)->update(['estado' => false, 'nombre' => 'OBS-' . $vieja->nombre]);
                } else {
                    DB::table('plan_cuentas')->where('id', $vieja->id)->delete();
                }
            }

            // 3. Los servicios no tienen inventario ni costo
            DB::statement('ALTER TABLE subcategoria_cuentas ALTER COLUMN cuenta_inventario_id DROP NOT NULL');
            DB::statement('ALTER TABLE subcategoria_cuentas ALTER COLUMN cuenta_costo_id DROP NOT NULL');

            // 4. Categorías y subcategorías nuevas
            $cat = function (string $nombre, ?string $padre = null): int {
                $padreId = $padre ? (int) DB::table('categorias_producto')->whereNull('categoria_padre_id')->where('nombre', $padre)->value('id') : null;
                $fila = DB::table('categorias_producto')->where('nombre', $nombre)->where('categoria_padre_id', $padreId)->first();
                if ($fila) {
                    return (int) $fila->id;
                }

                return (int) DB::table('categorias_producto')->insertGetId([
                    'nombre' => $nombre, 'categoria_padre_id' => $padreId, 'estado' => true, 'requiere_serie' => false,
                ]);
            };
            $id = fn (string $codigo) => (int) DB::table('plan_cuentas')->where('codigo', $codigo)->value('id');
            $sinClasificar = [$id('1.01.04.09'), $id('4.01.09'), $id('5.01.08')];
            $pc01 = DB::table('subcategoria_cuentas')->where('codigo', 'PC-CAT-01')->first();

            // [código, categoría padre|null, subcategoría, nombre mostrado, inventario, venta, costo]
            $definiciones = [
                ['PC-CAT-21', 'Parlantes y sistemas de sonido', 'Micrófonos', [$id('1.01.04.01.06'), $id('4.01.01.06'), $id('5.01.01.06')]],
                ['PC-CAT-22', 'Artículos y Equipos de DJ', 'Audífonos', [$id('1.01.04.02.05'), $id('4.01.02.05'), $id('5.01.02.05')]],
                ['PC-CAT-23', 'Iluminación Profesional', 'Láseres y efectos LED', $sinClasificar],
                ['PC-CAT-24', 'Iluminación Profesional', 'Máquinas de efectos y pirotecnia fría', $sinClasificar],
                ['PC-CAT-25', 'Parlantes y sistemas de sonido', 'Amplificadores y sistemas inalámbricos de audio',
                    $pc01 ? [(int) $pc01->cuenta_inventario_id, (int) $pc01->cuenta_venta_id, (int) $pc01->cuenta_costo_id] : null],
                ['PC-CAT-26', null, 'Inventario sin clasificar', $sinClasificar],
                ['PC-CAT-27', 'Servicios', 'Servicio técnico', [null, $id('4.01.08'), null]],
                ['PC-CAT-28', 'Servicios', 'Eventos', [null, $id('4.04'), null]],
            ];

            foreach ($definiciones as [$codigo, $padre, $sub, $cuentas]) {
                if ($cuentas === null || in_array(0, array_filter($cuentas, fn ($c) => $c !== null), true)) {
                    continue; // falta una cuenta del plan: se informa al ejecutar la carga, no se inventa
                }
                if ($padre === 'Servicios') {
                    $cat('Servicios'); // la categoría padre de los servicios debe existir antes que sus subcategorías
                }
                $categoriaId = $padre ? $cat($sub, $padre) : $cat($sub);
                if (DB::table('subcategoria_cuentas')->where('codigo', $codigo)->exists()) {
                    continue;
                }
                DB::table('subcategoria_cuentas')->insert([
                    'codigo' => $codigo, 'categoria' => $padre ?? $sub, 'subcategoria' => $padre ? $sub : null,
                    'categoria_producto_id' => $categoriaId,
                    'cuenta_inventario_id' => $cuentas[0], 'cuenta_venta_id' => $cuentas[1], 'cuenta_costo_id' => $cuentas[2],
                    'estado' => true,
                ]);
            }
        });
    }

    public function down(): void
    {
        // Sin reversa: no se descartan cuentas ni subcategorías en uso.
    }
};
