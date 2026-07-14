# VALIDACIÓN INTEGRACIÓN 2 — Dev 1 (Darío) + Dev 2, post-merge

**Fecha:** 2026-07-14
**Rama:** `feature/dev2-contabilidad-compras`
**Metodología:** igual rigor que `VALIDACION_FINAL_PRE_PRODUCCION.md` — todo probado con datos reales a través del flujo completo (crear → procesar → verificar en BD → verificar asiento contable), no solo lectura de código. Prioridad explícita: Taller→Facturación→Contabilidad, por ser el punto de mayor riesgo de la validación anterior.

---

## 1. Qué trajo Dev 1 (Darío) desde la última integración

6 commits nuevos en `feature/dev1-inventario-productos` (más un merge), no relacionados con los cambios de Dev2 de esta sesión:

| Commit | Contenido |
|---|---|
| `fd28d62` | Stock real en Facturas/Prefacturas/Taller (antes no se tocaba inventario al facturar); bodegas fijas resueltas por nombre; promociones temporales en Listas de Precio; PDF de Orden de Trabajo |
| `07da834` | Descuento especial (PIN/aprobación) — corrige columnas reales de BD; candado duro en Proformas |
| `5dc0eb3` | `egresarStock()` con `lockForUpdate()` + excepción real por stock insuficiente (antes solo advertía) |
| `bf10ead` | Validación del techo de descuento por producto en Facturas vía `DescuentoService` |
| `6b0bf76` | Descuento especial supera techo de producto; quita campo Bodega zombie del formulario |
| `7a71d2c` | Ajustes de UI (anchos/alturas Proformas vs Facturas) |

**Archivos con lógica de negocio real modificada:** `FacturaController.php` (374 líneas), `AprobacionController.php` (reescrito completo — corrige el bug de `limites_descuento` vacía encontrado en la sesión anterior), `PrefacturaController.php`, `ProformaController.php`, `Taller/LiquidacionController.php` (bodega por nombre), `InventarioService.php`, `ListaPrecioController.php` (promo con vigencia), + 1 migración nueva (`descuento_max_promo`).

**Antes de fusionar:** se verificó archivo por archivo que las 4 correcciones de la sesión anterior sobrevivían el merge — sin conflicto textual (git merge automático) ni semántico (revisión manual línea por línea de cada archivo tocado por ambos lados). Las 4 estaban intactas: `Taller/LiquidacionController` (asiento_id), `InventarioService` (stock_actual), `Ventas/FacturaController` y `PrefacturaController` (asiento_id).

---

## 2. Resultado de cada prueba

### 2.1 Taller → Facturación → Contabilidad (PRIORIDAD MÁXIMA)

**✅ Ciclo completo validado con datos reales, sin regresión.**

Ingreso de equipo → Orden de trabajo → Diagnóstico con aprobación del cliente → Agregar repuesto (reserva stock real vía `ResuelveBodegaTaller`, que ahora resuelve "Bodega Taller" por nombre en vez de hardcodear) → Liquidar (genera factura + confirma salida de inventario + genera asiento contable).

- Factura `001-001-000000010`, total $59.50 (mano de obra $25 + repuesto $30 + IVA $4.50).
- Asiento generado y **balanceado** (Debe=Haber=$59.50): Caja General DEBE, Venta de Mercaderías HABER, IVA en Ventas HABER.
- `factura.asiento_id` y `orden.asiento_id` ambos poblados correctamente.
- Repuesto pasó a estado `usado`; stock decrementado correctamente (5→3 tras usar 2 unidades).

El refactor de Darío (bodega resuelta por nombre en vez de hardcode) **no rompió nada** del fix de la sesión anterior — ambos cambios conviven en el mismo archivo sin pisarse.

### 2.2 Ventas — Stock real + Descuento especial + Promociones (nuevo de Dev 1)

**✅ Validado con datos reales — 2 bugs reales encontrados y corregidos** (ver sección 3).

- Factura con descuento que excede el techo del producto (0%) → bloqueada correctamente hasta tener `aprobacion_especial_id` válido.
- Flujo de aprobación por PIN (`AprobacionController::validar()`) probado end-to-end: solicitud → validación de PIN real → registro en `aprobaciones_especiales` → factura aceptada con el descuento autorizado, marcando la aprobación como consumida.
- Stock real descontado de "Bodega Principal UIO" al facturar (6→4 unidades), asiento generado y balanceado ($2,028.60).
- Promoción temporal en Lista de Precio (`descuento_max_promo` + vigencia): un descuento de 10% que antes requería aprobación especial (techo 0%) pasó a aceptarse automáticamente al configurar una promo vigente de 15% — confirmado que `DescuentoService::mapaMaximosPermitidos()` respeta la fecha de vigencia.
- Anulación de factura: revierte correctamente el stock (ingresarStock) usando los movimientos originales por `doc_tipo`/`doc_id`.

### 2.3 Cuentas por Cobrar — Badge de mora (`dias_vencido`)

