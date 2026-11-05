# Módulo F — Inventario

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-02_Inventario.pdf`, `01` §5.7 y §6. Decisiones: DEC-029, 030, 031, 033, 034.

**Prompt de arranque:**
> Implementa el módulo "Inventario" de Altamira ERP según `docs/Oct2026/02-02_Inventario.pdf` y la sección 6 de `docs/Oct2026/01_Parametros_Contables.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. **No modifiques el esquema ni los datos de la tabla `productos`.** Antes de editar, dime cómo se relacionan hoy productos, categorías y cuentas contables.

**Tareas**
1. **Categorías y subcategorías.** Implementar las 20 de PC-CAT-01 a PC-CAT-20. Cada categoría que se vende tiene tres cuentas (inventario, venta, costo) y ninguna apunta a una cuenta padre. Con subcategorías, el producto elige una y esta define las tres cuentas; sin subcategorías, hereda de la categoría sin preguntar. **"Accesorios" deja de ser categoría**: sus productos se reparten en la correspondiente (esto exige una reclasificación de datos: proponer el procedimiento y la tabla de mapeo sin tocar `productos`).
2. **Cuentas sin clasificar.** `1.01.04.09`, `4.01.09` y `5.01.08` solo para Computadoras y PISOS LED. Todo producto debe tener categoría; sin categoría no se guarda (DEC-030).
3. **Regalos.** Salen del kárdex; máximo 2 por factura (más de 2 requiere aprobación: DEC-033 pendiente). Sin precio ni cuenta de venta. Asiento al entregar: Debe 6.01.15 / Haber 1.01.04.08.01 o .02 según la subcategoría (PC-VEN-15).
4. **Kárdex.** Cada entrada y salida genera su movimiento. El costo de venta se lee del kárdex (`AsientoService::costoSalidaDocumento()`).
5. **Números de serie.** Obligatorios para DJ/controladores, computadoras, pantallas LED y TV. Al agregarlos a una factura de compra el sistema avisa para registrar el SN de cada unidad. En una inspección de bodega debe saberse qué unidades con SN se vendieron y cuáles siguen en bodega.
6. **Bodegas.** Crear las mismas en UIO e Import, incluida la de importaciones. **Error reportado:** 100 unidades de `SON.000232` de una importación pendiente no aparecen en la bodega de importaciones en el inventario consolidado.
7. **Faltantes y mermas** a 5.01.09, no a costo de venta (incluye el ajuste del inventario físico al 31/12).
8. **Listado de productos:** poder alargar/achicar filas o columnas para ver el nombre completo. Permitir márgenes de ganancia superiores al 100% (hoy el validador rechaza 100; DEC-034).
9. **Importaciones:** entran a 1.01.04.07 y al liquidar pasan al inventario de su categoría.
10. **Excel de inventario:** el inventario se entrega y se carga con la misma clasificación.

**Pruebas:** el documento no trae pruebas propias; IMP-02 cubre la liquidación. Proponer pruebas de kárdex, regalos, SN y bodegas.

**No hacer:** compras e importaciones (G), facturación (H), reglas de Taller (K).

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
