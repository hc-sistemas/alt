# VALIDACIÓN RRHH — Horario Oficial por Colaborador (NOM-05 Atrasos / NOM-06 Horas Extras)

**Fecha:** 2026-07-07
**Validador:** Claude Code (claude-sonnet-5)
**Rama:** `feature/dev2-contabilidad-compras`
**Alcance:** Reglas NOM-05 (atrasos) y NOM-06 (horas extras/suplementarias), documento `Sistema Altamira.pdf`.
**Método:** Diagnóstico de schema real (`altamira_schema.sql`) + código, seguido de pruebas reales end-to-end (Playwright contra `php artisan serve` + build de producción) y, para los dos escenarios de tiempo específicos pedidos (08:20 y 17:15), invocación directa del controlador real de producción con `Carbon::setTestNow()` — no se reimplementó la lógica en un script aparte; se ejecutó el código real de `AsistenciaController`.

---

## 1. Diagnóstico

### 1.1 Schema real (`altamira_schema.sql`)

La tabla `horarios` **no es por-colaborador**: es una tabla de **plantillas reutilizables** (`descripcion`, `hora_entrada`, `hora_salida`, `tolerancia_minutos`, días de la semana). `colaboradores.horario_id` es una FK opcional hacia `horarios`. Este diseño ya estaba en el schema legacy — no había que romperlo ni migrar a columnas por-colaborador.

### 1.2 ¿Existía interfaz de asignación?

**Sí, parcialmente.** El modal de Colaboradores (`Colaboradores/Index.tsx`, tab "Cargo") ya tenía un `<select>` para asignar un `horario_id` existente al colaborador, y `ColaboradorController@store/@update` ya validaba y guardaba `horario_id` (`nullable|exists:horarios,id`). Esto funcionaba correctamente.

**Lo que NO existía:** ninguna forma de **crear o editar** una plantilla de horario. No había `HorarioController`, ninguna ruta `rrhh/horarios/*`, ni página de administración. Solo existía **1 horario** en toda la base de datos ("Jornada General (Lun-Vie)", 08:00–17:00, tolerancia 5 min), imposible de ajustar desde la UI — cualquier horario distinto tendría que insertarse a mano en la BD.

### 1.3 ¿Tenía horario el colaborador "Administrador Sistema"?

Antes de esta sesión: **sí**, `horario_id = 1` (el único horario existente, tolerancia 5 min) — pero llegó así por un seeder, no por ninguna acción de UI.

### 1.4 ¿El cálculo de atrasos/horas extra ya dependía del horario?

**Sí, completamente** — y esto fue clave para el hallazgo del punto 3. `AsistenciaController::registrarEntrada()` y `registrarSalida()` ya cargan `$colaborador->horario` y comparan `now()` contra `hora_entrada`/`hora_salida` + tolerancia. El código NOM-05/NOM-06 **ya estaba implementado**; lo único que faltaba era poder configurar horarios distintos al único que venía sembrado.

---

## 2. Implementado: alta rápida de horarios (plantilla)

Se optó por el **enfoque de plantillas reutilizables** (opción 3 del pedido), por ser el que ya define el schema — no se agregaron columnas nuevas a `colaboradores`.

- **`app/Http/Controllers/RRHH/HorarioController.php`** (nuevo) — `store()` valida (`descripcion`, `hora_entrada`, `hora_salida` formato `H:i`, `tolerancia_minutos` 0–120) y crea la plantilla.
- **`routes/web.php`** — nueva ruta `POST rrhh/horarios` → `rrhh.horarios.store`, dentro del mismo grupo protegido por `permiso:rrhh,ver`.
- **`resources/js/Pages/RRHH/Colaboradores/Index.tsx`** — junto al selector de Horario (tab "Cargo"), botón **"Nuevo horario"** que despliega un mini-formulario inline (descripción, hora entrada, hora salida, tolerancia — default 10 min). Al crear, se asigna automáticamente el nuevo horario al colaborador que se está editando/creando (sin recargar la página, vía `router.post` + `onSuccess`). También se agregó una nota explicativa bajo el selector: *"Los atrasos (NOM-05) y horas extras (NOM-06) se calculan comparando el timbre real contra este horario oficial + su tolerancia."*

