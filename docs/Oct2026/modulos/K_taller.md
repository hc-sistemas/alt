# Módulo K — Taller (Altamira FIX)

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-10_Taller.pdf`, `01` §5.13. Decisiones: DEC-032, 047, 048, 049, 051 a 054.

**Prompt de arranque:**
> Implementa los cambios del módulo "Taller" de Altamira ERP según `docs/Oct2026/02-10_Taller.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Inventario (F) y Ventas (H) deben estar hechos. Empieza describiendo el flujo actual Ingreso → Orden de trabajo → Diagnóstico → Repuestos.

**Tareas**
1. **Repuestos desde el inventario (prioritario).** La búsqueda por código o nombre debe jalar los productos de la categoría "Repuestos e Insumos de Taller" con su stock. Bloquear si la cantidad supera el stock. Cada repuesto genera su salida en el kárdex y queda para la factura.
2. **Estados.** Una sola lista de estados. Hoy la misma orden aparece distinta en Ingresos y en Órdenes (ej. OT-2026-000009 "En proceso" vs "Garantía"). La lista oficial está pendiente (DEC-051): proponer una al usuario y esperar confirmación.
3. **Turnos.** La columna "Turno" debe mostrar el número asignado por orden de llegada (hoy vacía). Órdenes "Esperando repuesto" quedan en pausa y no bloquean la fila. Indicadores: en cola, en proceso y esperando repuesto.
4. **Documento de ingreso** imprimible y reimprimible desde la lista, en una página A4/PDF: logo, "Altamira FIX — Servicio técnico", RUC de UIO, número de OT, datos del cliente y del equipo, problema reportado, componentes revisados, hasta 4 fotos, técnico, condiciones del servicio (texto configurable: DEC-054) y firmas.
5. **Ingreso.** Campos obligatorios: tipo, marca, modelo y diagnóstico inicial. Catálogo editable de Tipos de Equipo (menú Taller > Tipos de Equipo), con alta desde el mismo formulario. Foto del equipo.
6. **Diagnósticos.** Varios por orden, con botón "Aprobar". Quién aprueba y qué pasa: DEC-049 pendiente.
7. **Factura automática.** Desde la orden se genera la factura con dos tipos de línea: servicio técnico (PC-TAL-01) y repuestos (PC-TAL-02). Se emite con el RUC de UIO y el centro de costo FIX, y sigue las reglas de cobro del módulo H. El valor del servicio (DEC-052) y el momento de generarla (DEC-053) están pendientes: implementar el mecanismo y dejar esas dos reglas configurables o señaladas.
8. **Garantías.** Una orden en "Garantía" no se factura al cliente. La cuenta y la recuperación con el proveedor están pendientes (DEC-047). Poder ver el costo de garantías por producto.
9. **Componentes comprados afuera:** suman al costo final de la orden; si se compran más de 3, entran a inventario (DEC-032 pendiente: falta definir si son unidades o tipos, y la cuenta).
10. **Técnico externo:** su trabajo se registra como compra cuando factura (PC-TAL-03, DEC-048 pendiente).
11. **Datos de prueba.** Las órdenes de prueba actuales (todas del mismo cliente) se borran antes del arranque, solo con confirmación del usuario.

**Pruebas:** TAL-01 a TAL-08.

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
