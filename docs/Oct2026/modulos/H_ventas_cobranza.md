# Módulo H — Ventas y cobranza

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-04_Ventas_y_Cobranza.pdf`, `01` §5.1. Decisiones: DEC-001, 025, 028, 029, 033, 035, 039, 040, 042, 050, 052, 053.

**Prompt de arranque:**
> Implementa el módulo "Ventas y Cobranza" de Altamira ERP según `docs/Oct2026/02-04_Ventas_y_Cobranza.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Antes de empezar, confirma que Parámetros (B), Bancos (C), Caja (D), Tarjetas (E) e Inventario (F) estén hechos. Empieza reproduciendo el error de la transferencia.

**Tareas**
1. **Error de transferencia (prioritario).** `SQLSTATE[23502]`: `dias_credito` es NOT NULL y el sistema envía vacío. Corrección: dejarlo opcional o guardar 0 si no es crédito. `banco` obligatorio en transferencia (y depósito o tarjeta según DEC-040, pendiente). `num_cheque` solo para cheque; el comprobante de transferencia va en un campo de referencia aparte. Validar en pantalla con mensajes como "Seleccione el banco de la transferencia", nunca el error técnico. Los cambios de esquema en `factura_pagos` deben ser idempotentes.
2. **Formas de pago.** Efectivo, transferencia, tarjeta, cheque y crédito. En lugar de "transferencia" genérica se elige la cuenta bancaria (Pichincha, ProduBanco o Guayaquil), tomada de los bancos creados en el módulo de Bancos.
3. **Toda venta nace en CxC** (PC-VEN-01 / 02). El cobro cancela la CxC según la forma de pago (PC-VEN-06 a 09). Venta a crédito: sin movimiento en Bancos ni Caja.
4. **Cobro con tarjeta:** baja la factura y deja el valor por liquidar; registra procesador, tipo, lote y autorización (PC-VEN-08).
5. **Anticipos de clientes** (PC-VEN-10). Su aplicación a la factura está pendiente (DEC-028); no implementar ese asiento.
6. **Retenciones recibidas** en el cobro (PC-VEN-12). **Cheques post fechados** en cartera (PC-VEN-13) y su depósito (PC-VEN-14).
7. **Prefacturas.** Usar la lista de bancos del módulo de Bancos. **Error reportado:** una prefactura pagada por transferencia no afectó Bancos; la regla es que prefactura y nota de venta no generan movimiento y solo lo hace el pago registrado (revisar el reporte contra la regla antes de "corregir").
8. **Descuentos** en todos los productos, dentro de los límites por rol del módulo A. Regalos: máximo 2 por factura.
9. **Guías de remisión.** Explicar y habilitar la impresión y la vista de: clave de acceso de 49 dígitos y QR autorizado por el SRI (DEC-039 pendiente), número de comprobante, datos del transportista y placa, fechas de traslado, factura sustento y detalle físico de productos.
10. **Rótulos de caja** automáticos con los datos de la factura, como en el sistema anterior, y **editables**.
11. **Facturas de evento** (PC-VEN-16, cuenta de otros ingresos 4.03.02) y facturas de taller automáticas (ver módulo K).
12. **Devolución en ventas** (PC-VEN-05): incompleta y pendiente (DEC-029). No inventar contrapartida, IVA ni reverso del costo.
13. **Anulación.** Anular o eliminar una factura debe revertir su movimiento bancario o de caja (`CobroBancoService`).

**Pruebas:** VEN-01 (transferencia con banco → asiento Debe banco / Haber clientes), CXC-01 y CXC-02, más BAN-04 a BAN-08 y CCO-01 a CCO-04 del módulo C.

**No hacer:** liquidar tarjetas, conciliar bancos ni cerrar caja.

---

## Reglas comunes (aplican a este chat)

1. **Fuente de verdad.** Las cuentas contables viven solo en `01_Parametros_Contables`. Los módulos citan el ID (por ejemplo `PC-VEN-06`) y no copian códigos. Si falta una cuenta, se agrega al parámetro; no se escribe en el código a mano.
2. **Plan de cuentas.** El plan vigente según estos documentos es el **V4** (`Plan_de_cuentas_Final.xlsx`). El `CLAUDE.md` describe una migración previa al "Plan 2025" (`2026_10_05_100000_reemplazar_plan_cuentas_2025`), con formato `1.01.01.01`. Antes de tocar cuentas, el chat debe comprobar en la BD cuál plan está cargado y **avisar al usuario de cualquier diferencia**. No asumir.
3. **Base de datos.**
   - Solo la BD `altamira`. En producción el Postgres es compartido: no tocar `lideres` ni `sae_alt`.
   - **No modificar el esquema ni los datos de la tabla `productos`** (datos migrados del sistema legacy). Si una subcategoría necesita guardar sus cuentas, usar una tabla aparte y relacionarla, nunca columnas nuevas en `productos`.
   - Migraciones idempotentes: `Schema::hasTable()` / `Schema::hasColumn()` antes de crear o agregar. Seeders con `firstOrCreate()`.
   - Respetar los nombres reales de columnas de la tabla del `CLAUDE.md` y `public $timestamps = false;` en las tablas que no tienen timestamps.
4. **Contabilidad lista antes de operar (20/09/2026).** Las operaciones que generan asientos siguen bloqueándose si no hay período abierto o falta un parámetro (`AsientoService::validarConfiguracion()`). No revertir eso. Si se agregan eventos nuevos, hay que agregarlos a la validación.
5. **Asientos.** Siempre débito = crédito, montos con dos decimales, no escribir en cuentas padre (`Permite_Asientos = NO`), centro de costo obligatorio en cuentas de los grupos 4, 5 y 6.
6. **Exportaciones.** PDF y Excel se generan de forma síncrona. Solo el ZIP de nómina usa cola (`ExportarNominaZipJob`). No tocar eso.
7. **UI.** Inertia v2 + React 19 + TypeScript sin `any`. Colores con variables CSS, no clases fijas de Tailwind. Botones PDF/Excel al final de los filtros, unidos como `btn-group`. Rutas con Ziggy (`Tighten\Ziggy`).
8. **Pruebas.** Todas las pruebas de aceptación se ejecutan con una empresa o período de prueba, nunca con datos reales.
9. **Registro.** Al terminar, el chat resume: qué cambió, qué quedó pendiente y qué decisiones (DEC-xxx) bloquearon algo. No marcar nada como implementado sin haberlo probado.
10. **Decisiones abiertas.** Si una tarea depende de un DEC pendiente, **no inventar la regla**: implementar solo lo definido y dejar la parte pendiente señalada, o preguntar al usuario.
11. **Commits.** No hacer commit ni push sin que el usuario lo pida.
12. **Lectura de PDFs.** Los PDF de los módulos tienen 3 a 6 páginas y se leen completos con la herramienta Read. El 01 tiene 19 páginas y también se lee completo (sin el parámetro `pages`, porque falta `pdftoppm`).

---

## Lista de verificación al cerrar este chat

- [ ] Se leyó `CLAUDE.md` y el PDF del módulo.
- [ ] Migraciones idempotentes y probadas dos veces.
- [ ] Ninguna cuenta contable escrita a mano en código: todas salen de parámetros.
- [ ] Débito = crédito en todo asiento nuevo.
- [ ] Pruebas de aceptación del módulo ejecutadas y resultado anotado.
- [ ] `npm run build` sin errores de TypeScript.
- [ ] Lista de decisiones (DEC) que quedaron pendientes y por qué.
- [ ] Nada se marcó como "implementado" sin probarse.
- [ ] Sin commit ni push no pedidos.
