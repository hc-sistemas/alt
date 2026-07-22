# DISTRIBUCIÓN DE MÓDULOS — 2 DESARROLLADORES
# Sistema ERP Altamira — Fase 2 en adelante
# Base instalada: Laravel 12 + React 19 + Inertia v2 + Tailwind v4

---

## PRINCIPIO DE DIVISIÓN

El análisis de dependencias revela dos flujos naturales que casi no se tocan:

```
FLUJO COMERCIAL          FLUJO FINANCIERO/ADMIN
──────────────────       ──────────────────────
Clientes                 Colaboradores
Inventario/Productos     Plan de Cuentas
Ventas/Facturación SRI   Compras e Importaciones
Taller (Fix)             Bancos y Cajas
CxC + Reportes ventas    RRHH + Nómina
                         Reportes financieros + SRI
```

El único punto de contacto entre los dos flujos es `asientos_contables`.
Se resuelve con un **AsientoService compartido** que Dev 2 construye
y Dev 1 consume. Nunca escriben al mismo archivo al mismo tiempo.

---

## DESARROLLADOR 1 — "STACK COMERCIAL"
### Módulos: Personas (Clientes/Proveedores) · Inventario · Ventas · Taller

---

### SEMANA 1-2: Módulo Personas — Clientes y Proveedores
*(Requerido por Dev 2 también — hacerlo PRIMERO y avisar cuando esté listo)*

**Clientes** `resources/js/Pages/Personas/Clientes/`
- Lista con búsqueda por RUC/nombre, filtro por tipo
- Formulario: datos personales, dirección, crédito (días + cupo), retención
- Estado de cuenta del cliente (saldo CxC)
- Historial de facturas por cliente
- Importación masiva desde Excel (.xlsx)

**Proveedores** `resources/js/Pages/Personas/Proveedores/`
- Tabs: Nacional / Internacional
- Nacional: RUC, datos, crédito, días
- Internacional: campos adicionales ciudad, divisa, país
- Ambos: toggle crédito → campo días de crédito
- Conexión directa al dashboard de CxP

**Transportistas** `resources/js/Pages/Personas/Transportistas/`
- CRUD simple: razón social, placa, identificación, contacto

**Tablas que escribe:**
`clientes`, `proveedores`, `transportistas`

---

### SEMANA 2-3: Módulo Inventario

**Configuración previa** `resources/js/Pages/Inventario/Configuracion/`
- Marcas: CRUD con logo e ícono
- Categorías: árbol jerárquico (padre → hijo)
- Bodegas: nombre, tipo, empresa, centro costo
  - Tipos: general, importacion, taller, reserva, cuarentena

**Productos** `resources/js/Pages/Inventario/Productos/`
- Formulario con pestañas:
  - General: código, nombre, descripción, marca, categoría, unidad, tipo
  - Precios: PVP, PVD, costo, descuento máximo, IVA%, ICE%
  - Inventario: stock mínimo, stock máximo, bodega default
  - Contabilidad: cuenta inventario, cuenta costo ventas, cuenta ventas
    *(estas cuentas las elige de un selector que consulta `plan_cuentas`
     — Dev 2 debe tener al menos el CRUD de plan_cuentas antes)*
  - Series: toggle "requiere número de serie"
- Si tipo = DJ/accesorio/computadora → campo serie obligatorio al ingresar
- Ingreso de productos: solo por importación o factura de proveedor

**Listas de precio** `resources/js/Pages/Inventario/ListasPrecio/`
- Tabla PVP / PVD por producto y empresa
- Edición masiva desde Excel

**Kárdex / Movimientos** `resources/js/Pages/Inventario/Kardex/`
- Vista por producto: timeline de entradas y salidas
- Filtros: bodega, fechas, tipo movimiento
- Reporte de saldos disponibles por bodega
- Reporte de stock crítico (< stock mínimo)

**Traslados entre bodegas** `resources/js/Pages/Inventario/Traslados/`
- Formulario: bodega origen → destino, productos + cantidades
- Estado: pendiente → (receptor acepta con conteo físico) → aceptado
- Al aceptar: actualiza `inventario_saldos` en ambas bodegas
- Log en `inventario_movimientos` tipo='traslado'

