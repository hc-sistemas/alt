# Módulo D — Caja

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-05_Caja.pdf`, `01` §5.4 y PC-GEN-05. Decisiones: DEC-005, 011, 037, 038, 041.

**Prompt de arranque:**
> Implementa el módulo "Caja" de Altamira ERP según `docs/Oct2026/02-05_Caja.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Bancos (módulo C) debe estar hecho porque los depósitos van al banco. Antes de editar, describe cómo funciona hoy la apertura y el cierre de caja.

**Tareas**
1. **Alcance.** Solo efectivo. Transferencias, tarjetas y cheques no se cuentan ni se muestran. Un movimiento de caja no admite otra forma de pago.
2. **Encabezado.** La caja de UIO no debe decir "Altamira Import".
3. **Apertura.** Tabla de denominaciones × cantidad (monedas 0,01 / 0,05 / 0,10 / 0,25 / 0,50 / 1,00 y billetes 1 / 2 / 5 / 10 / 20 / 50 / 100) con totales de monedas, billetes y efectivo físico. **Solo el primer día.** Desde el segundo día el saldo inicial es el saldo final del anterior, automático. El conteo inicial queda como saldo inicial contable (contrapartida pendiente: DEC-011).
4. **Movimientos del día.** Se jalan de los módulos de origen (cobros en efectivo, pagos a proveedores, gastos, anticipos), no se digitan dos veces. Cada uno genera su asiento (PC-VEN-06, PC-VEN-10, PC-CAJ-03 a 08, PC-CAJ-10, PC-BAN-11). El movimiento manual pide concepto y monto; el depósito pide banco de destino. No se permite saldo final negativo (DEC-041 pendiente: bloquear y avisar, y señalarlo al usuario).
5. **Cierre.** Muestra, para el día, el número de facturas cobradas en efectivo, el total facturado en efectivo y el total cobrado en efectivo. **Error reportado:** hoy no arrastra esa información; revisar `CobroBancoService::ventasEsperadas` y la precarga de montos. Conteo físico con la misma tabla. Diferencia = conteo − saldo esperado; si no es cero, observación obligatoria y asiento automático (PC-CAJ-12 sobrante, PC-CAJ-13 faltante, ambos a 6.01.14 según DEC-005). Un día cerrado no se modifica: se corrige con un movimiento de ajuste en el día siguiente.
6. **Cuentas.** La caja es siempre 1.01.01.01. Ninguna línea apunta a cuentas padre ni a 4.01.09 / 5.01.08. Gastos operativos piden la subcuenta 6.01.xx; 6.01.12 no se usa por defecto.
7. **Segunda caja.** Solo si se define: 1.01.01.02 (PC-CAJ-15). Pendiente caja de Import (DEC-038).

**Pruebas:** CAJ-01 (cierre con faltante) y CAJ-02 (cierre con sobrante). El documento reconoce que faltan pruebas de apertura, arrastre de saldo y saldo negativo: proponerlas.

**No hacer:** conciliación bancaria ni vouchers (módulos C y E).

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
