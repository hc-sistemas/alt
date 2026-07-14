<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarPlanCuentasV2 extends Command
{
    protected $signature   = 'altamira:importar-plan-cuentas-v2
                              {--limpiar : Eliminar cuentas sin asientos antes}
                              {--preview : Ver cuántas cuentas se importarán}';
    protected $description = 'Importa el Plan de Cuentas V2 — Altamira Light & Sound';

    // ── MAPEO clase → tipo (campo real en BD) ────────────────
    private const TIPO_MAP = [
        '1' => 'activo',
        '2' => 'pasivo',
        '3' => 'patrimonio',
        '4' => 'ingreso',
        '5' => 'gasto',
    ];

    // ── PLAN COMPLETO ────────────────────────────────────────
    // FORMATO: [codigo, nombre, clase(1-5), naturaleza*, tipo_cuenta]
    // (*naturaleza no existe en BD — se ignora)
    // tipo_cuenta: grupo=permite_asientos=false, detalle=true
    private function getCuentas(): array
    {
        return [
            // ══ 1. ACTIVOS ════════════════════════════════════
            ['1',           'Activos',                                          '1','deudora',  'grupo'],
            ['1.1',         'Activo Corriente',                                 '1','deudora',  'grupo'],
            ['1.1.1',       'Efectivo y Equivalentes al Efectivo',              '1','deudora',  'grupo'],
            ['1.1.1.01',    'Caja General',                                     '1','deudora',  'detalle'],
            ['1.1.1.02',    'Cajas Chicas y Fondos Rotativos',                  '1','deudora',  'detalle'],
            ['1.1.1.03',    'Bancos Locales',                                   '1','deudora',  'detalle'],
            ['1.1.1.04',    'Bancos del Exterior',                              '1','deudora',  'detalle'],
            ['1.1.1.05',    'Cuentas Virtuales y Pasarelas de Pago',            '1','deudora',  'detalle'],
            ['1.1.2',       'Inversiones Financieras a Corto Plazo',            '1','deudora',  'grupo'],
            ['1.1.2.01',    'Inversiones a Costo Amortizado — Corto Plazo',     '1','deudora',  'detalle'],
            ['1.1.3',       'Cuentas y Documentos por Cobrar',                  '1','deudora',  'grupo'],
            ['1.1.3.01',    'Clientes Locales',                                 '1','deudora',  'detalle'],
            ['1.1.3.02',    'Clientes del Exterior',                            '1','deudora',  'detalle'],
            ['1.1.3.03',    'Anticipos a Proveedores',                          '1','deudora',  'detalle'],
            ['1.1.3.04',    'Préstamos y Anticipos a Empleados',                '1','deudora',  'detalle'],
            ['1.1.3.05',    '(-) Provisión Cuentas Incobrables',                '1','acreedora','detalle'],
            ['1.1.4',       'Inventarios',                                      '1','deudora',  'grupo'],
            ['1.1.4.01',    'Inventario de Mercaderías',                        '1','deudora',  'detalle'],
            ['1.1.4.02',    'Inventario de Materia Prima y Suministros',        '1','deudora',  'detalle'],
            ['1.1.4.03',    'Inventario en Tránsito — Importaciones en Curso',  '1','deudora',  'detalle'],
            ['1.1.4.04',    '(-) Provisión por Deterioro de Inventarios',       '1','acreedora','detalle'],
            ['1.1.5',       'Activos por Impuestos Corrientes',                 '1','deudora',  'grupo'],
            ['1.1.5.01',    'Crédito Tributario IVA Compras',                   '1','deudora',  'detalle'],
            ['1.1.5.02',    'Crédito Tributario por Retenciones de IVA',        '1','deudora',  'detalle'],
            ['1.1.5.03',    'Crédito Tributario por Retenciones de IR',         '1','deudora',  'detalle'],
            ['1.1.5.04',    'Anticipo de Impuesto a la Renta',                  '1','deudora',  'detalle'],
            ['1.1.5.05',    'IVA en Compras Pendiente de Clasificar',           '1','deudora',  'detalle'],
            ['1.1.6',       'Gastos Pagados por Anticipado',                    '1','deudora',  'grupo'],
            ['1.1.6.01',    'Seguros Pagados por Anticipado',                   '1','deudora',  'detalle'],
            ['1.1.6.02',    'Arriendos Pagados por Anticipado',                 '1','deudora',  'detalle'],
            ['1.1.7',       'Otros Activos Corrientes',                         '1','deudora',  'detalle'],
            ['1.2',         'Activo No Corriente',                              '1','deudora',  'grupo'],
            ['1.2.1',       'Propiedades, Planta y Equipo',                     '1','deudora',  'grupo'],
            ['1.2.1.01',    'Terrenos',                                         '1','deudora',  'detalle'],
            ['1.2.1.02',    'Edificios',                                        '1','deudora',  'detalle'],
            ['1.2.1.03',    'Instalaciones',                                    '1','deudora',  'detalle'],
            ['1.2.1.04',    'Muebles y Enseres',                                '1','deudora',  'detalle'],
            ['1.2.1.05',    'Maquinaria y Equipos',                             '1','deudora',  'detalle'],
            ['1.2.1.06',    'Equipos de Computación',                           '1','deudora',  'detalle'],
            ['1.2.1.07',    'Vehículos y Equipos de Transporte',                '1','deudora',  'detalle'],
            ['1.2.1.08',    'Repuestos y Herramientas de Uso Prolongado',       '1','deudora',  'detalle'],
            ['1.2.1.09',    'Construcciones en Curso',                          '1','deudora',  'detalle'],
            ['1.2.1.10',    '(-) Depreciación Acumulada PP&E',                  '1','acreedora','grupo'],
            ['1.2.1.10.1',  '(-) Dep. Acum. Edificios',                        '1','acreedora','detalle'],
            ['1.2.1.10.2',  '(-) Dep. Acum. Instalaciones',                    '1','acreedora','detalle'],
            ['1.2.1.10.3',  '(-) Dep. Acum. Muebles y Enseres',                '1','acreedora','detalle'],
            ['1.2.1.10.4',  '(-) Dep. Acum. Maquinaria y Equipos',             '1','acreedora','detalle'],
            ['1.2.1.10.5',  '(-) Dep. Acum. Equipos de Computación',           '1','acreedora','detalle'],
            ['1.2.1.10.6',  '(-) Dep. Acum. Vehículos',                        '1','acreedora','detalle'],
            ['1.2.2',       'Propiedades de Inversión',                         '1','deudora',  'grupo'],
            ['1.2.2.01',    'Terrenos de Inversión',                            '1','deudora',  'detalle'],
            ['1.2.2.02',    'Edificios de Inversión',                           '1','deudora',  'detalle'],
            ['1.2.2.03',    '(-) Depreciación Acumulada Propiedades de Inversión','1','acreedora','detalle'],
            ['1.2.3',       'Activos Intangibles',                              '1','deudora',  'grupo'],
            ['1.2.3.01',    'Software y Licencias',                             '1','deudora',  'detalle'],
            ['1.2.3.02',    'Marcas y Patentes',                                '1','deudora',  'detalle'],
            ['1.2.3.03',    '(-) Amortización Acumulada de Intangibles',        '1','acreedora','detalle'],
            ['1.2.4',       'Activos Financieros y Cuentas por Cobrar a Largo Plazo','1','deudora','grupo'],
            ['1.2.4.01',    'Cuentas y Documentos por Cobrar a Largo Plazo',    '1','deudora',  'detalle'],
            ['1.2.4.02',    'Inversiones a Costo Amortizado a Largo Plazo',     '1','deudora',  'detalle'],
            ['1.2.4.03',    '(-) Provisión Cuentas Incobrables a Largo Plazo',  '1','acreedora','detalle'],

            // ══ 2. PASIVOS ════════════════════════════════════
            ['2',           'Pasivos',                                          '2','acreedora','grupo'],
            ['2.1',         'Pasivo Corriente',                                 '2','acreedora','grupo'],
            ['2.1.1',       'Cuentas y Documentos por Pagar Comerciales',       '2','acreedora','grupo'],
            ['2.1.1.01',    'Proveedores Locales',                              '2','acreedora','detalle'],
            ['2.1.1.02',    'Proveedores del Exterior',                         '2','acreedora','detalle'],
            ['2.1.2',       'Obligaciones con Instituciones Financieras',       '2','acreedora','grupo'],
            ['2.1.2.01',    'Préstamos Bancarios a Corto Plazo',                '2','acreedora','detalle'],
            ['2.1.2.02',    'Tarjetas de Crédito Corporativas',                 '2','acreedora','detalle'],
            ['2.1.3',       'Obligaciones con la Administración Tributaria',    '2','acreedora','grupo'],
            ['2.1.3.01',    'Retenciones en la Fuente de IR por Pagar',         '2','acreedora','detalle'],
            ['2.1.3.02',    'Retenciones de IVA por Pagar',                     '2','acreedora','detalle'],
            ['2.1.3.03',    'Impuesto a la Renta por Pagar del Ejercicio',      '2','acreedora','detalle'],
            ['2.1.3.04',    'IVA en Ventas por Liquidar al SRI',                '2','acreedora','detalle'],
            ['2.1.3.05',    'IVA Retenido por Liquidar al SRI',                 '2','acreedora','detalle'],
            ['2.1.4',       'Obligaciones Laborales y Sociales',                '2','acreedora','grupo'],
            ['2.1.4.01',    'Nómina por Pagar',                                 '2','acreedora','detalle'],
            ['2.1.4.02',    'Obligaciones con el IESS — Aporte Patronal (11.15%)','2','acreedora','detalle'],
            ['2.1.4.03',    'Obligaciones con el IESS — Aporte Personal (9.45%)','2','acreedora','detalle'],
            ['2.1.4.04',    'Descuentos IESS — Préstamos Quirografarios e Hipotecarios','2','acreedora','detalle'],
            ['2.1.4.05',    'Décimo Tercer Sueldo por Pagar',                   '2','acreedora','detalle'],
            ['2.1.4.06',    'Décimo Cuarto Sueldo por Pagar',                   '2','acreedora','detalle'],
            ['2.1.4.07',    'Vacaciones por Pagar',                             '2','acreedora','detalle'],
            ['2.1.4.08',    'Fondos de Reserva por Pagar',                      '2','acreedora','detalle'],
            ['2.1.4.09',    'Participación Trabajadores en Utilidades por Pagar','2','acreedora','detalle'],
            ['2.1.5',       'Otros Pasivos Corrientes',                         '2','acreedora','grupo'],
            ['2.1.5.01',    'Porción Corriente de Obligaciones a Largo Plazo',  '2','acreedora','detalle'],
            ['2.1.5.02',    'Provisiones Diversas a Corto Plazo',               '2','acreedora','detalle'],
            ['2.1.6',       'Anticipos Recibidos de Clientes',                  '2','acreedora','grupo'],
            ['2.1.6.01',    'Anticipos de Clientes — Corto Plazo',              '2','acreedora','detalle'],
            ['2.2',         'Pasivo No Corriente',                              '2','acreedora','grupo'],
            ['2.2.1',       'Cuentas y Documentos por Pagar a Largo Plazo',     '2','acreedora','grupo'],
            ['2.2.1.01',    'Proveedores Locales a Largo Plazo',                '2','acreedora','detalle'],
            ['2.2.1.02',    'Proveedores del Exterior a Largo Plazo',           '2','acreedora','detalle'],
            ['2.2.1.03',    'Documentos y Letras por Pagar a Largo Plazo',      '2','acreedora','detalle'],
            ['2.2.2',       'Obligaciones Financieras a Largo Plazo',           '2','acreedora','grupo'],
            ['2.2.2.01',    'Préstamos Bancarios a Largo Plazo',                '2','acreedora','detalle'],
            ['2.2.3',       'Anticipos a Largo Plazo',                          '2','acreedora','grupo'],
            ['2.2.3.01',    'Anticipos de Clientes a Largo Plazo',              '2','acreedora','detalle'],
            ['2.2.4',       'Otras Cuentas por Pagar a Largo Plazo',            '2','acreedora','grupo'],
            ['2.2.4.01',    'Préstamos de Accionistas a Largo Plazo',           '2','acreedora','detalle'],
            ['2.2.5',       'Provisiones a Largo Plazo',                        '2','acreedora','grupo'],
            ['2.2.5.01',    'Provisión por Garantías de Productos',             '2','acreedora','detalle'],
            ['2.2.5.02',    'Provisión por Litigios y Demandas',                '2','acreedora','detalle'],
            ['2.2.6',       'Pasivos Diferidos',                                '2','acreedora','grupo'],
            ['2.2.6.01',    'Pasivos Diferidos',                                '2','acreedora','detalle'],

            // ══ 3. PATRIMONIO ═════════════════════════════════
            ['3',           'Patrimonio Neto',                                  '3','acreedora','grupo'],
            ['3.1',         'Patrimonio Neto',                                  '3','acreedora','grupo'],
            ['3.1.1',       'Capital',                                          '3','acreedora','grupo'],
            ['3.1.1.01',    'Capital del propietario',                          '3','acreedora','detalle'],
            ['3.1.2',       'Otros Resultados Integrales Acumulados',           '3','acreedora','grupo'],
            ['3.1.2.01',    'Superávit por Revaluación de PP&E',                '3','acreedora','detalle'],
            ['3.1.2.02',    'Ganancias y Pérdidas Actuariales',                 '3','acreedora','detalle'],
            ['3.1.3',       'Resultados Acumulados',                            '3','acreedora','grupo'],
            ['3.1.3.01',    'Ganancias Acumuladas',                             '3','acreedora','detalle'],
            ['3.1.3.02',    '(-) Pérdidas Acumuladas',                          '3','deudora',  'detalle'],
            ['3.1.4',       'Resultados del Periodo',                           '3','acreedora','grupo'],
            ['3.1.4.01',    'Utilidad del Periodo',                             '3','acreedora','detalle'],
            ['3.1.4.02',    '(-) Pérdida del Periodo',                          '3','deudora',  'detalle'],
            ['3.1.5',       'Aportes de Socios o Accionistas',                  '3','acreedora','grupo'],
            ['3.1.5.01',    'Aportes para Futuras Capitalizaciones',            '3','acreedora','detalle'],
            ['3.1.6',       'Retiros personales y/o gastos familiares',         '3','deudora',  'detalle'],

            // ══ 4. INGRESOS ═══════════════════════════════════
            ['4',           'Ingresos',                                         '4','acreedora','grupo'],
            ['4.1',         'Ingresos de Actividades Ordinarias',               '4','acreedora','grupo'],
            ['4.1.1',       'Venta de Bienes',                                  '4','acreedora','grupo'],
            ['4.1.1.01',    'Venta de Mercaderías — Mercado Local',             '4','acreedora','detalle'],
            ['4.1.1.02',    'Venta de Mercaderías al Exterior',                 '4','acreedora','detalle'],
            ['4.1.2',       'Prestación de Servicios',                          '4','acreedora','grupo'],
            ['4.1.2.01',    'Ingresos por Servicios Técnicos y Mantenimiento',  '4','acreedora','detalle'],
            ['4.1.2.02',    'Ingresos por Servicios de Capacitación y Asesorías','4','acreedora','detalle'],
            ['4.1.2.03',    'Ingresos por Alquiler de Equipos',                 '4','acreedora','detalle'],
            ['4.1.3',       '(-) Descuentos, Devoluciones y Rebajas en Ventas', '4','deudora',  'grupo'],
            ['4.1.3.01',    '(-) Devoluciones en Ventas',                       '4','deudora',  'detalle'],
            ['4.1.3.02',    '(-) Descuentos y Rebajas en Ventas',               '4','deudora',  'detalle'],
            ['4.2',         'Otros Ingresos No Operacionales',                  '4','acreedora','grupo'],
            ['4.2.1.01',    'Intereses y Rendimientos Financieros Ganados',     '4','acreedora','detalle'],
            ['4.2.1.02',    'Ganancia en Venta de Propiedades, Planta y Equipo','4','acreedora','detalle'],
            ['4.2.1.03',    'Ingresos por Arrendamientos',                      '4','acreedora','detalle'],
            ['4.2.1.04',    'Otros Ingresos No Operacionales',                  '4','acreedora','detalle'],

            // ══ 5. COSTOS Y GASTOS ════════════════════════════
            ['5',           'Costos Operativos y Gastos',                       '5','deudora',  'grupo'],
            ['5.1',         'Costo de Ventas y Producción',                     '5','deudora',  'grupo'],
            ['5.1.1',       'Costo de Ventas',                                  '5','deudora',  'grupo'],
            ['5.1.1.01',    'Costo de Ventas de Mercaderías Locales',           '5','deudora',  'detalle'],
            ['5.1.1.02',    'Costo de Ventas de Mercaderías Importadas',        '5','deudora',  'detalle'],
            ['5.1.1.03',    'Costo de Prestación de Servicios Técnicos',        '5','deudora',  'detalle'],
            ['5.1.1.04',    'Ajustes por Faltantes o Mermas de Inventario',     '5','deudora',  'detalle'],
            ['5.2',         'Gastos Operativos',                                '5','deudora',  'grupo'],
            ['5.2.1',       'Gastos de Personal',                               '5','deudora',  'grupo'],
            ['5.2.1.01',    'Sueldos, Salarios y Horas Extras',                 '5','deudora',  'detalle'],
            ['5.2.1.02',    'Comisiones y Bonos',                               '5','deudora',  'detalle'],
            ['5.2.1.03',    'Aporte Patronal IESS (11.15%)',                    '5','deudora',  'detalle'],
            ['5.2.1.04',    'Décimo Tercer Sueldo',                             '5','deudora',  'detalle'],
            ['5.2.1.05',    'Décimo Cuarto Sueldo',                             '5','deudora',  'detalle'],
            ['5.2.1.06',    'Vacaciones',                                       '5','deudora',  'detalle'],
            ['5.2.1.07',    'Fondos de Reserva',                                '5','deudora',  'detalle'],
            ['5.2.1.08',    'Capacitación al Personal',                         '5','deudora',  'detalle'],
            ['5.2.1.09',    'Uniformes y Equipos de Protección Personal',       '5','deudora',  'detalle'],
            ['5.2.2',       'Gastos Generales y Administrativos',               '5','deudora',  'grupo'],
            ['5.2.2.01',    'Honorarios Profesionales y Asesorías',             '5','deudora',  'detalle'],
            ['5.2.2.02',    'Arrendamientos de Locales y Bodegas',              '5','deudora',  'detalle'],
            ['5.2.2.03',    'Servicios Básicos',                                '5','deudora',  'detalle'],
            ['5.2.2.04',    'Mantenimiento y Reparación de Instalaciones',      '5','deudora',  'detalle'],
            ['5.2.2.05',    'Mantenimiento y Reparación de Vehículos',          '5','deudora',  'detalle'],
            ['5.2.2.06',    'Suministros y Materiales de Oficina',              '5','deudora',  'detalle'],
            ['5.2.2.07',    'Gastos de Viaje, Movilización y Viáticos',         '5','deudora',  'detalle'],
            ['5.2.2.08',    'Combustibles y Lubricantes',                       '5','deudora',  'detalle'],
            ['5.2.2.09',    'Seguros de Vehículos y Mercadería',                '5','deudora',  'detalle'],
            ['5.2.2.10',    'Impuestos, Tasas y Contribuciones Locales',        '5','deudora',  'detalle'],
            ['5.2.2.11',    'Publicidad, Marketing y Redes Sociales',           '5','deudora',  'detalle'],
            ['5.2.2.12',    'Gastos de Representación y Atención a Clientes',   '5','deudora',  'detalle'],
            ['5.2.2.13',    'Suscripciones y Membresías',                       '5','deudora',  'detalle'],
            ['5.2.2.14',    'Correo, Mensajería y Envíos',                      '5','deudora',  'detalle'],
            ['5.2.3',       'Gastos de Depreciación y Amortización',            '5','deudora',  'grupo'],
            ['5.2.3.01',    'Depreciación de Propiedades, Planta y Equipo',     '5','deudora',  'detalle'],
            ['5.2.3.02',    'Amortización de Activos Intangibles',              '5','deudora',  'detalle'],
            ['5.2.4',       'Gastos por Provisiones',                           '5','deudora',  'grupo'],
            ['5.2.4.01',    'Gasto por Cuentas Incobrables',                    '5','deudora',  'detalle'],
            ['5.3',         'Gastos Financieros',                               '5','deudora',  'grupo'],
            ['5.3.1.01',    'Intereses por Préstamos Bancarios',                '5','deudora',  'detalle'],
            ['5.3.1.02',    'Comisiones Bancarias y Pasarelas de Pago',         '5','deudora',  'detalle'],
            ['5.3.1.03',    'Pérdida por Diferencia en Cambio',                 '5','deudora',  'detalle'],
            ['5.4',         'Otros Gastos y No Deducibles',                     '5','deudora',  'grupo'],
            ['5.4.1.01',    'Gastos No Deducibles',                             '5','deudora',  'detalle'],
            ['5.4.1.02',    'Pérdida en Venta de Activos Fijos',                '5','deudora',  'detalle'],
            ['5.4.1.03',    'Otros Gastos Extraordinarios',                     '5','deudora',  'detalle'],
        ];
    }

    // Normaliza un código quitando ceros de relleno por segmento (p.ej. '1.1.1.01' y
    // '1.1.1.1' son la MISMA cuenta bajo dos convenciones de padding distintas). Este
    // normalizador es lo que faltaba: sin él, este comando compara por igualdad exacta
    // de string y nunca encuentra la cuenta ya existente si el padding no coincide
    // carácter por carácter — así se crearon ~70 cuentas duplicadas en 2026-07.
    private function normalizarCodigo(string $codigo): string
    {
        return implode('.', array_map(fn($seg) => ltrim($seg, '0') ?: '0', explode('.', $codigo)));
    }

    public function handle(): void
    {
        $cuentas = $this->getCuentas();

        $this->info('📒 Plan de Cuentas V2 — Altamira Light & Sound');
        $this->info('   Total cuentas a importar: ' . count($cuentas));
        $this->newLine();

        if ($this->option('preview')) {
            $this->table(
                ['Código', 'Nombre', 'Tipo BD', 'Permite asientos'],
                collect($cuentas)->map(fn($c) => [
                    $c[0],
                    mb_substr($c[1], 0, 50),
                    self::TIPO_MAP[$c[2]] ?? '?',
                    $c[4] === 'detalle' ? 'sí' : 'no',
                ])->toArray()
            );
            return;
        }

        // ── LIMPIAR solo cuentas sin asientos ────────────────
        if ($this->option('limpiar')) {
            $cuentasConAsientos = DB::table('asiento_detalles')
                ->pluck('cuenta_id')->unique()->values()->toArray();

            $eliminadas = DB::table('plan_cuentas')
                ->whereNotIn('id', $cuentasConAsientos)
                ->delete();

            $this->warn("   🗑️  {$eliminadas} cuentas sin asientos eliminadas.");
        }

        // ── CALCULAR NIVEL por puntos en el código ───────────
        $getNivel = fn(string $codigo): int =>
            str_contains($codigo, '.') ? substr_count($codigo, '.') + 1 : 1;

        // ── DETERMINAR CUENTA PADRE ──────────────────────────
        // Construye índice codigo→id en memoria para evitar N+1
        $indice = DB::table('plan_cuentas')
            ->pluck('id', 'codigo')
            ->toArray();

        // Índice por código NORMALIZADO (sin padding) → id, para encontrar cuentas
        // ya existentes aunque su código use otra convención de relleno.
        $indiceNormalizado = [];
        foreach (DB::table('plan_cuentas')->get(['id', 'codigo']) as $c) {
            $indiceNormalizado[$this->normalizarCodigo($c->codigo)] = $c->id;
        }

        $getPadreId = function(string $codigo) use (&$indice): ?int {
            $partes = explode('.', $codigo);
            if (count($partes) <= 1) return null;
            array_pop($partes);
            return $indice[implode('.', $partes)] ?? null;
        };

        // ── IMPORTAR ──────────────────────────────────────────
        $creadas      = 0;
        $actualizadas = 0;
        $omitidas     = 0;

        // Cuentas con asientos (no tocar estructura)
        $conAsientos = DB::table('asiento_detalles')
            ->pluck('cuenta_id')->unique()->values()->toArray();

        foreach ($cuentas as [$codigo, $nombre, $clase, /* naturaleza */, $tipoCuenta]) {
            $nivel          = $getNivel($codigo);
            $tipo           = self::TIPO_MAP[$clase] ?? 'activo';
            $permiteAsientos = $tipoCuenta === 'detalle';
            $padreId        = $getPadreId($codigo);

            $existente = DB::table('plan_cuentas')
                ->where('codigo', $codigo)
                ->first();

            // Si no hubo match exacto, buscar por código normalizado (misma cuenta,
            // distinto padding) antes de asumir que hay que crear una nueva.
            if (!$existente) {
                $idNormalizado = $indiceNormalizado[$this->normalizarCodigo($codigo)] ?? null;
                if ($idNormalizado) {
                    $existente = DB::table('plan_cuentas')->where('id', $idNormalizado)->first();
                }
            }

            if ($existente && \in_array($existente->id, $conAsientos)) {
                // Solo actualizar el nombre — nunca tocar estructura
                DB::table('plan_cuentas')
                    ->where('id', $existente->id)
                    ->update(['nombre' => $nombre]);
                $indice[$codigo] = $existente->id;
                $indiceNormalizado[$this->normalizarCodigo($codigo)] = $existente->id;
                $omitidas++;
                $this->line("   ⏭️  [{$codigo}] con asientos — solo nombre actualizado");
                continue;
            }

            $datos = [
                'codigo'          => $codigo,
                'nombre'          => $nombre,
                'tipo'            => $tipo,
                'nivel'           => $nivel,
                'padre_id'        => $padreId,
                'permite_asientos'=> $permiteAsientos,
                'estado'          => true,
            ];

            if ($existente) {
                DB::table('plan_cuentas')
                    ->where('id', $existente->id)
                    ->update($datos);
                $indice[$codigo] = $existente->id;
                $indiceNormalizado[$this->normalizarCodigo($codigo)] = $existente->id;
                $actualizadas++;
                $prefijo = str_repeat('  ', min($nivel - 1, 5));
                $this->line("   ✏️  {$prefijo}[{$codigo}] {$nombre}");
            } else {
                $nuevoId = DB::table('plan_cuentas')->insertGetId($datos);
                $indice[$codigo] = $nuevoId;
                $indiceNormalizado[$this->normalizarCodigo($codigo)] = $nuevoId;
                $creadas++;
                $prefijo = str_repeat('  ', min($nivel - 1, 5));
                $this->line("   ✅  {$prefijo}[{$codigo}] {$nombre}");
            }
        }

        // ── RESUMEN ───────────────────────────────────────────
        $this->newLine();
        $this->info('═══════════════════════════════════════');
        $this->info('✅ Importación completada:');
        $this->line("   Creadas:          {$creadas}");
        $this->line("   Actualizadas:     {$actualizadas}");
        $this->line("   Con asientos:     {$omitidas} (solo nombre actualizado)");
        $this->line("   TOTAL en BD:      " . DB::table('plan_cuentas')->count());
        $this->info('═══════════════════════════════════════');
    }
}
