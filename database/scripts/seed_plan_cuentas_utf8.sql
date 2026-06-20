-- =============================================================================
-- seed_plan_cuentas.sql
-- Plan de Cuentas NIIF + Ejercicios Contables 2026 â€” Altamira Light & Sound
--
-- Uso: psql -U postgres -d altamira -f seed_plan_cuentas.sql
-- Idempotente: seguro ejecutar mÃºltiples veces (ON CONFLICT DO NOTHING).
--
-- DiseÃ±o:
--   plan_cuentas.codigo es UNIQUE global â†’ plan compartido, empresa_id = NULL.
--   padre_id se resuelve dinÃ¡micamente con regexp, no hardcodeado.
--   permite_asientos = TRUE solo en cuentas hoja (sin hijos en esta lista).
-- =============================================================================
SET client_encoding TO 'UTF8';
BEGIN;

-- ---------------------------------------------------------------------------
-- 1. Staging temporal
-- ---------------------------------------------------------------------------
CREATE TEMP TABLE IF NOT EXISTS _cuentas_stage (
    codigo TEXT PRIMARY KEY,
    nombre TEXT NOT NULL,
    tipo   TEXT NOT NULL
);

TRUNCATE _cuentas_stage;

INSERT INTO _cuentas_stage (codigo, nombre, tipo) VALUES