**✅ Intacto tras el merge.** CxC real id=4, vencida desde 2026-07-06, muestra `dias_vencido = 8` vía `CuentaCobrarController::show()` real — coincide exactamente con el cálculo manual (hoy 2026-07-14 − vencimiento). El fix de Carbon 3 (`abs()`) de la sesión anterior sigue vigente, sin tocar por el merge.

### 2.4 Devolución de Compra → Inventario → Asiento

**✅ Intacto.** Devolución real sobre una compra activa: asiento generado y balanceado ($17.25: Proveedores Locales DEBE, Inventario HABER, Crédito Tributario IVA HABER), stock correctamente decrementado en la bodega real de la compra origen (verificado tras un primer chequeo equivocado contra la bodega incorrecta — el flujo en sí es correcto).

### 2.5 Proformas y Prefacturas

**✅ Validado con datos reales.** Proforma: creación simple sin incidentes. Prefactura: el mecanismo de "reserva" de Darío ahora **transfiere físicamente el stock** de "Bodega Principal UIO" a "Bodega Reservas" (no es un simple flag en el mismo almacén) — confirmado que la unidad aparece correctamente en Bodega Reservas con `cantidad_reservada=1` tras el abono.

**⚠️ Hallazgo menor (no bloqueante):** `PrefacturaController::store()` no tiene fallback para `descripcion` en el detalle (`$det['descripcion'] ?? null`, columna NOT NULL) — si el frontend alguna vez no envía ese campo, la petición falla. El formulario real (`Prefacturas/Form.tsx`) sí lo envía siempre, así que no es un bug activo, pero es frágil. No se corrigió por ser de bajo impacto — se documenta para que el equipo lo tenga en cuenta.

### 2.6 Clientes — CRUD

**✅ Validado con datos reales.** Crear y editar un cliente vía `ClienteController` real, incluyendo campos de crédito (`dias_credito`, `cupo_maximo`) — sin incidentes.

### 2.7 Inventario — Traslados, Activos Fijos, Alertas de stock crítico

**✅ Traslados validado con datos reales:** traslado entre Bodega Principal y Bodega Taller, aceptación con conteo físico, saldo actualizado correctamente en ambas bodegas.

**❌ → 🆕 Bug real encontrado y corregido: creación de Activos Fijos completamente rota.** La columna `activos_fijos.categoria` es `NOT NULL` en la base de datos real, pero **ni el frontend (`Activos/Form.tsx`) ni el backend (`ActivoFijoController::store()`) la capturan o envían nunca** — cualquier intento de crear un activo fijo por la UI real fallaba con una violación de restricción NOT NULL. Además, el modelo `ActivoFijo` no tenía `categoria` en su `$fillable`, así que aunque se hubiera enviado, Eloquent la habría descartado silenciosamente (mismo patrón de mass-assignment ya visto varias veces en esta validación).

Corrección: se agregó `categoria` a `$fillable` del modelo y un valor por defecto (`'General'`) en el controller cuando no se envía explícitamente, hasta que se agregue un selector real en el formulario. Validado de nuevo tras el fix: creación exitosa.

**✅ Alertas de stock crítico:** el reporte de Saldos (`KardexController::saldos()`, filtro `solo_criticos`) funciona correctamente con datos reales. **Gap de funcionalidad confirmado (no bug):** el job programado de notificación automática (`VerificarStockCriticoJob` / `NotificacionService::stockCritico()`) descrito en la especificación original **no existe en el código** — solo existe el reporte manual bajo demanda, no la alerta push automática.

### 2.8 Importación → Costos → Venta

**✅ Datos consistentes (verificación de humo, código no tocado por Dev 1 en esta pasada).** Importación liquidada real (`IMPORTACIÓN SHURE Q1-2026`) con costos de productos correctamente prorrateados y no-cero.

### 2.9 Autorización por PIN — `AprobacionController` (corregido por Dev 1, con 1 bug adicional encontrado)

**✅ → 🆕 Confirmado corregido, con 1 bug adicional encontrado y corregido en datos.**

Darío reescribió `AprobacionController::validar()` — corrige exactamente el bug de la sesión anterior (`limites_descuento` vacía hacía que el endpoint devolviera "código incorrecto" sin importar el código). El código nuevo usa `leftJoin` y separa "código incorrecto" de "sin permisos", pero **la tabla `limites_descuento` seguía vacía** en esta base de datos — el seeder que la puebla con los nombres de columna correctos (`PerfilSeeder.php`, también corregido por Darío) nunca se había vuelto a correr. Se ejecutó (`php artisan db:seed --class=PerfilSeeder`, idempotente) para aplicar el fix a la BD real.

**❌ → 🆕 Bug adicional encontrado (con confirmación del usuario antes de corregir):** 8 usuarios reales vinculados a colaboradores (ids 3–10) tenían `codigo_aprobacion` guardado en **texto plano** (sin hashear) — ningún seeder ni controlador actual produce esto, fue un dato suelto de una sesión anterior. Esto hacía que `Hash::check()` lanzara una excepción no controlada y **rompiera el endpoint de aprobación para cualquiera**, sin importar el código ingresado. Corregido: se re-hashearon los 8 preservando su PIN actual (sin cambiar el valor), eliminando el riesgo de seguridad y el crash. Recomendación de seguimiento: dado que se detectó en texto plano, conviene rotar estos 8 PINs por unos nuevos y comunicarlos por canal seguro, en vez de conservar el valor previamente expuesto.

