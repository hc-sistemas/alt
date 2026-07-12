# VALIDACIÓN RRHH — Descuento Automático de Préstamos/Anticipos en Rol de Pagos

**Fecha:** 2026-07-07
**Validador:** Claude Code (claude-sonnet-5)
**Rama:** `feature/dev2-contabilidad-compras`
**Alcance:** Regla NOM-01 (registro de préstamo) y NOM-02 (cruce automático `1.1.3.4` en el rol de pagos), documento `Sistema_Altamira.pdf`.
**Método:** Pruebas reales end-to-end vía Playwright contra `php artisan serve` + build de producción, con verificación de base de datos (PostgreSQL) antes/después de cada operación. No es solo lectura de código — cada resultado fue reproducido con datos reales y limpiado al finalizar.

---

## Leyenda

| Símbolo | Significado |
|---|---|
| ✅ | Probado con datos reales — funciona correctamente |
| ❌ | Bug confirmado con datos reales — corregido en esta sesión |
| 🆕 | Corrección nueva aplicada en esta sesión |

---

## Regla textual validada (NOM-02)

> "HABER (Cruces): 1.1.3.4 (Préstamos y Anticipos a Empleados) -> Descuento automático de la quincena o cuota del préstamo."

---

## 1. Registro del préstamo (Regla NOM-01)

**✅ Verificado con datos reales**

Préstamo de prueba creado vía UI real: colaborador id=2, tipo `prestamo`, monto $300,00, cuota mensual $100,00.

| Cuenta | Debe | Haber |
|---|---|---|
| 1.1.3.04 Préstamos y Anticipos a Empleados | $300,00 | |
| 1.1.1.03 Bancos Locales | | $300,00 |

Balanceado. Préstamo creado: `saldo=300.00`, `estado=activo`.

**Nota sobre "número de cuotas":** el sistema **no tiene** un campo `numero_cuotas` explícito (se revisó el formulario y el modelo `PrestamoEmpleado` — campos: `monto_total`, `saldo`, `cuota`, no hay contador de cuotas). El mecanismo real es: el usuario define directamente el **monto de la cuota** (ej. $100), y el número de cuotas queda implícito (`monto_total ÷ cuota`). El sistema descuenta `cuota` (o la mitad si el rol es quincenal, vía `PrestamoEmpleado::cuotasMes()`) en cada rol hasta que `saldo` llega a 0. Esto **sí cumple** el requisito de "descuento automático proporcional" del documento — solo que la "proporción" la define el monto de cuota, no un contador de cuotas. No se considera un bug: es un diseño válido y ya funcional para el propósito pedido.

---

## 2. ❌ → 🆕 Bug encontrado y corregido: el saldo del préstamo NO se actualizaba al procesar el rol

**Este es el hallazgo central de esta validación.**

### Diagnóstico (código)

- `NominaController::calcularDetalle()` **sí** calcula automáticamente `descuento_prestamos` vía `PrestamoEmpleado::cuotasMes($col->id, $periodo_tipo)` al generar el rol en borrador — la columna de Egresos se llena sola, correctamente. ✅
- `AsientoService::nomina()` **sí** genera la línea `HABER 1.1.3.04` con la suma de `descuento_prestamos + descuento_anticipos` de todos los colaboradores, al **procesar** el rol (borrador → procesado) — tal como pide la Regla NOM-02 ("Al cambiar el estado del mes a [Procesado], se dispara el asiento"). ✅
- **Pero** la actualización real de `prestamos_empleados.saldo` / `estado` estaba en `NominaController::pagar()` (procesado → pagado), **no** en `procesar()`. Esto significa que justo después de procesar un rol, el asiento contable ya mostraba la cuota descontada, pero la ficha del préstamo seguía mostrando el saldo viejo — una inconsistencia real entre el libro contable y el registro de RRHH, hasta que alguien registrara el pago (acción separada, no siempre inmediata).

### Confirmación empírica (con datos reales, código sin corregir)

1. Generé el rol de pagos mensual de julio/2026 (colaborador de prueba + resto de colaboradores reales de la empresa).
2. Columna Egresos mostró `descuento_prestamos = $100.00` para el colaborador de prueba — correcto.
3. Procesé el rol (botón "Procesar Nómina"). Resultado:
   - Asiento generado (`AS-2026-0067`), balanceado, con línea `1.1.3.04 Préstamos y Anticipos a Empleados HABER $120,00` (= $100 de la prueba + $20 de un préstamo real ya existente de otro colaborador).
   - **Saldo del préstamo de prueba: seguía en $300,00, estado `activo`** — **NO se redujo**, pese a que el asiento ya reflejaba el descuento. ❌ Bug confirmado con datos reales, no solo por lectura de código.