-- ============================================================
-- ACTIVOS
-- ============================================================
('1',           'Activos',                                                    'activo'),
('1.1',         'Activo Corriente',                                           'activo'),
('1.1.1',       'Efectivo y Equivalentes al Efectivo',                        'activo'),
('1.1.1.1',     'Caja General',                                               'activo'),
('1.1.1.2',     'Cajas Chicas y Fondos',                                      'activo'),
('1.1.1.3',     'Bancos Locales',                                             'activo'),
('1.1.1.4',     'Bancos del Exterior',                                        'activo'),
('1.1.1.5',     'Dinero ElectrÃ³nico / Pasarelas de Pago',                    'activo'),
('1.1.2',       'Inversiones Financieras a Corto Plazo',                      'activo'),
('1.1.2.1',     'Inversiones a Costo Amortizado (PÃ³lizas / DPFs)',           'activo'),
('1.1.3',       'Cuentas y Documentos por Cobrar',                            'activo'),
('1.1.3.1',     'Clientes Locales',                                           'activo'),
('1.1.3.2',     'Clientes del Exterior',                                      'activo'),
('1.1.3.3',     'Anticipos a Proveedores',                                    'activo'),
('1.1.3.4',     'PrÃ©stamos y Anticipos a Empleados',                         'activo'),
('1.1.3.5',     '(-) ProvisiÃ³n Cuentas Incobrables',                         'activo'),
('1.1.4',       'Inventarios',                                                'activo'),
('1.1.4.1',     'Inventario de MercaderÃ­a',                                   'activo'),
('1.1.4.2',     'Inventario de Materia Prima y Suministros',                  'activo'),
('1.1.4.3',     'Inventario en TrÃ¡nsito',                                    'activo'),
('1.1.4.4',     '(-) ProvisiÃ³n por Deterioro de Inventarios',                'activo'),
('1.1.5',       'Activos por Impuestos Corrientes',                           'activo'),
('1.1.5.1',     'CrÃ©dito Tributario por IVA',                                'activo'),
('1.1.5.2',     'CrÃ©dito Tributario por Retenciones de IVA',                 'activo'),
('1.1.5.3',     'CrÃ©dito Tributario por Retenciones de Impuesto a la Renta', 'activo'),
('1.1.5.4',     'Anticipo de Impuesto a la Renta',                           'activo'),
('1.1.6',       'Gastos Pagados por Anticipado',                              'activo'),
('1.1.6.1',     'Seguros Pagados por Anticipado',                             'activo'),
('1.1.6.2',     'Arriendos Pagados por Anticipado',                           'activo'),
('1.1.7',       'Otros Activos Corrientes',                                   'activo'),
('1.2',         'Activo No Corriente',                                        'activo'),
('1.2.1',       'Propiedades, Planta y Equipo',                               'activo'),
('1.2.1.1',     'Terrenos',                                                   'activo'),
('1.2.1.2',     'Edificios',                                                  'activo'),
('1.2.1.3',     'Instalaciones',                                              'activo'),
('1.2.1.4',     'Muebles y Enseres',                                          'activo'),
('1.2.1.5',     'Maquinaria y Equipos',                                       'activo'),
('1.2.1.6',     'Equipos de ComputaciÃ³n',                                    'activo'),
('1.2.1.7',     'VehÃ­culos y Equipos de Transporte',                         'activo'),
('1.2.1.8',     'Repuestos y Herramientas',                                   'activo'),
('1.2.1.9',     'Construcciones en Curso',                                    'activo'),
('1.2.1.10',    '(-) DepreciaciÃ³n Acumulada de PPE',                         'activo'),
('1.2.1.10.1',  '(-) Dep. Acum. Edificios',                                  'activo'),
('1.2.1.10.2',  '(-) Dep. Acum. Instalaciones',                              'activo'),
('1.2.1.10.3',  '(-) Dep. Acum. Muebles y Enseres',                         'activo'),
('1.2.1.10.4',  '(-) Dep. Acum. Maquinaria y Equipos',                      'activo'),
('1.2.1.10.5',  '(-) Dep. Acum. Equipos de ComputaciÃ³n',                    'activo'),
('1.2.1.10.6',  '(-) Dep. Acum. VehÃ­culos',                                 'activo'),
('1.2.1.11',    '(-) Deterioro Acumulado de PPE',                            'activo'),
('1.2.2',       'Propiedades de InversiÃ³n',                                  'activo'),
('1.2.2.1',     'Terrenos (InversiÃ³n)',                                       'activo'),
('1.2.2.2',     'Edificios (InversiÃ³n)',                                      'activo'),
('1.2.2.3',     '(-) DepreciaciÃ³n Acumulada de Propiedades de InversiÃ³n',   'activo'),
('1.2.3',       'Activos Intangibles',                                        'activo'),
('1.2.3.1',     'Software y Licencias',                                       'activo'),
('1.2.3.2',     'Marcas y Patentes',                                          'activo'),
('1.2.3.3',     '(-) AmortizaciÃ³n Acumulada de Intangibles',                 'activo'),
('1.2.4',       'Activos por Impuestos Diferidos',                            'activo'),
('1.2.4.1',     'Activo por Impuesto Diferido',                               'activo'),
('1.2.5',       'Activos Financieros y Cuentas por Cobrar a Largo Plazo',    'activo'),
('1.2.5.1',     'Cuentas y Documentos por Cobrar a Largo Plazo',             'activo'),
('1.2.5.2',     'Inversiones a Costo Amortizado a Largo Plazo',              'activo'),
('1.2.5.3',     '(-) ProvisiÃ³n Cuentas Incobrables a Largo Plazo',          'activo'),

