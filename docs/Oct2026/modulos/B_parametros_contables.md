# Módulo B — Parámetros contables y plan de cuentas

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `01_Parametros_Contables.pdf` completo (secciones 3 a 10), `02-08 §2.4`. Es el módulo que más afecta al resto.

**Prompt de arranque:**
> Implementa los cambios de "Parámetros contables y plan de cuentas" de Altamira ERP según `docs/Oct2026/01_Parametros_Contables.pdf`. Lee `CLAUDE.md` y las "Reglas comunes" de este mismo archivo. Primero revisa qué plan de cuentas hay realmente en la BD y compáralo con el catálogo de la sección 4 del PDF. Muéstrame las diferencias antes de cambiar nada.

**Tareas**
1. **Verificar el plan cargado** contra la sección 4 (catálogo de cuentas usadas) y reportar diferencias. El `CLAUDE.md` dice que ya se cargó el "Plan 2025"; estos documentos piden el V4.
2. **Crear 5 cuentas nuevas** (aprobadas por Edwin; crear antes de cargar el plan):
   - `1.01.03.05` Cheques en Cartera (post fechados recibidos)
   - `2.01.02.04` IVA por Pagar del mes (neto)
   - `2.01.06` Cuenta por pagar al socio
   - `2.01.07` Impuesto a la Renta por Pagar
   - `4.03.02` Otros ingresos
3. **Cuenta padre `1.01.02.02`** pasa a `Permite_Asientos = NO` (DEC-007).
4. **Corregir los 11 parámetros incorrectos** de la pantalla (sección 7). No modificar en código: ajustar el valor del parámetro.

   | Clave | Valor actual | Debe apuntar a |
   |---|---|---|
   | `cta_vouchers` | 1.01.03.01 | 1.01.03.04 Vouchers por Liquidar |
   | `cta_clientes_exterior` | 1.01.03.01 | 1.01.03.03 Clientes del Exterior |
   | `cta_ventas_locales` | 4.01.06 | La cuenta de venta sale de la subcategoría del producto |
   | `cta_anticipos_clientes` | 2.01.01.01 | 2.01.04 Anticipos recibidos de clientes |
   | `cta_costo_ventas` | 5.01.05 | Sale de la subcategoría del producto |
   | `cta_costo_ventas_importadas` | 5.01.05 | Sale de la subcategoría del producto |
   | `cta_anticipos_proveedores` | 1.01.03.01 | 1.01.06.01 Anticipos a Proveedores |
   | `cta_inventario_mercaderia` | 1.01.04.05 | Sale de la subcategoría; no hay cuenta por defecto |
   | `cta_ajuste_inventario` | 5.01.05 | 5.01.09 Faltantes y Mermas |
   | `cta_ajuste_conciliacion` | 6.02.01 | 6.01.14 Diferencias en Conciliación Bancaria |
   | `cta_anticipos_empleados` | 1.01.03.01 | 1.01.06.02 Préstamos y Anticipos a Empleados |

5. **Completar los 3 parámetros sin configurar:** `cta_bancos_locales` (el banco se elige al cobrar, no hay uno por defecto), `cta_inventario_transito` (1.01.04.07) y `cta_bancos_exterior` (1.01.02.03).
6. **Parámetros incompletos:** agregar la contrapartida de fondos de reserva (2.01.03.04), décimo tercero (2.01.03.03.01), décimo cuarto (2.01.03.03.02) y vacaciones (2.01.03.03.03). Agregar el parámetro del IVA de la comisión de tarjeta (1.01.05.01) y el de proveedores logísticos (2.01.01.03).
7. **Eliminar** `cta_gasto_compras_default` (6.01.12). Quien registre un gasto sin producto elige la cuenta (DEC-012).
8. **Cuenta de venta/costo/inventario por subcategoría.** Crear la estructura para que cada una de las 20 subcategorías (PC-CAT-01 a 20, sección 6 del PDF) defina sus tres cuentas. La tabla de equivalencias está en el módulo F (Inventario); coordinar con ese chat y **no tocar la tabla `productos`**.
9. **Tabla de eventos.** Cada evento debe mostrar cuenta de debe y de haber y no puede quedar sin cuenta (sección 5).
10. Agregar a la validación `AsientoService::validarConfiguracion()` y a `codigosCompra()` los parámetros nuevos.

**Pruebas (sección 9):** PC-T01 (venta a crédito → Debe 1.01.03.01 / Haber 4.01.xx + 2.01.02.01), PC-T02 (compra de producto → Debe 1.01.04.xx / Haber 2.01.01.01), PC-T03 (cobro con tarjeta → Debe 1.01.03.04 / Haber 1.01.03.01), PC-T04 (anticipo → Haber 2.01.04). **Ojo:** PC-T01 y PC-T02 citan 4.01.09 y 1.01.04.09, que están en revisión (DEC-030); la cuenta sin clasificar solo es válida para Computadoras y PISOS LED.

**Pendientes que NO se implementan sin respuesta:** DEC-011 (contrapartida del asiento de apertura), DEC-004 (renta), DEC-009 (participación), DEC-010 (retiros al cierre), DEC-027 (cheques de "otro pago"), DEC-028 (aplicación del anticipo), DEC-029 (devolución en ventas), DEC-025 (contracargos).

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