**Inventario físico** `resources/js/Pages/Inventario/InvFisico/`
- Toma de inventario: bodega + fecha + auditor
- Comparativo: stock sistema vs conteo físico
- Ajuste automático con asiento contable (llama a AsientoService)

**Activos Fijos** `resources/js/Pages/Inventario/ActivosFijos/`
- CRUD: nombre, fecha adquisición, costo, vida útil, depreciación acumulada
- Reporte de depreciación mensual

**Tablas que escribe:**
`marcas`, `categorias_producto`, `bodegas`, `productos`, `producto_series`,
`listas_precio`, `inventario_movimientos`, `inventario_saldos`,
`traslados_bodega`, `traslado_detalles`, `erp_inv_fisico`, `activos_fijos`

**Servicio que debe EXPONER para Dev 2:**
```php
// app/Services/InventarioService.php
// Dev 2 llama esto cuando registra una compra o devolución
interface InventarioServiceInterface {
    public function ingresarStock(int $productoId, int $bodegaId, float $cantidad, float $costo, string $docTipo, int $docId): void;
    public function egresarStock(int $productoId, int $bodegaId, float $cantidad, string $docTipo, int $docId): void;
    public function reservarStock(int $productoId, int $bodegaId, float $cantidad): void;
    public function liberarReserva(int $productoId, int $bodegaId, float $cantidad): void;
    public function getSaldoDisponible(int $productoId, int $bodegaId): float;
}
```

---

### SEMANA 3-5: Módulo Ventas y Facturación SRI

**SecuencialService** (construir primero — lo usan facturas, NC, retenciones, guías)
```php
// app/Services/SecuencialService.php
public function siguiente(int $empresaId, string $tipo): string
// Devuelve '001-001-000000001' y avanza el secuencial
// Con lock pesimista para evitar duplicados en concurrencia
```

**Proformas** `resources/js/Pages/Ventas/Proformas/`
- Misma estructura visual que factura pero sin envío SRI
- Estado: pendiente → facturada → vencida → anulada
- Conversión a factura con un clic
- Alerta 24h antes del vencimiento (notificación automática)

**Prefacturas / Reservas** `resources/js/Pages/Ventas/Prefacturas/`
- Panel de abonos: muestra saldo pendiente, historial de pagos
- Botón "+ Abonar": modal con forma de pago + monto
- Asiento automático por abono (llama a AsientoService de Dev 2):
  DEBE: Caja/Banco → HABER: 2.1.1.3 (Anticipos Clientes)
- Botón "Crear Factura" habilitado cuando saldo = 0
- Producto apartado en bodega reserva automáticamente

**Facturas Electrónicas SRI** `resources/js/Pages/Ventas/Facturas/`

  *Formulario de factura:*
  - Selector cliente (buscar por RUC o nombre con autocomplete)
  - Tabla de productos: código, descripción, cant, precio, desc%, IVA, total
  - Validación de descuento: si supera `limites_descuento.descuento_maximo_pct`
    → mostrar `AprobacionModal` (ya construido en Fase 1)
  - Si precio < costo → bloquear, solicitar aprobación tipo 'precio_bajo_costo'
  - Formas de pago: efectivo, tarjeta, transferencia, crédito (con días)
  - Totales: subtotal 0%, subtotal 15%, IVA, descuento, TOTAL
  - Botón "Guardar y Enviar al SRI"

  *Ciclo SRI (via Job en cola):*
  ```
  1. Guardar factura en BD (estado='pendiente')
  2. Generar XML según esquema SRI
  3. Firmar XML con certificado p12 (comando OpenSSL)
  4. Enviar a webservice SRI (ambiente pruebas/producción)
  5. Recibir autorización → actualizar clave_acceso, autorizacion, estado='autorizada'
  6. Generar PDF RIDE (dompdf)
  7. Enviar email al cliente con PDF + XML adjuntos
  8. Si falla → estado='rechazada', mostrar notificación con error SRI
  ```

  *Lista de facturas:*
  - Filtros: fecha, cliente, estado SRI, forma pago, centro costo
  - Estados con badge: pendiente(gris), autorizada(verde), rechazada(rojo), anulada(naranja)
  - Acciones: Ver RIDE PDF | Reenviar email | Anular (solo mismo día, solo SuperAdmin)
  - Exportar a Excel

  *Asiento automático al autorizar:*
  - Llama a `AsientoService::facturaAutorizada($factura)`
  - Dev 2 implementa la lógica contable, Dev 1 solo llama el método