-- ============================================================
-- PASIVOS
-- ============================================================
('2',           'Pasivos',                                                    'pasivo'),
('2.1',         'Pasivo Corriente',                                           'pasivo'),
('2.1.1',       'Cuentas y Documentos por Pagar Comerciales',                 'pasivo'),
('2.1.1.1',     'Proveedores Locales',                                        'pasivo'),
('2.1.1.2',     'Proveedores del Exterior',                                   'pasivo'),
('2.1.1.3',     'Anticipos de Clientes',                                      'pasivo'),
('2.1.2',       'Obligaciones con Instituciones Financieras',                 'pasivo'),
('2.1.2.1',     'PrÃ©stamos Bancarios a Corto Plazo',                         'pasivo'),
('2.1.2.2',     'Tarjetas de CrÃ©dito Corporativas',                          'pasivo'),
('2.1.3',       'Obligaciones con la AdministraciÃ³n Tributaria',              'pasivo'),
('2.1.3.1',     'Retenciones en la Fuente de IR por Pagar',                  'pasivo'),
('2.1.3.2',     'Retenciones de IVA por Pagar',                              'pasivo'),
('2.1.3.3',     'Impuesto a la Renta por Pagar del Ejercicio',               'pasivo'),
('2.1.3.4',     'IVA Ventas por Pagar',                                       'pasivo'),
('2.1.4',       'Obligaciones Laborales y Sociales',                          'pasivo'),
('2.1.4.1',     'NÃ³mina por Pagar',                                          'pasivo'),
('2.1.4.2',     'Obligaciones con el IESS - Aporte Patronal',                'pasivo'),
('2.1.4.3',     'Obligaciones con el IESS - Aporte Personal',                'pasivo'),
('2.1.4.4',     'Obligaciones con el IESS - PrÃ©stamos',                      'pasivo'),
('2.1.4.5',     'DÃ©cimo Tercer Sueldo por Pagar',                            'pasivo'),
('2.1.4.6',     'DÃ©cimo Cuarto Sueldo por Pagar',                            'pasivo'),
('2.1.4.7',     'Vacaciones por Pagar',                                       'pasivo'),
('2.1.4.8',     'Fondos de Reserva por Pagar',                               'pasivo'),
('2.1.4.9',     'Utilidades a Trabajadores (15%) por Pagar',                 'pasivo'),
('2.1.5',       'Obligaciones con Accionistas y Relacionadas',                'pasivo'),
('2.1.5.1',     'PrÃ©stamos de Accionistas a Corto Plazo',                    'pasivo'),
('2.1.5.2',     'Dividendos por Pagar',                                       'pasivo'),
('2.1.6',       'Otros Pasivos Corrientes',                                   'pasivo'),
('2.1.6.1',     'PorciÃ³n Corriente de Obligaciones a Largo Plazo',           'pasivo'),
('2.1.6.2',     'Provisiones a Corto Plazo',                                  'pasivo'),
('2.2',         'Pasivo No Corriente',                                        'pasivo'),
('2.2.1',       'Cuentas y Documentos por Pagar a Largo Plazo',              'pasivo'),
('2.2.1.1',     'Proveedores Locales a Largo Plazo',                         'pasivo'),
('2.2.1.2',     'Proveedores del Exterior a Largo Plazo',                    'pasivo'),
('2.2.1.3',     'Documentos / Letras por Pagar a Largo Plazo',              'pasivo'),
('2.2.2',       'Obligaciones Financieras a Largo Plazo',                    'pasivo'),
('2.2.2.1',     'PrÃ©stamos Bancarios a Largo Plazo',                         'pasivo'),
('2.2.3',       'Anticipos a Largo Plazo',                                    'pasivo'),
('2.2.3.1',     'Anticipos de Clientes a Largo Plazo',                       'pasivo'),
('2.2.4',       'Otras Cuentas por Pagar a Largo Plazo',                     'pasivo'),
('2.2.4.1',     'PrÃ©stamos de Accionistas a Largo Plazo',                    'pasivo'),
('2.2.4.2',     'Pasivos por Arrendamientos a Largo Plazo',                  'pasivo'),
('2.2.5',       'Provisiones a Largo Plazo',                                  'pasivo'),
('2.2.5.1',     'ProvisiÃ³n para JubilaciÃ³n Patronal',                        'pasivo'),
('2.2.5.2',     'ProvisiÃ³n para Desahucio',                                  'pasivo'),
('2.2.5.3',     'ProvisiÃ³n por GarantÃ­as de Productos',                      'pasivo'),
('2.2.5.4',     'ProvisiÃ³n por Litigios y Demandas',                         'pasivo'),
('2.2.6',       'Pasivos Diferidos',                                          'pasivo'),
('2.2.6.1',     'Pasivo por Impuesto Diferido',                              'pasivo'),

