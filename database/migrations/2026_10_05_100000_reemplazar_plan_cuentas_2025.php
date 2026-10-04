<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Reemplaza el plan de cuentas por "PLAN DE CUENTAS 2025 ALTAMIRA" (docs/). Se conservan los
    // ids de las cuentas equivalentes (se actualizan en sitio) para no romper asientos, bancos ni
    // parámetros; las cuentas que se fusionan en una sola reapuntan sus referencias. Las cuentas
    // sin equivalente y sin movimientos se eliminan; si tienen movimientos quedan inactivas con
    // prefijo OBS-. No toca productos.cuenta_* (decisión del proyecto). Idempotente: si el plan
    // nuevo ya está cargado no hace nada.

    // [codigo, nombre, nivel, tipo]. Las cuentas "(General)" no vienen en el Excel: las usan los
    // asientos automáticos de venta, costo e inventario, que no distinguen línea de producto.
    private const PLAN = [
        ['1', 'ACTIVO', 1, 'activo'],
        ['1.01', 'ACTIVO CORRIENTE', 2, 'activo'],
        ['1.01.01', 'Efectivo y Equivalentes de Efectivo', 3, 'activo'],
        ['1.01.01.01', 'Caja General', 4, 'activo'],
        ['1.01.01.02', 'Caja Chica', 4, 'activo'],
        ['1.01.02', 'Instituciones Financieras', 3, 'activo'],
        ['1.01.02.01', 'Banco Local - Cuenta Corriente del Negocio', 4, 'activo'],
        ['1.01.02.02', 'Banco Local - Cuenta de Ahorros del Negocio', 4, 'activo'],
        ['1.01.03', 'Cuentas y Documentos por Cobrar Comerciales', 3, 'activo'],
        ['1.01.03.01', 'Clientes Locales', 4, 'activo'],
        ['1.01.03.02', '(-) Provisión para Cuentas Incobrables', 4, 'activo'],
        ['1.01.04', 'Inventarios', 3, 'activo'],
        ['1.01.04.01', 'Inventario de Parlantes y Sistemas de Sonido', 4, 'activo'],
        ['1.01.04.01.01', 'Parlantes Activos (Amplificados)', 5, 'activo'],
        ['1.01.04.01.02', 'Parlantes Pasivos', 5, 'activo'],
        ['1.01.04.01.03', 'Subwoofers / Bajos', 5, 'activo'],
        ['1.01.04.01.04', 'Sistemas Line Array', 5, 'activo'],
        ['1.01.04.01.05', 'Parlantes de Instalación Comercial', 5, 'activo'],
        ['1.01.04.02', 'Inventario de Artículos y Equipos de DJ', 4, 'activo'],
        ['1.01.04.02.01', 'Controladores de DJ', 5, 'activo'],
        ['1.01.04.02.02', 'Mezcladoras / Mixers', 5, 'activo'],
        ['1.01.04.02.03', 'Reproductores Multimedia / Multiplayers', 5, 'activo'],
        ['1.01.04.02.04', 'Tornamesas / Turntables', 5, 'activo'],
        ['1.01.04.03', 'Inventario de Accesorios, Cables y Repuestos', 4, 'activo'],
        ['1.01.04.04', 'Inventario de Mercaderías en Tránsito', 4, 'activo'],
        ['1.01.04.05', 'Inventario de Mercaderías (General)', 4, 'activo'],
        ['1.01.05', 'Activos por Impuestos Corrientes', 3, 'activo'],
        ['1.01.05.01', 'Crédito Tributario de IVA por Compras (15%)', 4, 'activo'],
        ['1.01.05.02', 'Retenciones en la Fuente de IR que le realizaron', 4, 'activo'],
        ['1.01.05.03', 'Retenciones de IVA que le realizaron', 4, 'activo'],
        ['1.02', 'ACTIVO NO CORRIENTE', 2, 'activo'],
        ['1.02.01', 'Propiedades, Planta y Equipos', 3, 'activo'],
        ['1.02.01.01', 'Muebles y Enseres', 4, 'activo'],
        ['1.02.01.02', 'Equipos de Computación', 4, 'activo'],
        ['1.02.01.03', 'Vehículos', 4, 'activo'],
        ['1.02.01.04', 'Equipos de Alquiler / Demostración', 4, 'activo'],
        ['1.02.02', '(-) Depreciación Acumulada PPE', 3, 'activo'],
        ['1.02.02.01', '(-) Depreciación Acumulada Muebles y Enseres', 4, 'activo'],
        ['1.02.02.02', '(-) Depreciación Acumulada Equipos de Computación', 4, 'activo'],
        ['1.02.02.03', '(-) Depreciación Acumulada Vehículos', 4, 'activo'],
        ['1.02.02.04', '(-) Depreciación Acumulada Equipos de Alquiler', 4, 'activo'],
        ['2', 'PASIVO', 1, 'pasivo'],
        ['2.01', 'PASIVO CORRIENTE', 2, 'pasivo'],
        ['2.01.01', 'Cuentas y Documentos por Pagar Comerciales', 3, 'pasivo'],
        ['2.01.01.01', 'Proveedores Locales', 4, 'pasivo'],
        ['2.01.01.02', 'Proveedores del Exterior', 4, 'pasivo'],
        ['2.01.02', 'Obligaciones Tributarias con el SRI', 3, 'pasivo'],
        ['2.01.02.01', 'IVA Cobrado en Ventas (15%)', 4, 'pasivo'],
        ['2.01.02.02', 'Retenciones de Impuesto a la Renta por Pagar', 4, 'pasivo'],
        ['2.01.02.03', 'Retenciones de IVA por Pagar', 4, 'pasivo'],
        ['2.01.03', 'Obligaciones con el IESS y Empleados', 3, 'pasivo'],
        ['2.01.03.01', 'Sueldos y Salarios por Pagar', 4, 'pasivo'],
        ['2.01.03.02', 'Aportes al IESS por Pagar (Personal y Patronal)', 4, 'pasivo'],
        ['2.01.03.03', 'Beneficios Sociales por Pagar', 4, 'pasivo'],
        ['2.01.03.04', 'Fondos de Reserva por Pagar', 4, 'pasivo'],
        ['2.02', 'PASIVO NO CORRIENTE', 2, 'pasivo'],
        ['2.02.01', 'Obligaciones con Instituciones Financieras', 3, 'pasivo'],
        ['3', 'PATRIMONIO', 1, 'patrimonio'],
        ['3.01', 'PATRIMONIO DE LA PERSONA NATURAL', 2, 'patrimonio'],
        ['3.01.01', 'Capital Neto / Cuenta Capital', 3, 'patrimonio'],
        ['3.01.02', 'Resultados Acumulados', 3, 'patrimonio'],
        ['3.01.02.01', 'Utilidades Acumuladas de Ejercicios Anteriores', 4, 'patrimonio'],
        ['3.01.02.02', '(-) Pérdidas Acumuladas de Ejercicios Anteriores', 4, 'patrimonio'],
        ['3.01.03', 'Resultado del Ejercicio', 3, 'patrimonio'],
        ['3.01.03.01', 'Utilidad / (Pérdida) del Ejercicio Neto', 4, 'patrimonio'],
        ['3.01.04', 'Asignaciones y Retiros Personales', 3, 'patrimonio'],
        ['3.01.04.01', '(-) Retiros Personales del Propietario', 4, 'patrimonio'],
        ['4', 'INGRESOS', 1, 'ingreso'],
        ['4.01', 'INGRESOS OPERACIONALES (ACTIVIDAD ECONÓMICA)', 2, 'ingreso'],
        ['4.01.01', 'Venta de Parlantes y Sistemas de Sonido', 3, 'ingreso'],
        ['4.01.01.01', 'Venta de Parlantes Activos', 4, 'ingreso'],
        ['4.01.01.02', 'Venta de Parlantes Pasivos', 4, 'ingreso'],
        ['4.01.01.03', 'Venta de Subwoofers / Bajos', 4, 'ingreso'],
        ['4.01.01.04', 'Venta de Sistemas Line Array', 4, 'ingreso'],
        ['4.01.01.05', 'Venta de Parlantes de Instalación Comercial', 4, 'ingreso'],
        ['4.01.02', 'Venta de Equipos y Artículos de DJ', 3, 'ingreso'],
        ['4.01.02.01', 'Venta de Controladores de DJ', 4, 'ingreso'],
        ['4.01.02.02', 'Venta de Mezcladoras / Mixers', 4, 'ingreso'],
        ['4.01.02.03', 'Venta de Reproductores Multimedia / Multiplayers', 4, 'ingreso'],
        ['4.01.02.04', 'Venta de Tornamesas / Turntables', 4, 'ingreso'],
        ['4.01.03', 'Venta de Accesorios, Cables y Repuestos', 3, 'ingreso'],
        ['4.01.04', 'Ingresos por Alquiler de Equipos (Backline)', 3, 'ingreso'],
        ['4.01.05', 'Ingresos por Servicio Técnico y Mantenimiento', 3, 'ingreso'],
        ['4.01.06', 'Venta de Mercaderías (General)', 3, 'ingreso'],
        ['4.02', '(-) DEVOLUCIONES Y DESCUENTOS EN VENTAS', 2, 'ingreso'],
        ['4.02.01', '(-) Devoluciones en Ventas', 3, 'ingreso'],
        ['4.02.02', '(-) Descuentos Comerciales en Ventas', 3, 'ingreso'],
        ['5', 'COSTOS', 1, 'gasto'],
        ['5.01', 'COSTO DE VENTAS Y SERVICIOS', 2, 'gasto'],
        ['5.01.01', 'Costo de Venta - Parlantes y Sistemas de Sonido', 3, 'gasto'],
        ['5.01.01.01', 'Costo de Venta - Parlantes Activos', 4, 'gasto'],
        ['5.01.01.02', 'Costo de Venta - Parlantes Pasivos', 4, 'gasto'],
        ['5.01.01.03', 'Costo de Venta - Subwoofers / Bajos', 4, 'gasto'],
        ['5.01.01.04', 'Costo de Venta - Sistemas Line Array', 4, 'gasto'],
        ['5.01.01.05', 'Costo de Venta - Parlantes de Instalación', 4, 'gasto'],
        ['5.01.02', 'Costo de Venta - Equipos y Artículos de DJ', 3, 'gasto'],
        ['5.01.02.01', 'Costo de Venta - Controladores de DJ', 4, 'gasto'],
        ['5.01.02.02', 'Costo de Venta - Mezcladoras / Mixers', 4, 'gasto'],
        ['5.01.02.03', 'Costo de Venta - Reproductores Multimedia', 4, 'gasto'],
        ['5.01.02.04', 'Costo de Venta - Tornamesas', 4, 'gasto'],
        ['5.01.03', 'Costo de Venta - Accesorios, Cables y Repuestos', 3, 'gasto'],
        ['5.01.04', 'Costo de Servicios Directos (Mano de Obra / Alquiler)', 3, 'gasto'],
        ['5.01.04.01', 'Costo de Mano de Obra Directa (Técnicos de Alquiler)', 4, 'gasto'],
        ['5.01.04.02', 'Costo de Subcontratación de Servicio Técnico Especializado', 4, 'gasto'],
        ['5.01.04.03', 'Repuestos e Insumos consumidos en Servicio Técnico', 4, 'gasto'],
        ['5.01.05', 'Costo de Venta - Mercaderías (General)', 3, 'gasto'],
        ['6', 'GASTOS', 1, 'gasto'],
        ['6.01', 'GASTOS OPERACIONALES (ADMINISTRACIÓN Y VENTAS)', 2, 'gasto'],
        ['6.01.01', 'Sueldos, Salarios y Beneficios Sociales', 3, 'gasto'],
        ['6.01.02', 'Aporte Patronal al IESS y Fondos de Reserva', 3, 'gasto'],
        ['6.01.03', 'Arrendamiento de Inmuebles (Local Comercial / Bodega)', 3, 'gasto'],
        ['6.01.04', 'Servicios Básicos (Agua, Luz, Teléfono, Internet)', 3, 'gasto'],
        ['6.01.05', 'Publicidad, Marketing y Promoción Digital', 3, 'gasto'],
        ['6.01.06', 'Transporte, Fletes y Envíos Locales', 3, 'gasto'],
        ['6.01.07', 'Mantenimiento y Reparaciones de Activos Fijos', 3, 'gasto'],
        ['6.01.08', 'Depreciación de Propiedades, Planta y Equipo', 3, 'gasto'],
        ['6.01.09', 'Combustibles y Lubricantes', 3, 'gasto'],
        ['6.01.10', 'Gastos Financieros y Comisiones Bancarias', 3, 'gasto'],
        ['6.01.11', 'Honorarios Profesionales (Contador, Asesor Legal)', 3, 'gasto'],
        ['6.01.12', 'Suministros y Materiales de Oficina / Empaque', 3, 'gasto'],
        ['6.02', 'GASTOS NO DEDUCIBLES', 2, 'gasto'],
        ['6.02.01', 'Gastos del Negocio sin Sustento Tributario (Sin Factura)', 3, 'gasto'],
        ['6.02.02', 'Multas, Intereses y Recargos Tributarios / IESS', 3, 'gasto'],
    ];

    // código viejo => código nuevo (varias cuentas viejas pueden fusionarse en una nueva).
    private const MAPA = [
        '1' => '1', '1.1' => '1.01', '1.1.1' => '1.01.01',
        '1.1.1.1' => '1.01.01.01', '1.1.1.2' => '1.01.01.02',
        '1.1.1.3' => '1.01.02.01', '1.1.1.4' => '1.01.02.01', '1.1.1.5' => '1.01.03.01',
        '1.1.2' => '1.01.02',
        '1.1.3' => '1.01.03', '1.1.3.1' => '1.01.03.01', '1.1.3.2' => '1.01.03.01',
        '1.1.3.3' => '1.01.03.01', '1.1.3.4' => '1.01.03.01', '1.1.3.5' => '1.01.03.02',
        '1.1.4' => '1.01.04', '1.1.4.1' => '1.01.04.05', '1.1.4.2' => '1.01.04.03', '1.1.4.3' => '1.01.04.04',
        '1.1.5' => '1.01.05', '1.1.5.1' => '1.01.05.01', '1.1.5.2' => '1.01.05.03',
        '1.1.5.3' => '1.01.05.02', '1.1.5.4' => '1.01.05.02',
        '1.2' => '1.02', '1.2.1' => '1.02.01',
        '1.2.1.4' => '1.02.01.01', '1.2.1.6' => '1.02.01.02', '1.2.1.7' => '1.02.01.03', '1.2.1.5' => '1.02.01.04',
        '1.2.1.10' => '1.02.02', '1.2.1.10.3' => '1.02.02.01', '1.2.1.10.5' => '1.02.02.02',
        '1.2.1.10.6' => '1.02.02.03', '1.2.1.10.4' => '1.02.02.04',

        '2' => '2', '2.1' => '2.01', '2.1.1' => '2.01.01',
        '2.1.1.1' => '2.01.01.01', '2.1.1.2' => '2.01.01.02', '2.1.1.3' => '2.01.01.01', '2.1.1.4' => '2.01.03.01',
        '2.1.2.1' => '2.02.01', '2.1.2.2' => '2.02.01', '2.2.2.1' => '2.02.01', '2.2' => '2.02',
        '2.1.3' => '2.01.02', '2.1.3.1' => '2.01.02.02', '2.1.3.2' => '2.01.02.03',
        '2.1.3.3' => '2.01.02.02', '2.1.3.4' => '2.01.02.01',
        '2.1.4' => '2.01.03', '2.1.4.1' => '2.01.03.01', '2.1.4.2' => '2.01.03.02', '2.1.4.3' => '2.01.03.02',
        '2.1.4.4' => '2.01.03.02', '2.1.4.5' => '2.01.03.03', '2.1.4.6' => '2.01.03.03',
        '2.1.4.7' => '2.01.03.03', '2.1.4.8' => '2.01.03.04', '2.1.4.9' => '2.01.03.03',

        '3' => '3', '3.1' => '3.01', '3.1.1.01' => '3.01.01', '3.1.6.01' => '3.01.01',
        '3.1.4' => '3.01.02', '3.1.4.01' => '3.01.02.01', '3.1.4.02' => '3.01.02.02',
        '3.1.5' => '3.01.03', '3.1.5.01' => '3.01.03.01', '3.1.5.02' => '3.01.03.01',

        '4' => '4', '4.1' => '4.01', '4.1.1.01' => '4.01.06', '4.1.1.02' => '4.01.06',
        '4.1.2.01' => '4.01.05', '4.1.2.02' => '4.01.05',
        '4.1.3' => '4.02', '4.1.3.01' => '4.02.01', '4.1.3.02' => '4.02.02',

        '5' => '5', '5.1' => '5.01',
        '5.1.1.1' => '5.01.05', '5.1.1.2' => '5.01.05', '5.1.1.3' => '5.01.04.01', '5.1.1.4' => '5.01.05',

        '5.2' => '6.01', '5.4' => '6.02',
        '5.2.1.01' => '6.01.01', '5.2.1.02' => '6.01.01', '5.2.1.03' => '6.01.01', '5.2.1.04' => '6.01.02',
        '5.2.1.05' => '6.01.01', '5.2.1.06' => '6.01.01', '5.2.1.07' => '6.01.01', '5.2.1.08' => '6.01.02',
        '5.2.1.09' => '6.01.11', '5.2.1.10' => '6.01.12',
        '5.2.2.01' => '6.01.11', '5.2.2.02' => '6.01.03', '5.2.2.03' => '6.01.04', '5.2.2.04' => '6.01.07',
        '5.2.2.05' => '6.01.07', '5.2.2.06' => '6.01.12', '5.2.2.07' => '6.01.06', '5.2.2.08' => '6.01.09',
        '5.2.2.10' => '6.02.02', '5.2.2.11' => '6.01.05', '5.2.3.01' => '6.01.08',
        '5.3.1.01' => '6.01.10', '5.3.1.02' => '6.01.10', '5.3.1.03' => '6.01.10',
        '5.4.1.01' => '6.02.01', '5.4.1.02' => '6.02.01', '5.4.1.03' => '6.02.01',
    ];

    // parámetro contable => código nuevo (mantener en sincronía con AsientoService::FALLBACK_PLAN).
    private const PARAMETROS = [
        'cta_caja_general' => '1.01.01.01', 'cta_cajas_chicas' => '1.01.01.02',
        'cta_bancos_locales' => '1.01.02.01', 'cta_bancos_exterior' => '1.01.02.01',
        'cta_vouchers' => '1.01.03.01', 'cta_clientes_locales' => '1.01.03.01',
        'cta_clientes_exterior' => '1.01.03.01', 'cta_anticipos_proveedores' => '1.01.03.01',
        'cta_anticipos_empleados' => '1.01.03.01', 'cta_provision_incobrables' => '1.01.03.02',
        'cta_inventario_mercaderia' => '1.01.04.05', 'cta_inventario_transito' => '1.01.04.04',
        'cta_iva_compras' => '1.01.05.01', 'cta_retencion_iva_cobrada' => '1.01.05.03',
        'cta_retencion_ir_cobrada' => '1.01.05.02',
        'cta_proveedores_locales' => '2.01.01.01', 'cta_proveedores_exterior' => '2.01.01.02',
        'cta_anticipos_clientes' => '2.01.01.01', 'cta_retencion_ir' => '2.01.02.02',
        'cta_retencion_iva' => '2.01.02.03', 'cta_impuesto_renta_pagar' => '2.01.02.02',
        'cta_iva_ventas' => '2.01.02.01', 'cta_nomina_por_pagar' => '2.01.03.01',
        'cta_iess_por_pagar' => '2.01.03.02', 'cta_iess_personal_por_pagar' => '2.01.03.02',
        'cta_decimo_tercero_pagar' => '2.01.03.03', 'cta_decimo_cuarto_pagar' => '2.01.03.03',
        'cta_vacaciones_pagar' => '2.01.03.03', 'cta_fondos_reserva_pagar' => '2.01.03.04',
        'cta_participacion_trabajadores' => '2.01.03.03',
        'cta_ganancias_acumuladas' => '3.01.02.01', 'cta_perdidas_acumuladas' => '3.01.02.02',
        'cta_utilidad_periodo' => '3.01.03.01', 'cta_perdida_periodo' => '3.01.03.01',
        'cta_ventas_locales' => '4.01.06', 'cta_ventas_exterior' => '4.01.06',
        'cta_ingresos_servicios' => '4.01.05', 'cta_devoluciones_ventas' => '4.02.01',
        'cta_descuentos_ventas' => '4.02.02',
        'cta_costo_ventas' => '5.01.05', 'cta_costo_ventas_importadas' => '5.01.05',
        'cta_costo_servicios' => '5.01.04.01', 'cta_ajuste_inventario' => '5.01.05',
        'cta_sueldos_salarios' => '6.01.01', 'cta_horas_extras' => '6.01.01',
        'cta_aporte_patronal' => '6.01.02', 'cta_decimo_tercero' => '6.01.01',
        'cta_decimo_cuarto' => '6.01.01', 'cta_vacaciones' => '6.01.01', 'cta_fondos_reserva' => '6.01.02',
        'cta_gasto_compras_default' => '6.01.12', 'cta_gasto_servicios' => '6.01.11',
        'cta_gasto_arrendamiento' => '6.01.03', 'cta_gasto_servicios_basicos' => '6.01.04',
        'cta_gasto_publicidad' => '6.01.05', 'cta_comisiones_bancarias' => '6.01.10',
        'cta_gastos_no_deducibles' => '6.02.01', 'cta_ajuste_conciliacion' => '6.02.01',
    ];

    // [tabla, columna] que guardan un plan_cuentas.id.
    private const REFERENCIAS = [
        ['asiento_detalles', 'cuenta_id'],
        ['compra_detalles', 'cuenta_id'],
        ['bancos_cajas', 'cuenta_id'],
        ['movimientos_bancarios', 'cuenta_contrapartida_id'],
        ['parametros_contables', 'cuenta_id'],
        ['activos_fijos', 'cuenta_id'],
    ];

    public function up(): void
    {
        if (!Schema::hasTable('plan_cuentas')) {
            return;
        }
        if (DB::table('plan_cuentas')->where('codigo', '1.01.01.01')->exists()) {
            return;
        }

        DB::transaction(function () {
            $viejas = DB::table('plan_cuentas')->get()->keyBy('codigo');
            $refs   = $this->contarReferencias();

            // Libera todos los códigos: ninguna cuenta nueva puede chocar con una vieja.
            DB::statement("UPDATE plan_cuentas SET codigo = 'OBS-' || codigo");

            $candidatas = [];
            foreach (self::MAPA as $viejo => $nuevo) {
                if (isset($viejas[$viejo])) {
                    $candidatas[$nuevo][] = $viejas[$viejo];
                }
            }

            $codigosPlan = array_column(self::PLAN, 0);
            $ids         = [];
            $absorbidas  = [];

            foreach (self::PLAN as [$codigo, $nombre, $nivel, $tipo]) {
                $segmentos = explode('.', $codigo);
                $padreId   = count($segmentos) > 1 ? ($ids[implode('.', array_slice($segmentos, 0, -1))] ?? null) : null;
                $esHoja    = !$this->tieneHijos($codigo, $codigosPlan);

                $datos = [
                    'codigo' => $codigo, 'nombre' => $nombre, 'descripcion' => $nombre, 'tipo' => $tipo,
                    'padre_id' => $padreId, 'nivel' => $nivel, 'permite_asientos' => $esHoja, 'estado' => true,
                ];

                $lista = $candidatas[$codigo] ?? [];
                usort($lista, fn($a, $b) => [($refs[$b->id] ?? 0) > 0, (bool) $b->permite_asientos === $esHoja]
                                        <=> [($refs[$a->id] ?? 0) > 0, (bool) $a->permite_asientos === $esHoja]);

                if ($lista) {
                    $conserva = array_shift($lista);
                    DB::table('plan_cuentas')->where('id', $conserva->id)->update($datos);
                    $ids[$codigo] = $conserva->id;
                    foreach ($lista as $otra) {
                        $absorbidas[$otra->id] = $conserva->id;
                    }
                } else {
                    $ids[$codigo] = DB::table('plan_cuentas')->insertGetId($datos + ['total_asientos' => 0]);
                }
            }

            foreach ($absorbidas as $viejoId => $nuevoId) {
                foreach ($this->referenciasExistentes() as [$tabla, $columna]) {
                    DB::table($tabla)->where($columna, $viejoId)->update([$columna => $nuevoId]);
                }
            }

            foreach (self::PARAMETROS as $parametro => $codigo) {
                DB::table('parametros_contables')->where('codigo', $parametro)->update(['cuenta_id' => $ids[$codigo]]);
            }

            if (Schema::hasTable('rubros_nomina') && Schema::hasColumn('rubros_nomina', 'cuenta_contable')) {
                foreach (self::MAPA as $viejo => $nuevo) {
                    DB::table('rubros_nomina')->where('cuenta_contable', $viejo)->update(['cuenta_contable' => $nuevo]);
                }
            }

            // Sobrantes: se desvinculan del árbol; sin movimientos se eliminan, con movimientos quedan inactivas.
            $sobrantes = DB::table('plan_cuentas')->where('codigo', 'like', 'OBS-%')->pluck('id')->all();
            if ($sobrantes) {
                DB::table('plan_cuentas')->whereIn('id', $sobrantes)->update(['padre_id' => null]);
                $refs   = $this->contarReferencias();
                $borrar = array_values(array_filter($sobrantes, fn($id) => ($refs[$id] ?? 0) === 0));
                $quedan = array_values(array_diff($sobrantes, $borrar));
                if ($borrar) {
                    DB::table('plan_cuentas')->whereIn('id', $borrar)->delete();
                }
                if ($quedan) {
                    DB::table('plan_cuentas')->whereIn('id', $quedan)->update(['estado' => false, 'permite_asientos' => false]);
                }
            }
        });
    }

    public function down(): void
    {
    }

    private function tieneHijos(string $codigo, array $codigosPlan): bool
    {
        foreach ($codigosPlan as $otro) {
            if (str_starts_with($otro, $codigo . '.')) {
                return true;
            }
        }
        return false;
    }

    private function referenciasExistentes(): array
    {
        return array_values(array_filter(
            self::REFERENCIAS,
            fn($r) => Schema::hasTable($r[0]) && Schema::hasColumn($r[0], $r[1])
        ));
    }

    private function contarReferencias(): array
    {
        $cuenta = [];
        foreach ($this->referenciasExistentes() as [$tabla, $columna]) {
            $filas = DB::table($tabla)->whereNotNull($columna)
                ->selectRaw("$columna as id, COUNT(*) as n")->groupBy($columna)->get();
            foreach ($filas as $f) {
                $cuenta[$f->id] = ($cuenta[$f->id] ?? 0) + $f->n;
            }
        }
        return $cuenta;
    }
};
