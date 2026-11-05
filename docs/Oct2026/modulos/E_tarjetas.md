# Módulo E — Tarjetas

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-07_Tarjetas.pdf`, `01` §5.2. Decisiones: DEC-021 a 026, 040.

**Prompt de arranque:**
> Implementa el módulo "Tarjetas" de Altamira ERP según `docs/Oct2026/02-07_Tarjetas.pdf`. Reemplaza al módulo actual "Datafast". Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Bancos (C) debe existir. Muéstrame primero cómo está hoy el módulo Datafast.

**Tareas**
1. **Renombrar** "Datafast" a "Tarjetas" (menú, rutas, títulos). Cuidado con la ruta y permisos existentes: no romper el acceso de los perfiles.
2. **Flujo.** Cobro con tarjeta en CxC: la factura se da de baja y el valor queda "por liquidar" (PC-VEN-08: Debe 1.01.03.04 / Haber 1.01.03.01, por el bruto). Se registra procesador, tipo de tarjeta, lote (si se conoce) y autorización.
3. **Liquidación del lote** (solo contador o administrador): selección de las ventas que el procesador incluyó, suma del bruto contra el reporte, ingreso de comisión, IVA de la comisión y retenciones. Si no vienen, se calculan con el porcentaje configurado, pero **el valor del reporte siempre prevalece**. Asiento PC-TAR-01. Si no cuadra, el lote no se guarda. Diferencias de centavos o faltantes inexplicados van a 6.01.14 (PC-TAR-02), marcadas para revisión, nunca ajustadas en silencio.
4. **Conciliación** del neto del lote contra el banco (pestaña Tarjetas de Bancos).
5. **Configuración:** procesadores, tipo de tarjeta (crédito, débito, diferido), banco de destino, % de comisión y de retención por defecto (solo prellenan) y plazo de liquidación (alertas de lotes atrasados).
6. **Casos especiales:** anulación de venta con tarjeta (antes de liquidar se anula la pendiente con reverso; ya liquidada requiere nota de crédito) y tarjeta diferida (se liquida por lote; intereses a 6.01.10, PC-TAR-04). **Contracargo o devolución del procesador: pendiente (DEC-025); no implementar la contabilización.**
7. **Reportes:** tarjetas pendientes por procesador, comisiones por período y retenciones por período.

**Pruebas:** TAR-01 y CCO-04 (factura con tarjeta "por liquidar" no mueve el banco). Faltan pruebas de contracargo, diferido y diferencias.

**No hacer:** calcular impuestos de la venta (Ventas), registrar el pago de comisiones del propio negocio (Compras) ni decidir si el reporte del procesador sustituye al comprobante de retención (DEC-023).

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