-- ============================================================
-- PATRIMONIO
-- ============================================================
('3',           'Patrimonio',                                                 'patrimonio'),
('3.1',         'Patrimonio Neto',                                            'patrimonio'),
('3.1.1',       'Capital',                                                    'patrimonio'),
('3.1.1.01',    'Capital Suscrito o Asignado',                               'patrimonio'),
('3.1.1.02',    '(-) Capital Suscrito No Pagado',                            'patrimonio'),
('3.1.2',       'Reservas',                                                   'patrimonio'),
('3.1.2.01',    'Reserva Legal',                                              'patrimonio'),
('3.1.2.02',    'Reservas Facultativas',                                      'patrimonio'),
('3.1.2.03',    'Reservas Estatutarias',                                      'patrimonio'),
('3.1.3',       'Otros Resultados Integrales Acumulados',                     'patrimonio'),
('3.1.3.01',    'SuperÃ¡vit por RevaluaciÃ³n de PPE',                          'patrimonio'),
('3.1.3.02',    'Ganancias y PÃ©rdidas Actuariales',                          'patrimonio'),
('3.1.4',       'Resultados Acumulados',                                      'patrimonio'),
('3.1.4.01',    'Ganancias Acumuladas',                                       'patrimonio'),
('3.1.4.02',    '(-) PÃ©rdidas Acumuladas',                                   'patrimonio'),
('3.1.4.03',    'Resultados Acumulados por AdopciÃ³n NIIF',                   'patrimonio'),
('3.1.5',       'Resultados del Periodo',                                     'patrimonio'),
('3.1.5.01',    'Utilidad del Periodo',                                       'patrimonio'),
('3.1.5.02',    '(-) PÃ©rdida del Periodo',                                   'patrimonio'),
('3.1.6',       'Aportes de Socios o Accionistas',                           'patrimonio'),
('3.1.6.01',    'Aportes para Futuras Capitalizaciones',                      'patrimonio'),

-- ============================================================
-- INGRESOS
-- ============================================================
('4',           'Ingresos',                                                   'ingreso'),
('4.1',         'Ingresos de Actividades Ordinarias',                         'ingreso'),
('4.1.1',       'Venta de Bienes',                                            'ingreso'),
('4.1.1.01',    'Venta de MercaderÃ­as Locales',                              'ingreso'),
('4.1.1.02',    'Venta de MercaderÃ­as al Exterior',                          'ingreso'),
('4.1.2',       'PrestaciÃ³n de Servicios',                                   'ingreso'),
('4.1.2.01',    'Ingresos por Servicios TÃ©cnicos y Mantenimiento',           'ingreso'),
('4.1.2.02',    'Ingresos por Servicios de CapacitaciÃ³n y AsesorÃ­as',        'ingreso'),
('4.1.3',       '(-) Descuentos, Devoluciones y Rebajas en Ventas',          'ingreso'),
('4.1.3.01',    '(-) Devoluciones en Ventas',                                'ingreso'),
('4.1.3.02',    '(-) Descuentos y Rebajas en Ventas',                        'ingreso'),
('4.2',         'Otros Ingresos',                                             'ingreso'),
('4.2.1',       'Otros Ingresos',                                             'ingreso'),
('4.2.1.01',    'Ingresos Financieros',                                       'ingreso'),
('4.2.1.02',    'Ganancia en Venta de PPE',                                  'ingreso'),
('4.2.1.03',    'Ingresos por Arrendamientos',                               'ingreso'),
('4.2.1.09',    'Otros Ingresos Varios',                                      'ingreso'),