**Notas de Crédito** `resources/js/Pages/Ventas/NotasCredito/`
- Seleccionar factura origen → listar ítems
- Checkbox por ítem a devolver + cantidad
- Flujo físico: ítem devuelto va a bodega "Cuarentena/Garantías"
- Flujo contable: cruzar contra CxC pendiente o generar saldo a favor
- Envío SRI igual que factura

**Retenciones** `resources/js/Pages/Ventas/Retenciones/`
- Emitidas por clientes agentes de retención
- Asociar a factura específica
- Tipos: IR (varios %) + IVA (30%, 70%, 100%)
- Envío SRI

**Guías de Remisión** `resources/js/Pages/Ventas/GuiasRemision/`
- Asociar a factura o traslado
- Selector transportista
- Envío SRI

**Cuentas por Cobrar** `resources/js/Pages/Ventas/CxC/`
- Panel por cliente: facturas pendientes, saldo total
- Reporte de antigüedad de saldos: 30/60/90 días
- Botón "Registrar cobro": modal con forma de pago
- Botón "Castigar deuda" (>360 días, solo SuperAdmin, con aprobación)
- Alerta automática: día 0, +15, +30 días de vencimiento

**Tablas que escribe:**
`proformas`, `proforma_detalles`, `prefacturas`, `prefactura_detalles`,
`prefactura_abonos`, `facturas`, `factura_detalles`, `factura_pagos`,
`notas_credito`, `nota_credito_detalles`, `retenciones`, `retencion_detalles`,
`guias_remision`, `guia_remision_detalles`, `cuentas_cobrar`

**Servicios que CONSUME de Dev 2:**
- `AsientoService::facturaAutorizada()`
- `AsientoService::notaCreditoEmitida()`
- `AsientoService::anticipoCliente()`
- `AsientoService::cobro()`

---

### SEMANA 5-6: Módulo Taller (Altamira Fix)

**Tipos de equipo** `resources/js/Pages/Taller/TiposEquipo/` — CRUD simple

**Ingreso de equipos** `resources/js/Pages/Taller/Ingresos/`
- Formulario: datos cliente + datos equipo (marca, modelo, serie, color)
- Diagnóstico inicial + observaciones
- Upload foto del equipo (estado de llegada)
- Genera número de OT automático
- Asigna técnico disponible

**Órdenes de Trabajo** `resources/js/Pages/Taller/OrdenesTrabajo/`
- Lista con estados y filtros: técnico, estado, días en taller
- Vista kanban opcional: Pendiente | En Proceso | Listo | Entregado
- Badge de alerta si OT lleva >5 días en "En Proceso"

**Diagnóstico y Presupuesto** `resources/js/Pages/Taller/Diagnosticos/`
- Técnico llena: diagnóstico detallado, tiempo estimado
- Agrega repuestos desde inventario (bodega Taller)
  → Repuestos quedan en estado "Reservado" en Kárdex
- Costo mano de obra manual
- Botón "Enviar presupuesto al cliente" → genera PDF proforma de servicio
- Botón "Cliente aprobó" → repuestos pasan de Reservado a Usado en Kárdex

**Liquidación OT** `resources/js/Pages/Taller/Liquidacion/`
- Cambio estado a "Listo para Entregar"
- Genera factura electrónica automáticamente (reutiliza ciclo SRI de ventas)
- Si es garantía → factura $0, repuesto va a gasto 5.1.1.4 (Mermas)
- Notificación automática al vendedor: "Equipo listo para entregar"

**Tablas que escribe:**
`taller_tipos_equipo`, `taller_equipos`, `taller_ingresos`,
`taller_ordenes_trabajo`, `taller_ot_repuestos`, `taller_diagnosticos`

---

### SEMANA 6-7: Reportes Dev 1