No se tocó el flujo de asignación existente (el `<select>` y el guardado en `ColaboradorController`), que ya funcionaba.

---

## 3. 🔴 Bug crítico encontrado y corregido: signo invertido en `diffInMinutes()` (Carbon 3)

**Este es el hallazgo central de esta validación — sin él, NOM-05 y NOM-06 no funcionan aunque el horario esté bien configurado.**

### Diagnóstico

El proyecto usa **Carbon 3.11.4**. A diferencia de Carbon 2.x, en Carbon 3 los métodos `diffInMinutes()`/`diffInHours()` devuelven un valor **firmado** por defecto (no absoluto): `$a->diffInMinutes($b)` es negativo si `$b` es **anterior** a `$a`.

`AsistenciaController` calculaba:
```php
$minutosAtraso = (int) $ahora->diffInMinutes($horaOficial);   // $ahora es POSTERIOR a $horaOficial → resultado NEGATIVO
$horasExtra    = round($ahora->diffInMinutes($horaSalida) / 60, 2); // mismo problema
```

### Confirmación empírica (código sin corregir, invocando el controlador real)

Simulé con `Carbon::setTestNow()` una entrada a las 08:20 (horario 08:00, tolerancia 10 min) y una salida a las 17:15 (horario hasta 17:00), invocando `AsistenciaController::registrarEntrada()`/`registrarSalida()` reales:

```
Asistencia guardada -> minutos_atraso: -20
Asistencia actualizada -> horas_extra: -0.25 | tipo_extra: suplementaria
NO se creó ninguna HorasExtrasAprobacion (revisar).
```

**Impacto real, confirmado con datos:**
- **NOM-05:** `minutos_atraso` se guarda **negativo**. La fórmula de descuento (`Descuento = Minutos × Sueldo/14400`) produciría un descuento **negativo** — es decir, **sumaría** dinero al colaborador en vez de descontarlo.
- **NOM-06:** el candado `if ($horasExtra > 0 && $tipoExtra)` **nunca se cumple** porque `$horasExtra` es negativo → la solicitud de horas extra **nunca se crea**. El tiempo extra se pierde silenciosamente; nunca llega al panel de aprobación.
- Efecto colateral: `app/Jobs/AlertaAtrasosRecurrentes.php` filtra `where('minutos_atraso', '>', 0)` — con el bug, **ningún atraso real dispara jamás la alerta** de atrasos recurrentes.
- Encontré además **2 registros reales preexistentes** (colaborador de prueba "Administrador Sistema", fechas 05 y 06 de julio, de pruebas de sesiones anteriores) con `minutos_atraso` y `horas_extra` negativos — evidencia de que el bug ya estaba ocurriendo en la práctica, no solo en teoría. Los eliminé por ser datos de prueba obsoletos (no afectan a ningún colaborador real: se verificó que **ningún** colaborador real tiene registros con valores negativos).

### Corrección aplicada

`app/Http/Controllers/RRHH/AsistenciaController.php`:
```php
// registrarEntrada()
$minutosAtraso = (int) abs($ahora->diffInMinutes($horaOficial));

// registrarSalida()
$horasExtra = round(abs($ahora->diffInMinutes($horaSalida)) / 60, 2);
```

### Re-validación tras el fix (mismo método, mismo controlador real)

```
Asistencia guardada -> minutos_atraso: 20
Asistencia actualizada -> horas_extra: 0.25 | tipo_extra: suplementaria
HorasExtrasAprobacion creada -> horas_solicitadas: 0.25 | tipo: suplementaria | valor_calculado: 1.56 | estado: pendiente
```

