# AUDITORÍA — Carbon 3: `diffInMinutes` / `diffInHours` / `diffInDays` firmados

**Fecha:** 2026-07-07
**Validador:** Claude Code (claude-sonnet-5)
**Rama:** `feature/dev2-contabilidad-compras`
**Origen:** durante la validación de NOM-05/NOM-06 (ver `VALIDACION_RRHH_HORARIOS.md`) se encontró que `AsistenciaController` guardaba minutos de atraso y horas extra en negativo porque Carbon 3.11.4 (instalado en el proyecto) cambió el comportamiento por defecto de `diffInX()`: ahora es **firmado** (negativo si el argumento es anterior al objeto base), mientras que en Carbon 2 el valor por defecto era **absoluto** (siempre positivo). Esta auditoría busca **todos** los usos de este patrón en `app/` y corrige los que dependían del comportamiento antiguo.

**Método:** `grep -rn "diffInMinutes\|diffInHours\|diffInDays" app/`, revisión uno a uno del contexto, y para los casos corregidos, confirmación empírica invocando el código real (controladores/jobs reales) con `Carbon::setTestNow()` — no se reimplementó ninguna lógica en scripts aparte.

---

## Resultado de la auditoría (todos los usos encontrados)

| # | Archivo:línea | Uso | ¿Afectado? | Acción |
|---|---|---|---|---|
| 1 | `app/Http/Controllers/RRHH/AsistenciaController.php:103` | `minutos_atraso` al registrar entrada | ❌ Sí — NOM-05 guardaba atraso negativo | 🆕 Corregido (sesión anterior): `abs()` |
| 2 | `app/Http/Controllers/RRHH/AsistenciaController.php:152` | `horas_extra` al registrar salida | ❌ Sí — NOM-06 nunca creaba la solicitud de aprobación (`> 0` fallaba) | 🆕 Corregido (sesión anterior): `abs()` |
| 3 | `app/Http/Controllers/Contabilidad/AsientoContableController.php:193` | Candado de 24h para eliminar un asiento contable | ❌ Sí — el candado nunca se activaba, permitiendo eliminar asientos de cualquier antigüedad | 🆕 **Corregido en esta sesión**: reemplazado por comparación directa `$asiento->created_at->lt(now()->subHours(24))` (no depende de signo) |
| 4 | `app/Jobs/AlertaVouchersNoLiquidados.php:45` | Texto de notificación "lleva Nh sin liquidar" | ❌ Sí (cosmético) — el mensaje mostraba horas negativas | 🆕 **Corregido en esta sesión**: `abs()`. El filtro real de "más de 72 horas" (línea 26) usa una comparación de fecha directa en la query y **no** estaba afectado. |
| 5 | `app/Http/Controllers/Ventas/CuentaCobrarController.php:53` (índice) | `dias_vencido` en el listado de Cuentas por Cobrar | ❌ Sí — se guardaba negativo; el frontend (`CxC/Index.tsx`) solo pinta el badge de vencimiento si `dias_vencido > 0`, así que **nunca se mostraba** la mora real | 🆕 **Corregido en esta sesión**: `abs()` |
| 6 | `app/Http/Controllers/Ventas/CuentaCobrarController.php:107` (show) | `dias_vencido` en el detalle de una Cuenta por Cobrar | ❌ Sí — mismo problema que el anterior; además `CxC/Index.tsx` usa `dias_vencido > 360` para habilitar "castigar cuenta" (solo Super Admin), que tampoco se activaba nunca | 🆕 **Corregido en esta sesión**: `abs()` |
| 7 | `app/Http/Controllers/Bancos/ConciliacionController.php:691` | Tolerancia ±2 días del auto-match banco↔sistema en conciliación | ❌ Sí — sin `abs()`, la tolerancia dejaba de ser simétrica: cualquier partida de sistema **posterior** a la fecha del banco (diferencia negativa) pasaba el filtro `<= 2` sin importar cuántos días de diferencia real hubiera | 🆕 **Corregido en esta sesión**: `abs()` |
| 8 | `app/Models/CuentaPagar.php:71` | `dias_vencimiento` (Cuentas por Pagar) | ✅ No — ya pasaba `false` explícito y, verificado empíricamente, la dirección de la resta (`now()->diffInDays($fecha_vencimiento)`) da negativo cuando la fecha ya pasó y positivo cuando falta, que es exactamente lo que `getUrgenciaAttribute()` espera (`< 0` ⇒ "vencida"). No requiere cambio. |
| 9 | `app/Http/Controllers/RRHH/LiquidacionesController.php:84-85` | `mesesLaborados` / `diasLaborados` en cálculo de liquidación | ✅ No — hay un guard previo (`if ($fechaSalida->lt($fechaIngreso)) return error`) que garantiza que `fechaSalida` siempre es posterior o igual a `fechaIngreso` antes de llegar al `diffInX()`, por lo que el resultado siempre es positivo. No requiere cambio. |