- Reporte ventas: diario, semanal, mensual, trimestral, semestral, anual (PDF + gráfico)
- Venta por producto
- Costo y utilidad por producto
- Productos más vendidos
- Mayor margen de ganancia
- Ventas por vendedor + comisiones
- Kárdex por bodega
- Stock crítico/mínimo
- Saldos disponibles
- OT finalizadas
- Kárdex repuestos taller
- Garantías con precios
- Re-entrada de equipos

---

## DESARROLLADOR 2 — "STACK FINANCIERO"
### Módulos: Contabilidad · Compras/Importaciones · Bancos · RRHH · Reportes SRI

---

### SEMANA 1: Módulo Contabilidad (BASE — hacerlo PRIMERO)
*(Dev 1 necesita `plan_cuentas` para asignar cuentas a productos)*

**Plan de Cuentas** `resources/js/Pages/Contabilidad/PlanCuentas/`
- Árbol jerárquico expandible: Activos → Activo Corriente → Caja General
- Carga inicial del plan de cuentas completo de Altamira (seeder)
- Agregar/editar cuentas con validación de código (no duplicar)
- Toggle permite_asientos por cuenta
- Búsqueda por código o descripción
- Exportar a Excel

**Ejercicios Contables** `resources/js/Pages/Contabilidad/Ejercicios/`
- Tabla: año, mes, estado (abierto/cerrado), fecha apertura/cierre
- Botón "Abrir período" → crea ejercicio del mes
- Botón "Cerrar período" → solo Contador/SuperAdmin, con confirmación doble
  → Nadie puede crear asientos en ese mes una vez cerrado
- Cierre de año: mueve saldo 3.1.5.01 → 3.1.4.01, encierra clase 4 y 5

**Asientos Contables** `resources/js/Pages/Contabilidad/Asientos/`
- Lista: fecha, número, concepto, total debe/haber, tipo (auto/manual), estado
- Vista detalle: tabla partidas con debe/haber por cuenta
- Asiento manual (solo Contador/SuperAdmin):
  - Agregar filas cuenta + debe/haber
  - Validación en tiempo real: debe = haber (mostrar diferencia)
  - Alerta si el período está cerrado

**AsientoService** — CRÍTICO, construir junto con plan de cuentas:
```php
// app/Services/AsientoService.php
// Dev 1 llama estos métodos — nunca escribe directamente a asientos_contables

public function facturaAutorizada(Factura $factura): AsientoContable;
public function notaCreditoEmitida(NotaCredito $nc): AsientoContable;
public function anticipoCliente(PrefacturaAbono $abono): AsientoContable;
public function cobro(CuentaCobrar $cxc, array $pago): AsientoContable;
public function compraRegistrada(Compra $compra): AsientoContable;
public function pagoProveedor(CuentaPagar $cxp): AsientoContable;
public function movimientoBancario(MovimientoBancario $mov): AsientoContable;
public function nominaProcessada(Nomina $nomina): AsientoContable;
public function liquidacionDatafast(DatafastLiquidacion $liq): AsientoContable;
public function ajusteInventario(array $items): AsientoContable;
public function cierreAnual(int $empresaId, int $anio): void;
```
Cada método debe: verificar período abierto, generar asiento cuadrado,
registrar en `asientos_contables` + `asiento_detalles`, retornar el asiento.

**Centros de Costo** — ya en Fase 1, solo agregar vista de gestión

**Parámetros Contables** `resources/js/Pages/Contabilidad/Parametros/`
- Tabla configurable: evento → cuenta del plan de cuentas
- Cuentas a mapear (cargadas como seeder inicial):
  - Clientes locales → 1.1.3.1
  - Proveedores locales → 2.1.1.1
  - Anticipos clientes → 2.1.1.3
  - IVA ventas → 2.1.3.4
  - Vouchers/Datafast → 1.1.1.5
  - Comisiones bancarias → 5.3.1.02
  - Gastos no deducibles → 5.4.1.01

**Tablas que escribe:**
`plan_cuentas`, `ejercicios_contables`, `asientos_contables`, `asiento_detalles`,
`parametros_contables`