-- ============================================================
-- GASTOS
-- ============================================================
('5',           'Gastos',                                                     'gasto'),
('5.1',         'Costo de Ventas y ProducciÃ³n',                              'gasto'),
('5.1.1',       'Costo de Ventas',                                            'gasto'),
('5.1.1.1',     'Costo de Ventas de MercaderÃ­as Locales',                    'gasto'),
('5.1.1.2',     'Costo de Ventas de MercaderÃ­as Importadas',                 'gasto'),
('5.1.1.3',     'Costo de PrestaciÃ³n de Servicios TÃ©cnicos',                 'gasto'),
('5.1.1.4',     'Ajustes por Faltantes o Mermas de Inventario',              'gasto'),
('5.2',         'Gastos Operativos',                                          'gasto'),
('5.2.1',       'Gastos de Personal',                                         'gasto'),
('5.2.1.01',    'Sueldos y Salarios',                                         'gasto'),
('5.2.1.02',    'Horas Extras y Suplementarias',                              'gasto'),
('5.2.1.03',    'Comisiones y Bonos',                                         'gasto'),
('5.2.1.04',    'Aporte Patronal IESS (11.15%)',                              'gasto'),
('5.2.1.05',    'DÃ©cimo Tercer Sueldo',                                      'gasto'),
('5.2.1.06',    'DÃ©cimo Cuarto Sueldo',                                      'gasto'),
('5.2.1.07',    'Vacaciones',                                                 'gasto'),
('5.2.1.08',    'Fondos de Reserva',                                          'gasto'),
('5.2.1.09',    'CapacitaciÃ³n al Personal',                                   'gasto'),
('5.2.1.10',    'Uniformes y Equipos de ProtecciÃ³n',                          'gasto'),
('5.2.2',       'Gastos Generales y Administrativos',                         'gasto'),
('5.2.2.01',    'Honorarios Profesionales y AsesorÃ­as',                      'gasto'),
('5.2.2.02',    'Arrendamientos de Locales y Bodegas',                       'gasto'),
('5.2.2.03',    'Servicios BÃ¡sicos',                                          'gasto'),
('5.2.2.04',    'Mantenimiento y ReparaciÃ³n de Instalaciones',                'gasto'),
('5.2.2.05',    'Mantenimiento y ReparaciÃ³n de VehÃ­culos',                   'gasto'),
('5.2.2.06',    'Suministros y Materiales de Oficina',                        'gasto'),
('5.2.2.07',    'Gastos de Viaje, MovilizaciÃ³n y ViÃ¡ticos',                 'gasto'),
('5.2.2.08',    'Combustibles y Lubricantes',                                 'gasto'),
('5.2.2.09',    'Seguros de VehÃ­culos y MercaderÃ­a',                         'gasto'),
('5.2.2.10',    'Impuestos, Tasas y Contribuciones',                          'gasto'),
('5.2.2.11',    'Publicidad, Marketing y Redes Sociales',                     'gasto'),
('5.2.3',       'Gastos de DepreciaciÃ³n y AmortizaciÃ³n',                     'gasto'),
('5.2.3.01',    'DepreciaciÃ³n de Propiedades, Planta y Equipo',              'gasto'),
('5.2.3.02',    'AmortizaciÃ³n de Activos Intangibles',                       'gasto'),
('5.2.4',       'Gastos por Provisiones',                                     'gasto'),
('5.2.4.01',    'Gasto por Cuentas Incobrables',                             'gasto'),
('5.2.4.02',    'Gasto ProvisiÃ³n JubilaciÃ³n Patronal y Desahucio',           'gasto'),
('5.3',         'Gastos Financieros',                                         'gasto'),
('5.3.1',       'Gastos Financieros',                                         'gasto'),
('5.3.1.01',    'Intereses por PrÃ©stamos Bancarios',                         'gasto'),
('5.3.1.02',    'Comisiones Bancarias y Pasarelas de Pago',                  'gasto'),
('5.3.1.03',    'Diferencia en Cambio',                                       'gasto'),
('5.4',         'Otros Gastos y No Deducibles',                              'gasto'),
('5.4.1',       'Otros Gastos',                                               'gasto'),
('5.4.1.01',    'Gastos No Deducibles Locales',                              'gasto'),
('5.4.1.02',    'PÃ©rdida en Venta de Activos Fijos',                         'gasto'),
('5.4.1.03',    'Otros Gastos Extraordinarios',                               'gasto');

