# Módulo I — Contabilidad

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-08_Contabilidad.pdf`, `01` completo. Decisiones: DEC-002, 004, 007, 008, 009, 010, 011, 031, 037, 045.

**Prompt de arranque:**
> Implementa el módulo "Contabilidad" de Altamira ERP según `docs/Oct2026/02-08_Contabilidad.pdf`. Lee `CLAUDE.md` (sección "Auditoría y corrección del módulo de Contabilidad", que **no debe revertirse**) y las "Reglas comunes" de este mismo archivo. Este módulo va casi al final porque los cierres dependen de los demás. Primero compara lo que ya existe con cada punto del PDF y dame la lista de brechas.

**Tareas**
1. **Asientos.** Número correlativo en su período, período tomado de la fecha, rechazo de períodos cerrados, débito o crédito (no ambos), mínimo dos líneas y cuadre, concepto y documento de respaldo obligatorios, solo cuentas que permiten asientos, advertencia de naturaleza invertida con confirmación, centro de costo en cuentas 4/5/6, usuario y fecha de registro, **anulación con reverso y motivo (nunca borrar)** y búsqueda por número, fecha, concepto, cuenta y monto. Los asientos de módulos (facturas, etc.) no se editan, solo se anulan.
2. **Diarios:** general, caja, bancos, ventas, compras e importaciones.
3. **Botones de origen** (clientes, proveedores, cierre de caja, depósitos masivos, subir depósitos, distribución de costos): no crean asientos propios; abren el proceso de su módulo.
4. **Plan de cuentas:** carga desde Excel; creación de las 5 cuentas nuevas y ajuste de 1.01.02.02 (se coordina con el módulo B).
5. **Saldos iniciales:** asiento de apertura para cada banco y la caja; sin digitar a mano. Contrapartida pendiente (DEC-011).
6. **Retenciones:** catálogo de códigos SRI con porcentaje y cuenta (2.01.02.02 IR / 2.01.02.03 IVA), retenciones emitidas y recibidas por período. Catálogo pendiente de confirmar por el contador (DEC-045).
7. **Informes (9):** lista de diarios, mayor general, balances (comprobación, general y de resultados) **por RUC y por centro de costo**, retenciones, conciliación, transacciones bancarias, análisis vertical y horizontal, talón resumen del anexo (contenido pendiente: DEC-008) e impresión de documentos. Todos filtrables por RUC (UIO, Import) y por centro de costo (Matriz/UIO, FIX), y cuadrados con el mayor.
8. **Cierre mensual** en este orden (el cierre se bloquea si algo falta): cajas de todos los días cerradas, bancos conciliados, tarjetas liquidadas o justificadas, cheques, CxC y CxP cuadradas con antigüedad, inventario cuadrado con kárdex, importaciones liquidadas, depreciación, nómina, IVA (PC-TRI-01), cuentas sin clasificar revisadas (no se exige saldo cero: DEC-031), anticipos conciliados, balances y cierre del período. Reglas de períodos: no cerrar antes del último día sin confirmación y motivo; bloquear con asiento descuadrado, banco sin conciliar o caja abierta; registrar usuario y fecha; reabrir solo con motivo y queda en auditoría; avisar si se abre un período con el anterior abierto.
9. **Cierre anual:** inventario físico al 31/12, depreciación, provisiones, renta (pendiente: DEC-004), cierre de resultados a 3.01.03.01 (PC-CIE-01), traslado a 3.01.02.01 o .02 (PC-CIE-02), retiros del propietario (pendiente: DEC-010), asiento de apertura del año siguiente y ejercicio no reabrible salvo autorización escrita del dueño.
10. **Respetar lo ya hecho** (CLAUDE.md): `resultadoAcumulado()` incluye `CIERRE_ANUAL`, la numeración `AS-{año}-` y los agregados con un solo `GROUP BY`.

**Pruebas:** AS-01 a AS-12, PER-01 a PER-07, CIE-01 a CIE-03, INF-01 e INF-02.

**No hacer:** definir cuentas por evento (módulo B), conciliar (C), liquidar tarjetas (E).

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