**Servicio que EXPONE:**
`AsientoService` (todos los métodos listados arriba)

---

### SEMANA 2: Módulo Compras e Importaciones

**Compras Locales** `resources/js/Pages/Compras/ComprasLocales/`
- Formulario por pestañas:
  - Datos generales: proveedor, tipo doc, número, fecha, vencimiento
  - Botón "Cargar XML": parsea XML SRI y autocompleta campos
  - Productos/Cuentas: agregar líneas (producto del inventario o cuenta contable)
  - Retención: % IR y % IVA aplicados al proveedor
  - Activos fijos: toggle por línea si es activo fijo
  - Formas de pago / cuotas
  - Checkbox "Gasto No Deducible" → bloquea IVA + dirige a cuenta 5.4.1.01
- Al guardar: llama `AsientoService::compraRegistrada()` + crea CxP
- Candado: si tiene pago registrado → botones Editar y Eliminar deshabilitados

**Devoluciones en Compras** `resources/js/Pages/Compras/Devoluciones/`
- Nota de crédito de proveedor
- Reversa del asiento original

**Importaciones COMEX** `resources/js/Pages/Compras/Importaciones/`

  *Crear importación:*
  - Nombre, proveedor (internacional), costo FOB, divisa, país embarque
  - Fechas: partida, llegada Ecuador, liquidación
  - Estado automático: En tránsito → En aduana → Liquidada
  - Número de invoice

  *Asociar compras a importación:*
  - Tipo documento: "Comprobante emitido en el Exterior"
  - Selector bodega (Bodega Importaciones)
  - Selector importación relacionada
  - Costos extra a ingresar como compras individuales:
    ISD, 15% IVA, Seguro transporte, Advalorem, Fodinfa,
    ICE, Flete marítimo, Gastos destino, Honorarios aduanero,
    Almacenaje, Honorarios banco, Transporte nacional

  *Liquidar importación:*
  - Lista facturas de gastos adicionales (arriba)
  - Lista mercadería importada (abajo)
  - Selector método prorrateo: Cantidad | Precio unitario | Peso
  - Botón "Calcular" → distribuye costos extra entre productos
  - Botón "Liquidar" → actualiza costo en Kárdex + estado=Liquidada
  - Botón "Revertir" si hay error

  *Anticipos a proveedores extranjeros:*
  - Registro de anticipo: proveedor + importación + monto + banco
  - Asiento: DEBE 1.1.3.3 / HABER 1.1.1.4
  - Al liquidar importación: cruce automático del anticipo

**Cuentas por Pagar** `resources/js/Pages/Compras/CxP/`
- Panel cronológico: ordenado de más antigua a más nueva
- Colores: verde(>7 días), naranja(1-7 días), rojo(vencida)
- Botón "Aprobar pago" (Admin/SuperAdmin con código de aprobación)
- Al aprobar → registra en módulo Bancos automáticamente
- Reporte antigüedad de saldos proveedores

**Tablas que escribe:**
`compras`, `compra_detalles`, `importaciones`, `anticipos_proveedores`,
`cuentas_pagar`

**Servicios que CONSUME de Dev 1:**
- `InventarioService::ingresarStock()` (al registrar compra con productos)

---

### SEMANA 3-4: Módulo Bancos y Cajas

**Catálogo Bancos y Cajas** `resources/js/Pages/Bancos/Catalogo/`
- CRUD: nombre, tipo (banco/caja/caja_chica/tarjeta), número cuenta
- Asociar cuenta del plan de cuentas
- Saldo inicial

**Movimientos Bancarios** `resources/js/Pages/Bancos/Movimientos/`
- Registrar Ingreso/Egreso:
  - Tipo: transferencia, cheque, efectivo, depósito
  - Banco/Caja origen
  - Persona: proveedor, cliente, empleado, otro (con buscador)
  - Número documento / número cheque
  - Descripción
  - Cuenta contrapartida (del plan de cuentas)
- Asiento automático: llama `AsientoService::movimientoBancario()`
- Lista principal con búsqueda y exportación PDF/XML
- Campos: # doc, # comprobante, # anticipo, persona, tipo transacción, centro costo

