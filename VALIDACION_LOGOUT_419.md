# VALIDACIÓN — Bug 419 "Page Expired" al Cerrar Sesión

**Fecha:** 2026-07-07
**Validador:** Claude Code (claude-sonnet-5)
**Rama:** `feature/dev2-contabilidad-compras`
**Reportado como:** "pendiente, reportado hace varias sesiones, no resuelto"
**Método:** Pruebas reales end-to-end vía Playwright contra `php artisan serve` + build de producción (`npm run build`), incluyendo un caso con inactividad real de sesión (no simulada).

---

## Diagnóstico

El botón "Cerrar Sesión" del Topbar usa `router.post(route('logout'))` de Inertia, que viaja sobre `window.axios` (configurado en `resources/js/bootstrap.js`). Antes del fix, `bootstrap.js` copiaba el token CSRF del `<meta name="csrf-token">` al header `X-CSRF-TOKEN` de axios **una sola vez, en la carga inicial de la página**:

```js
const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
}
```

Laravel valida `X-CSRF-TOKEN` con prioridad sobre `X-XSRF-TOKEN`. El problema: ese valor queda **congelado** desde la carga inicial. Cualquier regeneración de sesión (login, o el propio logout, o el paso del tiempo si el middleware de sesión rota el token) invalida ese valor fijo, y **todo POST posterior con axios** — incluidas las visitas internas de Inertia, que reutilizan esta misma instancia de axios — recibía 419, incluido el propio logout.

Esto es el mismo bug de fondo que ya se había corregido una vez para otros formularios POST (commit `91d1b61`), pero el logout seguía afectado porque la causa raíz (el header fijo) no se había eliminado, solo mitigado en otros puntos.

## Corrección

`resources/js/bootstrap.js` — se **eliminó por completo** la asignación fija de `X-CSRF-TOKEN` desde el meta tag. axios ya adjunta automáticamente `X-XSRF-TOKEN` leyendo la cookie `XSRF-TOKEN`, que Laravel reemite **fresca en cada respuesta** — por lo que no hace falta gestionar el token a mano, y nunca queda obsoleto.

```diff
- const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
- if (csrfMeta) {
-     window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
- }
+ // No fijar X-CSRF-TOKEN desde el meta tag: ese valor queda congelado en la carga
+ // inicial y Laravel lo prioriza sobre X-XSRF-TOKEN, así que tras cualquier
+ // regeneración de sesión (login, logout) queda obsoleto y todo POST posterior
+ // -incluidas las visitas internas de Inertia, que usan este mismo axios- recibe
+ // 419. axios ya adjunta X-XSRF-TOKEN automáticamente leyendo la cookie
+ // XSRF-TOKEN (que Laravel reemite fresca en cada respuesta), así que no hace
+ // falta gestionar el token a mano.
```

Nota: esta corrección ya existía en el working tree de una sesión anterior, pero **nunca se había commiteado** — por eso el bug seguía apareciendo como "pendiente" en el seguimiento del usuario pese a estar ya resuelto en el código. Ver también commit `ef35673` (import de `bootstrap.js` en `app.tsx`), relacionado pero distinto: ese corrigió que `window.axios` fuera `undefined`; este corrige que el CSRF token quedara obsoleto.

---

## Validación con datos reales (Playwright)

### Caso 1 — Logout inmediato tras navegar

Secuencia: login → navegar por 3 páginas reales (`/rrhh/colaboradores`, `/bancos/movimientos`, `/dashboard`) → clic en "Cerrar Sesión".

```
=== clic en Cerrar Sesion ===
POST /logout status: 302
URL final: http://127.0.0.1:8000/login
=== CONSOLE ERRORS ===
(ninguno)
```

**✅ 302 (no 419).** Sin errores de consola.

### Caso 2 — Logout tras 3 minutos de inactividad real de sesión

Secuencia: login → esperar **3 minutos reales** sin ninguna interacción (sin polling, sin navegación) → clic en "Cerrar Sesión".

```
=== esperando 3 minutos SIN actividad (sesion inactiva) ===
=== fin de espera, intentando logout ahora ===
POST /logout status tras 3 min inactivo: 302
URL final: http://127.0.0.1:8000/login
```

**✅ 302 (no 419)**, incluso con la sesión inactiva por 3 minutos — el escenario que originalmente disparaba el 419 con el token fijo.

---

## Resumen ejecutivo

| # | Caso | Estado |
|---|---|---|
| 1 | Logout inmediato tras login + navegación | ✅ 302, sin errores de consola |
| 2 | Logout tras 3 min de inactividad real | ✅ 302, sin errores de consola |

**Build:** `npm run build` — 0 errores.

**Archivo modificado:** `resources/js/bootstrap.js` — se elimina la fijación manual y obsoleta de `X-CSRF-TOKEN`, dejando que axios use `X-XSRF-TOKEN` desde la cookie fresca en cada request.

**Estado del commit:** ⚠️ Este fix, junto con las correcciones de toasts duplicados en RRHH (`Colaboradores/Index.tsx`, `HorasExtras/Index.tsx`, `Asistencia/Index.tsx`), sigue **sin commitear** en la rama `feature/dev2-contabilidad-compras`. Se recomienda commitearlos para que dejen de reaparecer como bugs "pendientes" en próximas sesiones.