✅ Confirmado también visualmente en el navegador (capturas adjuntas en el repo de trabajo, no versionadas): el panel de Asistencia muestra "20 min" de atraso y "0.25h sup" de extra; el panel de Horas Extras muestra 1 solicitud en estado **"Pendiente"**, sin aprobar.

### ⚠️ Hallazgos relacionados (fuera de alcance de esta tarea en su momento — **corregidos en una sesión posterior**)

El mismo patrón (`diffInHours`/`diffInDays` firmado en Carbon 3) aparecía también en:
- `app/Http/Controllers/Contabilidad/AsientoContableController.php:193` — el candado que impide eliminar un asiento contable de más de 24 horas nunca se activaba.
- `app/Jobs/AlertaVouchersNoLiquidados.php:45` — el número de horas mostrado en la notificación de vouchers sin liquidar salía negativo (cosmético).

**Actualización:** una auditoría completa del patrón `diffInMinutes`/`diffInHours`/`diffInDays` en todo `app/` encontró 2 casos adicionales afectados (`CuentaCobrarController` y `ConciliacionController`) y corrigió los 4. Ver **`AUDITORIA_CARBON3.md`** para el detalle completo, la lista de los 9 usos revisados y la validación del candado de 24h.

---

## 4. Configuración de prueba dejada lista

- **Horario creado:** id=2, "Horario Administrativo (Prueba NOM-05/06)", 08:00–17:00, tolerancia 10 min — creado con la nueva interfaz (Playwright validó el flujo real de clic en "Nuevo horario" → "Crear y asignar" → "Actualizar").
- **Colaborador:** "Administrador Sistema" (usuario `admin@altamira.com`) → `horario_id = 2`. Confirmado en BD.
- **Escenario de hoy (2026-07-07) dejado en el sistema, sin aprobar:**
  - Asistencia: entrada 08:20, salida 17:15, 20 min de atraso, 0.25h extra suplementaria.
  - 1 solicitud en **Horas Extras**, estado **Pendiente**, lista para que Steeven la apruebe manualmente desde `RRHH → Horas Extras` (no fue aprobada ni rechazada por mí).

---

## 5. Resumen ejecutivo

| # | Ítem | Estado |
|---|---|---|
| 1 | Interfaz para asignar horario a un colaborador | Ya existía ✅ |
| 1 | Interfaz para crear/gestionar plantillas de horario | No existía ❌ → 🆕 implementada (alta rápida inline en Colaboradores) |
| 2 | Cálculo de atrasos (NOM-05) dependiente del horario | Ya implementado, pero con bug de signo ❌ → 🆕 corregido |
| 2 | Cálculo de horas extra (NOM-06) dependiente del horario | Ya implementado, pero con bug de signo que impedía crear la solicitud ❌ → 🆕 corregido |
| 3 | Candado NOM-06: horas extra nunca pasan solas a nómina | ✅ confirmado (estado siempre `pendiente`, requiere aprobación manual) |
| — | Bugs relacionados (Carbon 3, otros 4 archivos) | Encontrados y corregidos — ver `AUDITORIA_CARBON3.md` |

**Build:** `npm run build` — 0 errores. `php -l` sin errores de sintaxis en los archivos modificados.

**Archivos modificados/creados:**
- `app/Http/Controllers/RRHH/HorarioController.php` (nuevo)
- `app/Http/Controllers/RRHH/AsistenciaController.php` (fix del bug de signo)
- `routes/web.php` (ruta `rrhh.horarios.store`)
- `resources/js/Pages/RRHH/Colaboradores/Index.tsx` (alta rápida de horario inline)

**Estado del servidor:** dejé `php artisan serve` corriendo para que Steeven pueda entrar directamente a `RRHH → Horas Extras` y aprobar la solicitud pendiente sin tener que levantarlo de nuevo.

**Pendiente de commit:** como en sesiones anteriores, estos cambios más los ya reportados (toasts RRHH, fix de logout 419) siguen sin commitear.