**Cheques** `resources/js/Pages/Bancos/Cheques/`
- Registro de cheques emitidos y recibidos
- Estados: emitido, cobrado, protestado, anulado
- Cheques posfechados con alerta de vencimiento

**Cajas Chicas / Cierres diarios** `resources/js/Pages/Bancos/Cajas/`
- Apertura de caja: usuario + centro costo + monto inicial
- Durante el día: facturas en efectivo se registran automáticamente
- Cierre: conciliación automática con facturas del día
- Si hay diferencia → advertencia antes de cerrar
- Reporte diario, semanal, mensual de caja

**Tarjetas / Datafast** `resources/js/Pages/Bancos/Datafast/`

  *Lotes (cierre datáfono):*
  - Número de lote + total
  - Al crear: asiento DEBE 1.1.1.5 (Vouchers) / HABER Ventas

  *Liquidaciones:*
  - Asociar lote + valor depositado por banco
  - Sistema calcula: comisión Datafast, retención IVA, retención IR
  - Al aprobar: asiento automático completo (ver spec CxC-03)
  - Alerta si lote sin liquidar >72 horas

**Conciliación Bancaria** `resources/js/Pages/Bancos/Conciliacion/`
- Upload estado de cuenta Excel (.xlsx o .csv) del banco
- Sistema hace match automático por fecha y monto
- Panel "Partidas en Tránsito": registros que no cruzaron
- Contador puede asignar manualmente o crear asiento faltante desde ahí
- Historial de conciliaciones por banco y período

**Estado de Cuenta Bancario** `resources/js/Pages/Bancos/EstadoCuenta/`
- Vista consolidada por banco: saldo inicial + movimientos + saldo final
- Filtro por período

**Tablas que escribe:**
`bancos_cajas`, `movimientos_bancarios`, `cheques`, `cierres_caja`,
`datafast_lotes`, `datafast_liquidaciones`, `conciliaciones_bancarias`,
`partidas_transito`

---

### SEMANA 4-5: Módulo RRHH

**Colaboradores** `resources/js/Pages/RRHH/Colaboradores/`
- Formulario completo por pestañas:
  - Identificación: cédula, nombres, apellidos, fecha nac, sexo
  - Contacto: email, teléfono, dirección
  - Cargo: puesto, departamento, fecha ingreso, tipo contrato
  - Remuneración: sueldo base, tipo décimos (acumula/mensualiza), fondos reserva
  - Datos bancarios: banco, tipo cuenta, número cuenta
  - Sistema: usuario vinculado, estado activo/inactivo
- Al crear: genera automáticamente filas de nómina quincenal y mensual

**Puestos de Trabajo** `resources/js/Pages/RRHH/Puestos/` — CRUD simple
**Horarios** `resources/js/Pages/RRHH/Horarios/` — CRUD con días de la semana

**Asistencia — Timbre Digital** `resources/js/Pages/RRHH/Asistencia/`
- Pantalla de acceso rápido (widget en dashboard para colaboradores)
- Reloj digital en grande con hora del SERVIDOR (nunca del cliente)
- Botón "Registrar Entrada" / "Registrar Salida" (cambia de estado)
- Candado antitrampas: timestamp del servidor, ignorar hora del browser
- Si entrada > hora oficial + tolerancia → calcula minutos de atraso
- Si salida > hora oficial → encola para aprobación de horas extras

**Aprobación de Horas Extras** `resources/js/Pages/RRHH/HorasExtras/`
- Panel Admin: lista de horas pendientes de aprobación por colaborador/día
- Admin edita cuántas horas fueron realmente productivas
- Cálculo:
  - Suplementarias (días laborables, antes 24:00): VH × 1.5
    (límite: 4h/día, 12h/semana — bloquear con alerta)
  - Extraordinarias (fines de semana, feriados, madrugada): VH × 2.0
  - Donde VH = Sueldo Base / 240

**Roles de Pago** `resources/js/Pages/RRHH/Nomina/`
- Selector período: año + mes + tipo (mensual/quincenal)
- Tabla de empleados con columnas:
  Empleado | Ingresos | Otros Ingresos | Egresos | Otros Egresos | Días Lab | Total | Tipo Pago
