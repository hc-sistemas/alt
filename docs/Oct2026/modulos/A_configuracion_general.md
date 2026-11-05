# Módulo A — Configuración general

Altamira ERP · Instrucciones de ejecución (Oct 2026). Este archivo es autónomo: pégalo o indícalo en un chat nuevo y trabaja **solo** este módulo.

**Mapa de módulos (para entender las referencias "módulo B", "módulo C", etc.):** A Configuración · B Parámetros contables · C Bancos · D Caja · E Tarjetas · F Inventario · G Compras e importaciones · H Ventas y cobranza · I Contabilidad · J RRHH y nómina · K Taller. Solo trabaja en tu módulo; si algo exige cambiar otro, avísalo y no lo hagas.

---

**Documentos:** `02-01_Configuracion_General.pdf`, sección "Configuración" del V3. Decisiones: DEC-033, 035, 036, 037, 038.

**Prompt de arranque:**
> Implementa el módulo "Configuración general" de Altamira ERP según `docs/Oct2026/02-01_Configuracion_General.pdf`. Lee primero `CLAUDE.md`, ese PDF y las "Reglas comunes" de este mismo archivo. No avances a otros módulos. Antes de editar, dime qué encontraste en el código actual y qué vas a cambiar.

**Tareas**
1. **Centros de costo.** Deben existir tres: Altamira UIO (RUC 1711293454001), Altamira Import (RUC 1755265848001, hoy no existe) y Altamira FIX (bajo el RUC de UIO, pero con reportes contables como si fuera otra empresa). Hoy el selector muestra "MATRIZ — Altamira Matriz" y "FIX". El nombre oficial está pendiente (DEC-037): proponer el cambio a UIO y confirmar con el usuario antes de renombrar.
2. **Permisos.** "Los permisos siguen sin valer". Verificar que cada acción (ver, crear, editar, eliminar, anular) consulte el permiso del rol (`usePermiso` en el frontend y verificación en el backend). Roles: Super Admin, Admin, Contador, Vendedor, Bodeguero y Técnico. Módulos: Dashboard, Ventas, Compras, Inventario, Contabilidad, Bancos, RRHH, Taller, Reportes, Configuración y Asistencia. La matriz de qué puede hacer cada rol no está definida (DEC-036): no inventarla; solo hacer que se respete la que esté guardada.
3. **Límites de descuento por rol** (DEC-035, aprobada).

   | Rol | % sin aprobación | % con aprobación |
   |---|---|---|
   | Admin | 10 | 100 |
   | Vendedor | 8 | 20 |
   | Bodeguero | 8 | 20 |

   El vendedor **no puede aprobar descuentos** (hoy tiene "¿Puede aprobar?" marcado y 100%). El descuento del vendedor es 0 u 8%, no valores intermedios. Recordar que la tabla `limites_descuento` exige `empresa_id`.
4. **Carga inicial.** Preparar el borrado de datos de prueba y la carga desde Excel del plan de cuentas V4 y del inventario. **No ejecutar el borrado sin confirmación explícita del usuario.**
5. **Empresas.** Cada documento, colaborador, caja y banco pertenece a una sola empresa. Nombre comercial de ambas: Altamira Light & Sound.

**Pruebas:** el documento no define ninguna (CAM-012). Proponer al usuario pruebas de permisos y de límites de descuento.

**No hacer:** definir la matriz de permisos por rol (DEC-036) ni los nombres de cajas sin confirmación.

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