Validado end-to-end tras ambas correcciones: solicitud de aprobación con PIN real → aceptada → factura con descuento especial completada correctamente.

---

## 3. Resumen de bugs encontrados y corregidos en esta sesión

| # | Módulo | Bug | Severidad |
|---|---|---|---|
| 1 | Ventas/RRHH | `limites_descuento` vacía — el seeder correcto existía pero nunca se había corrido contra esta BD | Alta (bloqueaba todo el flujo de aprobación) |
| 2 | Configuración/Usuarios | `codigo_aprobacion` en texto plano para 8 usuarios reales — crasheaba el endpoint de aprobación para cualquiera | Crítica (seguridad + disponibilidad) |
| 3 | Inventario | Creación de Activos Fijos rota end-to-end: columna `categoria` NOT NULL nunca capturada ni en `$fillable` | Alta (funcionalidad completamente inoperante) |

Ningún bug se encontró en el punto de mayor riesgo (Taller→Facturación→Contabilidad) — el fix de la sesión anterior sigue funcionando exactamente igual tras el merge.

---

## 4. Flujos cruzados Dev1↔Dev2 — confirmación final

| Flujo | Estado |
|---|---|
| Factura → Asiento | ✅ Intacto |
| Factura crédito → CxC → badge de mora (`dias_vencido`) | ✅ Intacto |
| Devolución → Inventario → Asiento | ✅ Intacto |
| Taller → Facturación → Asiento | ✅ Intacto (el más crítico — sin regresión) |
| Importación → Costos → Venta | ✅ Datos consistentes |

---

## 5. FASE 3 — Prueba de humo Dev 2 (confirmar que nada se rompió)

Ninguno de los archivos de Contabilidad, Compras, Bancos, RRHH o Reportes SRI fue tocado por los commits nuevos de Dev 1 (`git diff` sin cambios) — riesgo de regresión nulo por construcción. Aun así, se corrió una prueba de humo real de cada uno:

| Módulo | Prueba | Resultado |
|---|---|---|
| Contabilidad | Asiento manual balanceado + PDF Balance de Comprobación | ✅ |
| Compras | (sin CxP pendiente real disponible para pago; código sin diff, sin riesgo) | ⚠️ No probado end-to-end esta vez — cero cambios de código, ya validado en la sesión anterior |
| Bancos | Index de Movimientos Bancarios | ✅ |
| RRHH | Index de Nómina | ✅ |
| Reportes SRI | Formulario 103 y 104 (PDF) | ✅ |

---

## 6. FASE 4 — Dashboard general

**❌ No implementado — sigue siendo un stub.** `DashboardController::index()` devuelve `ventas_hoy`, `ventas_ayer`, `meta_mes` y `ventas_mes` **hardcodeados en 0**. El frontend ya tiene los widgets construidos (`VentasDia`, `MetaMes`, `VentasMensuales`, de Fase 1), pero **no están conectados a datos reales de Ventas, Compras o Bancos**. No existen los widgets pedidos por el cliente: Ratios financieros, Cartera por cobrar, CxP ordenada, gráficos de Ingresos/Egresos, flujo de caja diario. Esto es una funcionalidad pendiente de construir, no un bug — se documenta como pendiente real para priorizar.

---

## 7. Balance de Comprobación

**Antes de esta sesión:** DEBE = HABER = $110,741.03
**Después de todas las pruebas y limpiezas de esta sesión:** DEBE = HABER = **$110,741.03** — sin cambios, verificado después de cada prueba individual y al final.

`plan_cuentas`: 206 cuentas (sin cambios). `php artisan migrate:status`: 0 pendientes tras correr la única migración nueva de Dev 1. `npm run build`: sin errores.

---

## 8. Veredicto final

**Sistema integrado (Dev 1 + Dev 2) listo para producción**, con 2 pendientes a decidir por el negocio (no bloqueantes para lo ya construido):

1. **Dashboard general** — no implementado (stub con ceros). Es la funcionalidad más visible pendiente del documento de especificaciones del cliente.
2. **Alertas automáticas de stock crítico y demás jobs programados** (`NotificacionService`, `VerificarStockCriticoJob`, etc.) — solo existe el reporte manual, no las notificaciones push automáticas de la especificación original.

El flujo de mayor riesgo (Taller→Facturación→Contabilidad) sigue funcionando exactamente igual tras traer el trabajo nuevo de Darío. Se encontraron y corrigieron 3 bugs reales adicionales (2 de configuración/seguridad, 1 de Inventario) durante esta validación, todos con datos reales y limpieza completa verificada. Balance de Comprobación cuadrado en $110,741.03 antes y después de cada prueba.