- Cálculo automático al abrir período:
  - Ingresos: sueldo + comisiones + horas extras aprobadas
  - Egresos: IESS 9.45% + anticipos/quincenas + préstamos + atrasos + descuentos
  - Fórmula atrasos: Minutos × (Sueldo / 14400)
  - Neto = Ingresos − Egresos
- Estados: Borrador → Procesado → Pagado
- Edición manual (solo Contador/SuperAdmin) → badge [Modificado Manualmente]
- Botón PDF por empleado: formato A4, logo Altamira, desglose, líneas de firma
- Botón exportar todos los roles del mes → .zip con PDFs individuales
- Al procesar: `AsientoService::nominaProcessada()`

**Préstamos y Anticipos** `resources/js/Pages/RRHH/Prestamos/`
- Registro de adelanto/préstamo + cuotas de descuento
- Descuento automático en el rol de pagos siguiente
- `AsientoService` registra el egreso del anticipo

**Liquidaciones / Finiquitos** `resources/js/Pages/RRHH/Liquidaciones/`
- Motivo: renuncia, despido, fin de contrato
- Cálculo proporcional automático: décimos + vacaciones + fondos − anticipos
- Al aprobar (solo SuperAdmin): colaborador pasa a Inactivo + bloquea acceso

**Alertas RRHH (Jobs programados):**
- Día 28 de cada mes, 9:00 AM → recordatorio cierre nómina al Admin/Contador
- Si colaborador acumula >3 atrasos en la semana → alerta al SuperAdmin

**Tablas que escribe:**
`colaboradores`, `puestos_trabajo`, `horarios`, `asistencias`,
`horas_extras_aprobacion`, `nominas`, `nomina_detalles`, `rubros_nomina`,
`prestamos_empleados`, `liquidaciones`

---

### SEMANA 5-6: Sistema de Alertas y Notificaciones

**NotificacionService** (ampliar el de Fase 1):
```php
// app/Services/NotificacionService.php
public function stockCritico(Producto $p): void;
public function importacionLiquidada(Importacion $imp): void;
public function proformaProxVencer(Proforma $p): void;       // 24h antes
public function facturaVencida(CuentaCobrar $cxc): void;     // día 0, +15, +30
public function obligacionProxVencer(CuentaPagar $cxp): void; // 48h antes
public function vouchersNoLiquidados(DatafastLote $lote): void; // +72h
public function otListaEntregar(TallerOrdenTrabajo $ot): void;
public function otTiempoVencido(TallerOrdenTrabajo $ot): void; // +5 días
public function atrasosRecurrentes(Colaborador $c): void;    // >3 en semana
public function recordatorioCierreNomina(): void;            // día 28, 9:00 AM
```

**Jobs programados** `app/Console/Commands/` + `routes/console.php`:
```php
// Cada hora:
Schedule::job(new VerificarStockCriticoJob)->hourly();
Schedule::job(new VerificarVouchersDatafastJob)->hourly();

// Cada día a las 8:00 AM:
Schedule::job(new VerificarFacturasVencidasJob)->dailyAt('08:00');
Schedule::job(new VerificarObligacionesProximasJob)->dailyAt('08:00');
Schedule::job(new VerificarOtTiempoVencidoJob)->dailyAt('08:00');
Schedule::job(new VerificarProformasProximasJob)->dailyAt('08:00');
Schedule::job(new VerificarAtrasosRecurrentesJob)->dailyAt('07:00');

// Día 28 de cada mes:
Schedule::job(new RecordatorioCierreNominaJob)->monthlyOn(28, '09:00');
```

---

### SEMANA 6-7: Reportes Dev 2 + Formularios SRI

**Reportes financieros:**
- Estado de Situación Financiera (Balance General)
- Estado de Resultados (PyG)
- Flujo de Caja: mensual + comparativo
- Balance de Comprobación
- Libro Diario / Mayorizaciones
- Conciliación bancaria semanal y mensual

**Reportes SRI:**
- ATS (Anexo Transaccional Simplificado): XML mensual
- Formulario 103 (Retenciones en la fuente): PDF
- Formulario 104 (IVA): PDF
- Anexo ICE: compras y ventas