### Corrección aplicada

`app/Http/Controllers/RRHH/NominaController.php`:
- Se **movió** el bloque de descuento de saldo de préstamos/anticipos (el `foreach` sobre `$nomina->detalles` que actualiza `PrestamoEmpleado`) desde `pagar()` hacia `procesar()`, ejecutándose dentro de la misma `DB::transaction()` que genera el asiento contable.
- Se eliminó ese bloque de `pagar()` (para no descontar dos veces) y se dejó un comentario explicando que el descuento ya ocurrió en `procesar()`.

Con esto, el saldo de `prestamos_empleados` queda **siempre consistente** con el asiento contable ya generado, exactamente en el momento que especifica la Regla NOM-02 ("al cambiar el estado del mes a Procesado").

---

## 3. Re-validación completa tras el fix: ciclo de 3 cuotas + 4to rol

Se repitió el préstamo de prueba ($300, cuota $100) y se generaron/procesaron **4 roles mensuales consecutivos reales** (julio, agosto, septiembre, octubre 2026) con el código ya corregido:

| Rol | Mes | `descuento_prestamos` calculado | Saldo tras procesar | Estado tras procesar |
|---|---|---|---|---|
| 1 | Julio 2026 | $100,00 | **$200,00** | activo |
| 2 | Agosto 2026 | $100,00 | **$100,00** | activo |
| 3 | Septiembre 2026 | $100,00 | **$0,00** | **pagado** ✅ |
| 4 | Octubre 2026 | **$0,00** (préstamo ya pagado, excluido por `scopeActivos()`) | $0,00 (sin cambio) | pagado |

**Todo confirmado exactamente como pide el documento:**
- ✅ El sistema detecta automáticamente el préstamo activo del colaborador.
- ✅ La cuota aparece sola en la columna Egresos del rol.
- ✅ El asiento de cada rol procesado incluye la línea `HABER 1.1.3.04` con el monto correcto.
- ✅ El saldo se reduce correctamente en `prestamos_empleados` **inmediatamente al procesar** (ya no al pagar).
- ✅ Tras la 3ª cuota, saldo llega a $0,00 y el estado cambia automáticamente a `pagado`.
- ✅ Un 4to rol generado después **no vuelve a descontar** — el préstamo pagado queda correctamente excluido (`scopeActivos()`: `estado='activo' AND saldo>0`).

Cada uno de los 3 asientos de rol (julio/agosto/septiembre) quedó balanceado (Debe = Haber) y con la línea `1.1.3.04` incluyendo tanto la cuota de prueba como la cuota de un préstamo real preexistente de otro colaborador (verificado que ese préstamo real también se redujo correctamente en paralelo — $20/mes × 3 = $60 — y fue restaurado a su valor original al limpiar).

---

## Resumen ejecutivo

| # | Ítem | Estado |
|---|---|---|
| 1 | Registro de préstamo (asiento DEBE 1.1.3.4 / HABER 1.1.1.3) | ✅ |
| 1 | Campo "número de cuotas" | No existe explícito; diseño por monto de cuota — válido, no es bug |
| 2 | Descuento automático calculado en el rol (columna Egresos) | ✅ |
| 2 | Asiento de nómina incluye HABER 1.1.3.4 | ✅ |
| 2 | Saldo del préstamo se actualiza al **procesar** el rol | ❌→🆕 corregido |
| 3 | Ciclo completo de 3 cuotas hasta saldo $0 y estado `pagado` | ✅ (tras el fix) |
| 3 | 4to rol no descuenta préstamo ya pagado | ✅ |

**Build:** `npm run build` — 0 errores. `php -l` sin errores de sintaxis.

**Limpieza:** los 4 roles de nómina de prueba (julio–octubre 2026) y sus asientos contables fueron eliminados; el préstamo de prueba (colaborador id=2) y su asiento de registro fueron eliminados; el préstamo de otro colaborador (afectado colateralmente por compartir el mismo mecanismo de cálculo agregado de nómina) fue restaurado a su saldo y estado originales. Verificado tras la limpieza: mismo número de préstamos y nóminas que al inicio, 0 asientos huérfanos, Balance de Comprobación general cuadrado (DEBE = HABER = $163.481,81).

**Archivo modificado:**
- `app/Http/Controllers/RRHH/NominaController.php` — el descuento de saldo de préstamos/anticipos se mueve de `pagar()` a `procesar()`, para que quede sincronizado con el asiento contable en el mismo momento que exige la Regla NOM-02.
