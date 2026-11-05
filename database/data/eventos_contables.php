<?php

/**
 * Eventos contables de docs/Oct2026/01_Parametros_Contables.pdf (§5), solo lectura.
 * [id, sección, evento, debe, haber, estado]
 * Estado: correcto | incorrecto | incompleto | sin configurar | pendiente.
 * «No documentado» en debe/haber marca un evento sin cuentas definidas
 * (la pantalla lo señala; sigue pendiente de decisión, ver DEC en el documento 04).
 * Si se cambia una regla, se cambia primero en el documento 01 y luego aquí.
 */
return [
    // ── 5.1 Ventas, cuentas por cobrar y cobros ───────────────────────────
    ['PC-VEN-01', 'Ventas y cobranza', 'Venta local (cualquier forma de pago)', '1.01.03.01 Clientes Locales', '4.01.xx Venta por categoría; 2.01.02.01 IVA Cobrado en Ventas', 'incorrecto'],
    ['PC-VEN-02', 'Ventas y cobranza', 'Venta a cliente del exterior', '1.01.03.03 Clientes del Exterior', '4.01.xx Venta por categoría; 2.01.02.01 IVA (si aplica)', 'incorrecto'],
    ['PC-VEN-03', 'Ventas y cobranza', 'Venta a crédito', '1.01.03.01 Clientes Locales', '4.01.xx Venta por categoría; 2.01.02.01 IVA', 'incorrecto'],
    ['PC-VEN-04', 'Ventas y cobranza', 'Costo de venta de mercadería local', '5.01.xx Costo de venta por categoría', '1.01.04.xx Inventario por categoría', 'incorrecto'],
    ['PC-VEN-05', 'Ventas y cobranza', 'Devolución en ventas', '4.02.01 (-) Devoluciones en Ventas', 'No documentado', 'incompleto'],
    ['PC-VEN-06', 'Ventas y cobranza', 'Cobro en efectivo', '1.01.01.01 Caja General', '1.01.03.01 Clientes Locales', 'correcto'],
    ['PC-VEN-07', 'Ventas y cobranza', 'Cobro por transferencia', 'Banco elegido al cobrar (PC-BAN-01)', '1.01.03.01 Clientes Locales', 'sin configurar'],
    ['PC-VEN-08', 'Ventas y cobranza', 'Cobro con tarjeta', '1.01.03.04 Vouchers por Liquidar', '1.01.03.01 Clientes Locales', 'incorrecto'],
    ['PC-VEN-09', 'Ventas y cobranza', 'Cobro de venta a crédito', '1.01.01.01 Caja General o banco elegido', '1.01.03.01 Clientes Locales', 'correcto'],
    ['PC-VEN-10', 'Ventas y cobranza', 'Anticipo o reserva de cliente', '1.01.01.01 Caja General o banco elegido', '2.01.04 Anticipos recibidos de clientes', 'incorrecto'],
    ['PC-VEN-11', 'Ventas y cobranza', 'Aplicación del anticipo a la factura', '2.01.04 Anticipos recibidos de clientes', 'No documentado', 'pendiente'],
    ['PC-VEN-12', 'Ventas y cobranza', 'Retención recibida en el cobro', '1.01.05.02 Ret. IR que le realizaron; 1.01.05.03 Ret. IVA que le realizaron', '1.01.03.01 Clientes Locales', 'correcto'],
    ['PC-VEN-13', 'Ventas y cobranza', 'Cheque post fechado recibido de cliente', '1.01.03.05 Cheques en Cartera', '1.01.03.01 Clientes Locales', 'correcto'],
    ['PC-VEN-14', 'Ventas y cobranza', 'Depósito de cheque en cartera', 'Banco destino (PC-BAN-01)', '1.01.03.05 Cheques en Cartera', 'correcto'],
    ['PC-VEN-15', 'Ventas y cobranza', 'Entrega de regalo en factura', '6.01.15 Regalos y Promociones a Clientes', '1.01.04.08.01 / 1.01.04.08.02 según subcategoría', 'correcto'],
    ['PC-VEN-16', 'Ventas y cobranza', 'Factura de evento', '1.01.03.01 Clientes Locales', '4.03.02 Otros ingresos; 2.01.02.01 IVA Cobrado en Ventas', 'correcto'],
    // ── 5.2 Tarjetas ──────────────────────────────────────────────────────
    ['PC-TAR-01', 'Tarjetas', 'Liquidación de lote de tarjetas', 'Banco destino (neto); 6.01.10 comisión; 1.01.05.01 IVA de la comisión; 1.01.05.02 / 1.01.05.03 retenciones', '1.01.03.04 Vouchers por Liquidar (bruto del lote)', 'incompleto'],
    ['PC-TAR-02', 'Tarjetas', 'Diferencia en liquidación de tarjetas', '6.01.14 Diferencias en Conciliación (faltante)', '6.01.14 (sobrante)', 'incorrecto'],
    ['PC-TAR-03', 'Tarjetas', 'Contracargo o devolución del procesador', 'No documentado', 'No documentado', 'pendiente'],
    ['PC-TAR-04', 'Tarjetas', 'Intereses de tarjeta diferida', '6.01.10 Gastos Financieros y Comisiones', 'Banco o 1.01.03.04 según el lote', 'correcto'],
    // ── 5.3 Bancos y cheques ──────────────────────────────────────────────
    ['PC-BAN-01', 'Bancos', 'Cuenta contable de cada banco', '1.01.02.01.01 Pichincha; 1.01.02.02.01 ProduBanco; 1.01.02.02.02 Guayaquil; 1.01.02.03 Exterior', '—', 'sin configurar'],
    ['PC-BAN-04', 'Bancos', 'Pago a proveedor por transferencia', '2.01.01.01 / 2.01.01.02 / 2.01.01.03 según el proveedor', 'Banco elegido (PC-BAN-01)', 'correcto'],
    ['PC-BAN-05', 'Bancos', 'Aporte de socio en banco', 'Banco elegido (PC-BAN-01)', '3.01.01 Capital Neto', 'correcto'],
    ['PC-BAN-06', 'Bancos', 'Pago de gasto operativo o servicio por banco', '6.01.xx según el tipo de gasto', 'Banco elegido (PC-BAN-01)', 'correcto'],
    ['PC-BAN-07', 'Bancos', 'Pago de préstamo: capital', '2.02.01 Obligaciones con Instituciones Financieras', 'Banco elegido (PC-BAN-01)', 'correcto'],
    ['PC-BAN-08', 'Bancos', 'Pago de préstamo: interés', '6.01.10 Gastos Financieros y Comisiones', 'Banco elegido (PC-BAN-01)', 'correcto'],
    ['PC-BAN-09', 'Bancos', 'Pago de tarjeta de crédito de la empresa', '2.01.05 Tarjetas de Crédito por Pagar', 'Banco elegido (PC-BAN-01)', 'correcto'],
    ['PC-BAN-11', 'Bancos', 'Depósito de caja al banco', 'Banco elegido (PC-BAN-01)', '1.01.01.01 Caja General', 'correcto'],
    ['PC-BAN-12', 'Bancos', 'Transferencia entre bancos', 'Banco destino (PC-BAN-01)', 'Banco origen (PC-BAN-01)', 'correcto'],
    ['PC-BAN-13', 'Bancos', 'Intereses ganados (nota de crédito del banco)', 'Banco elegido (PC-BAN-01)', '4.03.01 Intereses Ganados', 'correcto'],
    ['PC-BAN-14', 'Bancos', 'Comisión bancaria (nota de débito del banco)', '6.01.10 Gastos Financieros y Comisiones', 'Banco elegido (PC-BAN-01)', 'correcto'],
    ['PC-BAN-15', 'Bancos', 'Sobrante de conciliación', 'Banco elegido (PC-BAN-01)', '6.01.14 Diferencias en Conciliación Bancaria', 'incorrecto'],
    ['PC-BAN-16', 'Bancos', 'Faltante de conciliación', '6.01.14 Diferencias en Conciliación Bancaria', 'Banco elegido (PC-BAN-01)', 'incorrecto'],
    ['PC-BAN-17', 'Bancos', 'Cheque emitido para pagar factura de proveedor', '2.01.01.xx según el proveedor', 'Banco seleccionado (PC-BAN-01)', 'correcto'],
    ['PC-BAN-18', 'Bancos', 'Cheque emitido para otro pago (sueldos, arriendo, impuestos)', 'Cuenta de obligación o gasto según la categoría (por confirmar)', 'Banco seleccionado (PC-BAN-01)', 'pendiente'],
    ['PC-BAN-19', 'Bancos', 'Anulación de cheque no cobrado', 'Reverso del asiento original', 'Reverso del asiento original', 'correcto'],
    ['PC-BAN-20', 'Bancos', 'Préstamo recibido del dueño', 'Banco elegido o 1.01.01.01 Caja General', '2.01.06 Cuenta por pagar al socio', 'correcto'],
    ['PC-BAN-21', 'Bancos', 'Pago o abono del préstamo al dueño', '2.01.06 Cuenta por pagar al socio', 'Banco elegido o 1.01.01.01 Caja General', 'correcto'],
    // ── 5.4 Caja ──────────────────────────────────────────────────────────
    ['PC-CAJ-03', 'Caja', 'Aporte de socio en efectivo', '1.01.01.01 Caja General', '3.01.01 Capital Neto', 'correcto'],
    ['PC-CAJ-04', 'Caja', 'Pago a proveedor en efectivo', '2.01.01.01 Proveedores Locales', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-05', 'Caja', 'Gasto operativo en efectivo', '6.01.xx según el tipo de gasto', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-06', 'Caja', 'Pago de servicios en efectivo', '6.01.04 Servicios Básicos', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-07', 'Caja', 'Anticipo de sueldo del socio', '3.01.04.01 (-) Retiros Personales del Propietario', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-08', 'Caja', 'Pago de préstamo en efectivo', '2.02.01 Obligaciones (capital); 6.01.10 (interés)', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-10', 'Caja', 'Envíos pagados en efectivo', '6.01.06 Transporte, Fletes y Envíos Locales', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-12', 'Caja', 'Sobrante de arqueo de caja', '1.01.01.01 Caja General', '6.01.14 Diferencias en Conciliación Bancaria', 'correcto'],
    ['PC-CAJ-13', 'Caja', 'Faltante de arqueo de caja', '6.01.14 Diferencias en Conciliación Bancaria', '1.01.01.01 Caja General', 'correcto'],
    ['PC-CAJ-15', 'Caja', 'Segunda caja (caja chica)', '1.01.01.02 Caja Chica', 'Según el movimiento', 'correcto'],
    // ── 5.5 Compras y cuentas por pagar ───────────────────────────────────
    ['PC-COM-01', 'Compras', 'Compra nacional de producto físico', '1.01.04.xx Inventario por categoría; 1.01.05.01 Crédito Tributario de IVA', '2.01.01.01 Proveedores Locales', 'incorrecto'],
    ['PC-COM-02', 'Compras', 'Compra sin producto (gasto o servicio)', '6.01.xx elegida por el usuario; 1.01.05.01 IVA (si aplica)', '2.01.01.01 Proveedores Locales', 'correcto'],
    ['PC-COM-03', 'Compras', 'Retenciones emitidas en compras', '—', '2.01.02.02 Ret. IR por Pagar; 2.01.02.03 Ret. IVA por Pagar', 'correcto'],
    ['PC-COM-04', 'Compras', 'Anticipo a proveedor', '1.01.06.01 Anticipos a Proveedores', 'Banco elegido o 1.01.01.01 Caja General', 'incorrecto'],
    ['PC-COM-05', 'Compras', 'Anticipo aplicado en la factura del proveedor', '—', '1.01.06.01 Anticipos a Proveedores', 'correcto'],
    // ── 5.6 Importaciones ─────────────────────────────────────────────────
    ['PC-IMP-01', 'Importaciones', 'Factura de mercadería del exterior', '1.01.04.07 Mercaderías en Tránsito', '1.01.06.01 Anticipo aplicado; 2.01.01.02 Proveedores del Exterior', 'sin configurar'],
    ['PC-IMP-02', 'Importaciones', 'Gastos de importación con factura local', '1.01.04.07 (base); 1.01.05.01 (IVA)', '2.01.01.03 Proveedores Logísticos; 2.01.02.02 / 2.01.02.03 retenciones', 'incompleto'],
    ['PC-IMP-03', 'Importaciones', 'Impuestos de importación e IVA de importación', '1.01.04.07 (ISD, FODINFA, advalorem, ICE); 1.01.05.01 (IVA)', 'Banco elegido o 1.01.01.01 Caja General', 'sin configurar'],
    ['PC-IMP-04', 'Importaciones', 'Honorarios del banco en importación', '1.01.04.07 En tránsito', 'Banco con que se paga (PC-BAN-01)', 'sin configurar'],
    ['PC-IMP-05', 'Importaciones', 'Liquidación de la importación (prorrateo)', '1.01.04.xx Inventario de su categoría', '1.01.04.07 En tránsito', 'sin configurar'],
    ['PC-IMP-06', 'Importaciones', 'Gasto de importación pagado directo por banco o caja', '1.01.04.07 Mercaderías en Tránsito', 'Banco elegido o 1.01.01.01 Caja General', 'sin configurar'],
    // ── 5.7 Inventario ────────────────────────────────────────────────────
    ['PC-INV-01', 'Inventario', 'Faltantes y mermas de inventario', '5.01.09 Faltantes y Mermas de Inventario', '1.01.04.xx Inventario por categoría', 'incorrecto'],
    ['PC-INV-02', 'Inventario', 'Inventario de mercaderías (parámetro genérico)', '1.01.04.xx Inventario de la subcategoría', '—', 'incorrecto'],
    ['PC-INV-03', 'Inventario', 'Costo de venta de mercadería importada', '5.01.xx Costo de venta de la subcategoría', '1.01.04.xx Inventario de la subcategoría', 'incorrecto'],
    ['PC-INV-04', 'Inventario', 'Componentes comprados afuera para taller', 'No documentado', 'No documentado', 'pendiente'],
    // ── 5.8 Nómina ────────────────────────────────────────────────────────
    ['PC-NOM-01', 'Nómina', 'Sueldos y horas extras', '6.01.01 Sueldos, Salarios y Beneficios Sociales', '2.01.03.01 Sueldos y Salarios por Pagar (neto)', 'correcto'],
    ['PC-NOM-02', 'Nómina', 'Aporte personal IESS (9,45%)', '2.01.03.01 Sueldos y Salarios por Pagar', '2.01.03.02 Aportes al IESS por Pagar', 'correcto'],
    ['PC-NOM-03', 'Nómina', 'Aporte patronal IESS (11,15%)', '6.01.02 Aporte Patronal al IESS y Fondos de Reserva', '2.01.03.02 Aportes al IESS por Pagar', 'correcto'],
    ['PC-NOM-04', 'Nómina', 'Fondos de reserva', '6.01.02 Aporte Patronal al IESS y Fondos de Reserva', '2.01.03.04 Fondos de Reserva por Pagar', 'incompleto'],
    ['PC-NOM-05', 'Nómina', 'Provisión décimo tercer sueldo', '6.01.01 Sueldos, Salarios y Beneficios Sociales', '2.01.03.03.01 Provisión Décimo Tercer Sueldo', 'incompleto'],
    ['PC-NOM-06', 'Nómina', 'Provisión décimo cuarto sueldo', '6.01.01 Sueldos, Salarios y Beneficios Sociales', '2.01.03.03.02 Provisión Décimo Cuarto Sueldo', 'incompleto'],
    ['PC-NOM-07', 'Nómina', 'Provisión vacaciones', '6.01.01 Sueldos, Salarios y Beneficios Sociales', '2.01.03.03.03 Provisión Vacaciones', 'incompleto'],
    ['PC-NOM-08', 'Nómina', 'Entrega de préstamo o anticipo a empleado', '1.01.06.02 Préstamos y Anticipos a Empleados', 'Banco elegido o 1.01.01.01 Caja General', 'incorrecto'],
    ['PC-NOM-09', 'Nómina', 'Préstamo descontado en nómina', '2.01.03.01 Sueldos y Salarios por Pagar', '1.01.06.02 Préstamos y Anticipos a Empleados', 'correcto'],
    ['PC-NOM-10', 'Nómina', 'Pago de nómina', '2.01.03.01 Sueldos y Salarios por Pagar', 'Banco de pago (PC-BAN-01)', 'correcto'],
    ['PC-NOM-11', 'Nómina', 'Participación de trabajadores', 'No documentado', '2.01.03.05 Participación Trabajadores en Utilidades', 'pendiente'],
    // ── 5.9 a 5.11 Activos fijos, impuestos, cierres ──────────────────────
    ['PC-AF-01', 'Activos fijos', 'Depreciación mensual', '6.01.08 Depreciación de Propiedades, Planta y Equipo', '1.02.02.xx (-) Depreciación acumulada del activo', 'correcto'],
    ['PC-TRI-01', 'Impuestos', 'Liquidación mensual del IVA', '2.01.02.01 IVA Cobrado en Ventas', '1.01.05.01 Crédito Tributario de IVA; 2.01.02.04 IVA por Pagar del mes', 'correcto'],
    ['PC-TRI-02', 'Impuestos', 'Provisión de impuesto a la renta', 'No documentado', '2.01.07 Impuesto a la Renta por Pagar', 'pendiente'],
    ['PC-CIE-01', 'Cierres y patrimonio', 'Cierre de cuentas de resultado', 'Ingresos 4.xx (por su saldo)', '3.01.03.01 Utilidad / (Pérdida) del Ejercicio Neto (costos y gastos contra la misma cuenta)', 'correcto'],
    ['PC-CIE-02', 'Cierres y patrimonio', 'Traslado del resultado del ejercicio', '3.01.03.01 (si hay utilidad)', '3.01.02.01 Utilidades Acumuladas / 3.01.02.02 Pérdidas Acumuladas', 'correcto'],
    ['PC-CIE-03', 'Cierres y patrimonio', 'Retiros del propietario al cierre', '3.01.01 Capital Neto', '3.01.04.01 (-) Retiros Personales del Propietario', 'pendiente'],
    ['PC-CIE-04', 'Cierres y patrimonio', 'Asiento de apertura', 'Saldos deudores del balance general', 'Saldos acreedores del balance general; contrapartida del primer arranque no documentada', 'pendiente'],
    // ── 5.12 Gastos operativos ────────────────────────────────────────────
    ['PC-GTO-01', 'Gastos operativos', 'Gasto genérico de compras sin cuenta (se elimina)', 'Ninguna: el usuario elige la cuenta de gasto', 'Proveedor, banco o caja', 'incorrecto'],
    ['PC-GTO-02', 'Gastos operativos', 'Honorarios y servicios profesionales', '6.01.11 Honorarios Profesionales', 'Proveedor, banco o caja', 'correcto'],
    ['PC-GTO-03', 'Gastos operativos', 'Arrendamiento de locales', '6.01.03 Arrendamiento de Inmuebles', 'Proveedor, banco o caja', 'correcto'],
    ['PC-GTO-04', 'Gastos operativos', 'Servicios básicos', '6.01.04 Servicios Básicos', 'Proveedor, banco o caja', 'correcto'],
    ['PC-GTO-05', 'Gastos operativos', 'Publicidad y marketing', '6.01.05 Publicidad, Marketing y Promoción Digital', 'Proveedor, banco o caja', 'correcto'],
    ['PC-GTO-06', 'Gastos operativos', 'Transporte, fletes y envíos locales', '6.01.06 Transporte, Fletes y Envíos Locales', 'Proveedor, banco o caja', 'correcto'],
    ['PC-GTO-07', 'Gastos operativos', 'Gastos no deducibles', '6.02.01 Gastos del Negocio sin Sustento Tributario', 'Proveedor, banco o caja', 'correcto'],
    // ── 5.13 Taller ───────────────────────────────────────────────────────
    ['PC-TAL-01', 'Taller', 'Factura de servicio técnico (mano de obra)', '1.01.03.01 Clientes Locales', '4.01.08 Ingresos por Servicio Técnico; 2.01.02.01 IVA Cobrado en Ventas', 'correcto'],
    ['PC-TAL-02', 'Taller', 'Repuestos facturados en la orden de trabajo', '1.01.03.01 Clientes Locales; 5.01.06 Costo de Ventas - Repuestos', '4.01.06 Venta de Repuestos; 2.01.02.01 IVA; 1.01.04.06 Inventario de Repuestos', 'correcto'],
    ['PC-TAL-03', 'Taller', 'Factura del técnico externo por trabajos de taller', '5.01.07.02 Costo de Subcontratación; 1.01.05.01 IVA (si aplica)', '2.01.01.01 Proveedores Locales', 'pendiente'],
    ['PC-TAL-04', 'Taller', 'Repuestos y mano de obra en garantía', 'No documentado', '1.01.04.06 Inventario de Repuestos (si se usa repuesto)', 'pendiente'],
];