---

## SERVICIOS COMPARTIDOS — CONTRATO DE INTERFAZ

Estos servicios son el punto de contacto entre los dos devs.
Definir las interfaces ANTES de empezar la semana 1 para evitar bloqueos.

```
Dev 1 LLAMA         →    Dev 2 PROVEE
─────────────────────────────────────
AsientoService::facturaAutorizada()
AsientoService::notaCreditoEmitida()
AsientoService::anticipoCliente()
AsientoService::cobro()
AsientoService::ajusteInventario()

Dev 2 LLAMA         →    Dev 1 PROVEE
─────────────────────────────────────
InventarioService::ingresarStock()
InventarioService::egresarStock()
InventarioService::reservarStock()
InventarioService::liberarReserva()
InventarioService::getSaldoDisponible()
SecuencialService::siguiente()    ← Dev 1 lo construye (semana 3)
```

---

## ESTRATEGIA GIT — SIN CONFLICTOS

```
main
  └── develop
        ├── feature/dev1-personas-clientes
        ├── feature/dev1-inventario-productos
        ├── feature/dev1-ventas-facturas
        ├── feature/dev1-taller
        ├── feature/dev1-reportes-ventas
        ├── feature/dev2-contabilidad-base      ← merge a develop PRIMERO
        ├── feature/dev2-compras-importaciones
        ├── feature/dev2-bancos
        ├── feature/dev2-rrhh
        └── feature/dev2-reportes-sri
```

**Reglas:**
1. Dev 2 hace merge de `contabilidad-base` a `develop` antes del fin de la semana 1
   para que Dev 1 pueda usar `plan_cuentas` y `AsientoService`
2. Dev 1 hace merge de `personas-clientes` a `develop` antes del fin de semana 1
   para que Dev 2 pueda usar `clientes` y `proveedores` en compras/bancos
3. Cada dev hace merge de su feature a `develop` al terminar cada módulo
4. Nunca trabajar directamente en `develop` ni `main`
5. Pull request con revisión del otro dev antes de merge

---

## TABLA RESUMEN DE SEMANAS

| Semana | Dev 1 (Comercial)              | Dev 2 (Financiero)              |
|--------|-------------------------------|----------------------------------|
| 1      | Clientes + Proveedores ⚡     | Plan Cuentas + AsientoService ⚡  |
| 2      | Inventario (productos/bodegas)| Compras Locales                  |
| 3      | Inventario (Kárdex/traslados) | Importaciones COMEX              |
| 4      | Ventas (Proformas/Prefacturas)| Bancos (movimientos/cajas)       |
| 5      | Ventas (Facturas SRI/NC/Ret.) | Bancos (Datafast/Conciliación)   |
| 6      | Taller (Fix)                  | RRHH (Colaboradores/Asistencia)  |
| 7      | Reportes Ventas/Inv/Taller    | RRHH (Nómina/Liquidaciones)      |
| 8      | Buffer/ajustes/pruebas        | Reportes SRI + Alertas/Jobs      |

⚡ = Merge obligatorio a develop al terminar para desbloquear al otro dev

---

## ARCHIVOS QUE NUNCA DEBEN TOCAR LOS DOS AL MISMO TIEMPO

| Archivo/Tabla                | Dueño  | El otro solo...          |
|-----------------------------|--------|--------------------------|
| `asientos_contables`        | Dev 2  | llama AsientoService     |
| `asiento_detalles`          | Dev 2  | solo lectura para reportes|
| `plan_cuentas`              | Dev 2  | lectura para selector    |
| `ejercicios_contables`      | Dev 2  | lectura para validar      |
| `inventario_saldos`         | Dev 1  | llama InventarioService  |
| `inventario_movimientos`    | Dev 1  | llama InventarioService  |
| `producto_series`           | Dev 1  | lectura                  |
| `secuenciales`              | Dev 1  | llama SecuencialService  |
| `facturas`                  | Dev 1  | lectura (Dev 2 para SRI) |
| `nominas`                   | Dev 2  | no tocar                 |
| `colaboradores`             | Dev 2  | lectura (Dev 1 en taller)|