**Resumen:** 7 de 9 usos estaban afectados por el cambio de comportamiento de Carbon 3; los 7 fueron corregidos (2 en la sesión de NOM-05/06, 5 en esta sesión). Los 2 restantes ya eran seguros (uno por pasar `false` con la dirección correcta, otro por un guard de orden previo).

---

## Validación del candado de 24h en Asientos Contables (ítem 3)

Se invocó `AsientoContableController::destroy()` real (no reimplementado), autenticado como `super_admin`, con dos asientos de prueba creados y borrados en la misma corrida (no afectan datos reales):

- **Asiento de 30 horas de antigüedad** (`created_at` forzado a 30h antes de un "ahora" simulado con `Carbon::setTestNow()`):
  ```
  Flash: "No se puede eliminar: el asiento tiene más de 24 horas. Usa la opción Anular."
  Sigue existiendo: SÍ (correcto, no se borró)
  ```
- **Asiento de 2 horas de antigüedad:**
  ```
  Flash: "Asiento TEST-RECIENTE-002 eliminado permanentemente."
  Sigue existiendo: NO (correcto, se borró)
  ```

✅ El candado bloquea correctamente asientos con más de 24 horas y permite eliminar los recientes, tal como exige el diseño (`// CORRECCIÓN 4: eliminar físico (solo super_admin, máx 24 h)`).

---

## Archivos modificados en esta sesión

- `app/Http/Controllers/Contabilidad/AsientoContableController.php` — candado de 24h: comparación directa con `lt()` en vez de `diffInHours()`.
- `app/Http/Controllers/Ventas/CuentaCobrarController.php` — `dias_vencido` con `abs()` en `index()` y `show()`.
- `app/Http/Controllers/Bancos/ConciliacionController.php` — tolerancia ±2 días del auto-match con `abs()`.
- `app/Jobs/AlertaVouchersNoLiquidados.php` — horas en el mensaje de notificación con `abs()`.

(El fix de `app/Http/Controllers/RRHH/AsistenciaController.php` — ítems 1 y 2 — se aplicó en la sesión anterior de validación de NOM-05/06 y se lista aquí solo por completitud de la auditoría.)

**Build:** `npm run build` — 0 errores. `php -l` sin errores de sintaxis en los 4 archivos modificados.

**Estado del commit:** ⚠️ Sin commitear, junto con el resto de cambios pendientes de la sesión (toasts RRHH, fix de logout 419, alta de horarios NOM-05/06) — a revisar y commitear todo junto al final, según lo pedido.

---

## Actualización (2026-07-11) — un octavo caso encontrado durante VALIDACION_FINAL_PRE_PRODUCCION.md

La auditoría original solo buscó `diffInMinutes`/`diffInHours`/`diffInDays`. Durante la validación de Fase 4 (RRHH → Nómina) se encontró que **`diffInMonths` tiene el mismo problema** y no había sido incluido en el grep original:

- `app/Http/Controllers/RRHH/NominaController.php:447` — `$mesesContrato = (int) now()->diffInMonths($col->fecha_ingreso)`, usado para decidir si aplican Fondos de Reserva mensualizados (regla: desde el mes 13 de contrato). Firmado en Carbon 3, siempre negativo (la fecha de ingreso siempre es anterior a `now()`), por lo que `$mesesContrato >= 13` **nunca se cumplía para ningún colaborador, sin importar su antigüedad real** — Fondos de Reserva mensualizados nunca se pagaban. Corregido con `abs()`.
- `app/Http/Controllers/RRHH/LiquidacionesController.php:84` (`$fechaIngreso->diffInMonths($fechaSalida)`) — revisado de nuevo, sigue **sin necesitar cambio**: hay un guard previo (`fechaSalida->lt(fechaIngreso)` → error) que garantiza que `fechaSalida` sea posterior, por lo que el resultado siempre es positivo.

Búsqueda ampliada confirmó que no existen usos de `diffInYears`, `diffInWeeks` ni `diffInSeconds` en `app/` — el patrón queda cerrado en 8 casos afectados (7 de la auditoría original + este), todos corregidos.
