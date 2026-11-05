<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Módulo B (docs/Oct2026/01_Parametros_Contables.pdf):
 *  - cuenta nueva 4.03.02 «Otros ingresos» (aprobada por Edwin; PC-VEN-16)
 *  - parámetros que el documento exige y la pantalla aún no tenía
 *  - tabla subcategoria_cuentas: las 20 subcategorías de §6 (PC-CAT-01..20) con
 *    sus cuentas de inventario, venta y costo. No toca la tabla `productos`.
 *
 * Idempotente. Debe correr después de cargar_plan_cuentas_final.
 */
return new class extends Migration
{
    /** clave => [código de cuenta, descripción] (configuración general, empresa_id NULL) */
    private const PARAMETROS = [
        'cta_cheques_cartera'          => ['1.01.03.05', 'Cheques en Cartera (post fechados recibidos)'],
        'cta_otros_ingresos'           => ['4.03.02',    'Otros ingresos (facturas de evento)'],
        'cta_iva_comision_tarjeta'     => ['1.01.05.01', 'IVA de la comisión de tarjeta (crédito tributario)'],
        'cta_proveedores_logisticos'   => ['2.01.01.03', 'Proveedores de Servicios Logísticos y Agentes Aduaneros'],
        'cta_cuenta_socio'             => ['2.01.06',    'Cuenta por pagar al socio (préstamos del dueño)'],
        'cta_iva_por_pagar_mes'        => ['2.01.02.04', 'IVA por Pagar del mes (neto)'],
        'cta_impuesto_renta_pagar'     => ['2.01.07',    'Impuesto a la Renta por Pagar'],
        'cta_capital'                  => ['3.01.01',    'Capital Neto / Cuenta Capital'],
        'cta_retiros_propietario'      => ['3.01.04.01', '(-) Retiros Personales del Propietario'],
        'cta_fondos_reserva_pagar'     => ['2.01.03.04', 'Fondos de Reserva por Pagar'],
        'cta_decimo_tercero_pagar'     => ['2.01.03.03.01', 'Provisión Décimo Tercer Sueldo por Pagar'],
        'cta_decimo_cuarto_pagar'      => ['2.01.03.03.02', 'Provisión Décimo Cuarto Sueldo por Pagar'],
        'cta_vacaciones_pagar'         => ['2.01.03.03.03', 'Provisión Vacaciones por Pagar'],
    ];

    /** PC-CAT-xx => [categoría, subcategoría, inventario, venta, costo] (§6 del documento 01) */
    private const SUBCATEGORIAS = [
        'PC-CAT-01' => ['Parlantes y sistemas de sonido', 'Parlantes Activos (Amplificados)',           '1.01.04.01.01', '4.01.01.01', '5.01.01.01'],
        'PC-CAT-02' => ['Parlantes y sistemas de sonido', 'Parlantes Pasivos',                           '1.01.04.01.02', '4.01.01.02', '5.01.01.02'],
        'PC-CAT-03' => ['Parlantes y sistemas de sonido', 'Subwoofers / Bajos',                          '1.01.04.01.03', '4.01.01.03', '5.01.01.03'],
        'PC-CAT-04' => ['Parlantes y sistemas de sonido', 'Sistemas Line Array',                         '1.01.04.01.04', '4.01.01.04', '5.01.01.04'],
        'PC-CAT-05' => ['Parlantes y sistemas de sonido', 'Parlantes de Instalación Comercial',          '1.01.04.01.05', '4.01.01.05', '5.01.01.05'],
        'PC-CAT-06' => ['Artículos y Equipos de DJ',      'Controladores de DJ',                         '1.01.04.02.01', '4.01.02.01', '5.01.02.01'],
        'PC-CAT-07' => ['Artículos y Equipos de DJ',      'Mezcladoras / Mixers',                        '1.01.04.02.02', '4.01.02.02', '5.01.02.02'],
        'PC-CAT-08' => ['Artículos y Equipos de DJ',      'Consolas análogas y digitales',               '1.01.04.02.03', '4.01.02.03', '5.01.02.03'],
        'PC-CAT-09' => ['Artículos y Equipos de DJ',      'Tornamesas / Turntables',                     '1.01.04.02.04', '4.01.02.04', '5.01.02.04'],
        'PC-CAT-10' => ['Iluminación Profesional',        'Luces Robóticas, Beams, Spots',               '1.01.04.03.01', '4.01.03.01', '5.01.03.01'],
        'PC-CAT-11' => ['Iluminación Profesional',        'Tachos LED / Iluminación Estática',           '1.01.04.03.02', '4.01.03.02', '5.01.03.02'],
        'PC-CAT-12' => ['Iluminación Profesional',        'Controladores DMX alámbricos e inalámbricos', '1.01.04.03.03', '4.01.03.03', '5.01.03.03'],
        'PC-CAT-13' => ['Pantallas LED y Video',          'Módulos y Gabinetes LED',                     '1.01.04.04.01', '4.01.04.01', '5.01.04.01'],
        'PC-CAT-14' => ['Pantallas LED y Video',          'Procesadores y Equipos de Control de Video',  '1.01.04.04.02', '4.01.04.02', '5.01.04.02'],
        'PC-CAT-15' => ['Cables, Conectores y Estructuras', null,                                        '1.01.04.05',    '4.01.05',    '5.01.05'],
        'PC-CAT-16' => ['Repuestos e Insumos de Taller',  null,                                          '1.01.04.06',    '4.01.06',    '5.01.06'],
        // Regalos: no se venden; al entregarlos el costo va a 6.01.15 (PC-VEN-15, PC-CAT-R03)
        'PC-CAT-17' => ['Regalos',                        'Camisetas de regalo General',                 '1.01.04.08.01', null,         '6.01.15'],
        'PC-CAT-18' => ['Regalos',                        'Demás regalos',                               '1.01.04.08.02', null,         '6.01.15'],
        // Solo estas dos categorías usan las cuentas sin clasificar (PC-GEN-03, PC-CAT-R04)
        'PC-CAT-19' => ['Computadoras, partes y repuestos', 'Todas las subcategorías (venta esporádica)', '1.01.04.09',   '4.01.09',    '5.01.08'],
        'PC-CAT-20' => ['PISOS LED (descontinuada)',      null,                                          '1.01.04.09',    '4.01.09',    '5.01.08'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('plan_cuentas')) {
            return;
        }

        DB::transaction(function () {
            $this->crearCuentaOtrosIngresos();
            $this->cargarParametros();
            $this->cargarSubcategorias();
        });
    }

    private function crearCuentaOtrosIngresos(): void
    {
        if (DB::table('plan_cuentas')->where('codigo', '4.03.02')->exists()) {
            return;
        }
        DB::table('plan_cuentas')->insert([
            'empresa_id'       => null,
            'codigo'           => '4.03.02',
            'nombre'           => 'Otros ingresos',
            'tipo'             => 'ingreso',
            'nivel'            => 3,
            'padre_id'         => DB::table('plan_cuentas')->where('codigo', '4.03')->value('id'),
            'permite_asientos' => true,
            'estado'           => true,
            'total_asientos'   => 0,
        ]);
    }

    private function cargarParametros(): void
    {
        if (!Schema::hasTable('parametros_contables')) {
            return;
        }
        foreach (self::PARAMETROS as $clave => [$codigoCuenta, $descripcion]) {
            $cuentaId = DB::table('plan_cuentas')->where('codigo', $codigoCuenta)->value('id');
            if (!$cuentaId) {
                continue;
            }
            // Si ya existe (general), no se pisa lo que el contador haya configurado.
            $existe = DB::table('parametros_contables')
                ->whereNull('empresa_id')->where('codigo', $clave)->exists();
            if (!$existe) {
                DB::table('parametros_contables')->insert([
                    'empresa_id'  => null,
                    'codigo'      => $clave,
                    'cuenta_id'   => $cuentaId,
                    'descripcion' => $descripcion,
                ]);
            }
        }
    }

    private function cargarSubcategorias(): void
    {
        if (!Schema::hasTable('subcategoria_cuentas')) {
            Schema::create('subcategoria_cuentas', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 12)->unique();            // PC-CAT-01..20
                $table->string('categoria', 120);
                $table->string('subcategoria', 150)->nullable();
                $table->unsignedBigInteger('categoria_producto_id')->nullable(); // vínculo con categorias_producto (módulo F)
                $table->unsignedBigInteger('cuenta_inventario_id');
                $table->unsignedBigInteger('cuenta_venta_id')->nullable();       // null: regalos, no se venden
                $table->unsignedBigInteger('cuenta_costo_id');
                $table->boolean('estado')->default(true);
            });
        }

        $ids = DB::table('plan_cuentas')->pluck('id', 'codigo');
        foreach (self::SUBCATEGORIAS as $codigo => [$categoria, $sub, $inv, $venta, $costo]) {
            if (DB::table('subcategoria_cuentas')->where('codigo', $codigo)->exists()) {
                continue;
            }
            if (!isset($ids[$inv], $ids[$costo]) || ($venta !== null && !isset($ids[$venta]))) {
                throw new RuntimeException("{$codigo}: alguna cuenta no existe en el plan de cuentas.");
            }
            DB::table('subcategoria_cuentas')->insert([
                'codigo'               => $codigo,
                'categoria'            => $categoria,
                'subcategoria'         => $sub,
                'cuenta_inventario_id' => $ids[$inv],
                'cuenta_venta_id'      => $venta !== null ? $ids[$venta] : null,
                'cuenta_costo_id'      => $ids[$costo],
                'estado'               => true,
            ]);
        }
    }

    public function down(): void
    {
        // Sin reversa: no se eliminan cuentas ni parámetros contables.
    }
};