-- ---------------------------------------------------------------------------
-- 2. Insertar en plan_cuentas (plan compartido: empresa_id = NULL)
--    nivel    = nÃºmero de puntos en el cÃ³digo + 1
--    permite_asientos = TRUE solo si no hay cuentas hijas en el catÃ¡logo
-- ---------------------------------------------------------------------------
INSERT INTO plan_cuentas
    (empresa_id, codigo, nombre, tipo, padre_id, nivel, permite_asientos, estado)
SELECT
    NULL::bigint,
    t.codigo,
    t.nombre,
    t.tipo,
    NULL,   -- padre_id se corrige en el UPDATE siguiente
    (length(t.codigo) - length(replace(t.codigo, '.', '')) + 1)::smallint,
    NOT EXISTS (
        SELECT 1
        FROM   _cuentas_stage t2
        WHERE  t2.codigo LIKE t.codigo || '.%'
    ),
    TRUE
FROM  _cuentas_stage t
ORDER BY t.codigo
ON CONFLICT (codigo) DO NOTHING;

-- ---------------------------------------------------------------------------
-- 3. Resolver padre_id dinÃ¡micamente
--    Para cÃ³digo 'A.B.C', el padre es el registro con cÃ³digo 'A.B'.
--    Solo actualiza filas cuyo padre_id todavÃ­a sea NULL (idempotente).
-- ---------------------------------------------------------------------------
UPDATE plan_cuentas pc
SET    padre_id = parent.id
FROM   plan_cuentas parent
WHERE  strpos(pc.codigo, '.') > 0                              -- tiene al menos un punto
  AND  parent.codigo = regexp_replace(pc.codigo, '\.[^.]+$', '') -- cÃ³digo del padre
  AND  pc.padre_id IS NULL;                                    -- no actualizar si ya estÃ¡ resuelto

-- ---------------------------------------------------------------------------
-- 4. Ejercicios contables 2026 â€” 12 meses Ã— 2 empresas = 24 filas
-- ---------------------------------------------------------------------------
INSERT INTO ejercicios_contables
    (empresa_id, anio, mes, descripcion, fecha_apertura, estado)
SELECT
    e.id,
    2026,
    m.mes,
    to_char(make_date(2026, m.mes, 1), 'TMMonth') || ' 2026',
    make_date(2026, m.mes, 1),
    'abierto'
FROM  generate_series(1, 12) AS m(mes)
CROSS JOIN (SELECT id FROM empresas WHERE id IN (1, 2)) e
ON CONFLICT (empresa_id, anio, mes) DO NOTHING;

-- ---------------------------------------------------------------------------
-- Limpieza
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS _cuentas_stage;

COMMIT;

-- ---------------------------------------------------------------------------
-- VerificaciÃ³n (ejecutar manualmente para confirmar)
-- ---------------------------------------------------------------------------
-- SELECT COUNT(*)                              FROM plan_cuentas;            -- 197
-- SELECT COUNT(*)                              FROM plan_cuentas WHERE permite_asientos = TRUE;
-- SELECT COUNT(*)                              FROM plan_cuentas WHERE padre_id IS NULL; -- 5 (raÃ­ces)
-- SELECT COUNT(*)                              FROM ejercicios_contables;    -- 24
-- SELECT pc.codigo, pc.nombre, pc.padre_id, p.codigo AS padre_codigo
--   FROM plan_cuentas pc LEFT JOIN plan_cuentas p ON p.id = pc.padre_id
--  WHERE pc.padre_id IS NOT NULL AND p.id IS NULL;  -- debe retornar 0 (sin huÃ©rfanos)
