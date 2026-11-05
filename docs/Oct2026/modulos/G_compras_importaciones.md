# Módulo G — Compras e importaciones

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-03_Compras_e_Importaciones.pdf`, `01` §5.5, §5.6 y §10. Decisiones: DEC-012, 030, 043, 045, 046.

**Prompt de arranque:**
> Implementa el módulo "Compras e Importaciones" de Altamira ERP según `docs/Oct2026/02-03_Compras_e_Importaciones.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Parámetros (B) e Inventario (F) deben estar hechos. Recuerda que desde el 20/09/2026 las compras se bloquean si la contabilidad no está lista: no lo reviertas.

**Tareas**
1. **Importación: validar el FOB.** No dejar continuar hasta que el valor FOB cuadre con la suma de los productos (hoy se queda con el FOB inicial).
2. **Tabla de costos extra** con columnas Base, IVA y Total, y fila de impuestos. Conceptos y dónde se digita:

   | Concepto | Se digita en | Cálculo |
   |---|---|---|
   | Mercadería, seguro internacional, flete marítimo | Total | Sin IVA |
   | Gastos destino, honorarios aduaneros, almacenaje, transporte nacional | Total con IVA | Base = Total ÷ 1,15 (redondeo a 2 decimales); IVA = Total − Base |
   | Advalorem, FODINFA, IVA importación, ICE, ISD | Fila impuestos | Sin cálculo |
   | Honorarios banco | Base = Total | Sin IVA |

   Ejemplo oficial: total 8.950,00; costo a inventario 8.172,17; IVA crédito tributario 777,83 (cuadre en `01` §10). Usar este ejemplo como caso de prueba.
3. **Asientos** por fila (PC-IMP-01 a 06) y validar débito = crédito antes de guardar la importación. Es el plan V4, no el "plan v3" que dice el V3 (DEC-046).
4. **Liquidación (prorrateo).** Prorratear por el valor de cada mercadería, mostrar el costo final por categoría y dejar 1.01.04.07 en cero.
5. **Facturas de compra.** Corregir que todos los gastos de importación aparezcan cobrados por el proveedor del exterior; cada gasto va con su proveedor y asiento.
6. **Compras nacionales:** buscador de proveedor, filas expandibles en el detalle, verificar el IVA (aparece 12% en lugar de 15% al buscar el producto) y **quitar la restricción** que impide que una factura sin productos llegue a Cuentas por Pagar.
7. **Cuentas por pagar.** Toda factura (nacional o internacional) llega a CxP. Hoy las internacionales no llegan. Pagos totales o parciales; el botón verde de abono siempre visible, sin importar los días de crédito. **"Anticipos Proveedores" se une a CxP** (PC-COM-04, PC-COM-05); eliminar el submenú. El recordatorio del V3 dice "cuentas por cobrar" y es un error de texto: es CxP (DEC-043).
8. **Retenciones** de IR e IVA según el catálogo SRI (catálogo pendiente: DEC-045; usar el existente).
9. **Devolución de compras:** mostrar el número de factura de la compra de origen.
10. **Gasto sin producto:** el usuario elige la cuenta; no hay cuenta por defecto (DEC-012).
11. **Recepción de bodega.** Respetar el flujo vigente del 20/09/2026 (la factura queda pendiente hasta que bodega confirma). Revisar su interacción con la regla de que toda factura llegue a CxP: si hay conflicto, **avisar al usuario** en vez de resolverlo en silencio.

**Pruebas:** CXP-01, IMP-01 e IMP-02.

**No hacer:** ejecutar el pago (Bancos o Caja) ni definir SN (F).

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
