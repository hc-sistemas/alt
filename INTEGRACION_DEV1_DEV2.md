# Reporte de Integración Dev1 + Dev2

**Fecha:** 2026-07-06  
**Rama activa:** `feature/dev2-contabilidad-compras`  
**Ramas integradas:** `origin/main` (estable) + `origin/feature/dev1-inventario-productos` (Taller + fixes Ventas)

---

## Resumen ejecutivo

| Item | Resultado |
|---|---|
| Migraciones | 100 corridas / 0 pendientes |
| Build frontend | 0 errores (npm run build) |
| Tests de integración | **10/10 ✅** |
| Conflictos de merge resueltos | 3 (types/index.ts, routes/web.php, Sidebar.tsx) |
| Datos sembrados | 15 clientes · 7 facturas · 4 OTs · 48 asientos |

---

## Datos sembrados — IntegracionSeeder

| Tabla | Registros nuevos | Descripción |
|---|---|---|
| `clientes` | +12 | Variedad: RUC/cédula, con y sin crédito (0–90 días) |
| `facturas` | 7 | 6 activas (efectivo/crédito/datafast/transferencia) + 1 anulada |
| `factura_detalles` | 7 | Un detalle por factura |
| `factura_pagos` | 7 | Una forma de pago por factura |
| `cuentas_cobrar` | 2 | Facturas crédito → saldo pendiente $5,842 |
| `notas_credito` | 1 | Devolución total sobre primera factura activa |
| `nota_credito_detalles` | 1 | Detalle copiado del detalle de la factura original |
| `prefacturas` | 2 | Con 50% abonado cada una |
| `prefactura_abonos` | 2 | Abono efectivo parcial |
| `datafast_liquidaciones` | +1 | Con retención IR > 0 (lote pre-existente id=3) |
| `cheques` | 1 | Estado=anulado (validación C-02) |
| `anticipos_proveedores` | +1 | Estado=cruzado, saldo=0 |
| `importaciones` | +1 | Estado=liquidada, costo_total=$15,050 |
| `taller_tipos_equipo` | 5 | Consola, Amplificador, Procesador, Controlador, Subwoofer |
| `taller_equipos` | 5 | Allen&Heath SQ-5, Crown XLS2502, dbx 231S, Chauvet Obey40, JBL SRX818SP |
| `taller_ingresos` | 4 | Un ingreso por equipo |
| `taller_ordenes_trabajo` | 4 | Un OT por estado: diagnostico/aprobado/en_reparacion/facturado |
| `taller_diagnosticos` | 4 | Uno por OT, con aprobación del cliente donde corresponde |
| `taller_ot_repuestos` | 4 | Transistor IRFP250 reservado (stock_actual=50) |
| `asientos_contables` | +6 | Uno por factura activa (doc_tipo=FAC) |
| `productos` (stock_minimo) | 3 actualizados | stock_minimo > stock_actual → alertas críticas |

---

## Tests de integración — Resultados

| # | Flujo | Resultado | Detalle |
|---|---|---|---|
| T-01 | Factura → Asiento contable | ✅ | 6 facturas activas · todas con asiento (doc_tipo=FAC) |
| T-02 | Asientos balanceados (debe=haber) | ✅ | 48 asientos revisados · todos balanceados |
| T-03 | Factura crédito → CxC con aging | ✅ | 2 facturas crédito · CxC OK · saldo pendiente=$5,842 |
| T-04 | Nota de Crédito → vinculada a factura + detalles | ✅ | 1 NC · factura existe · detalles presentes |
| T-05 | Taller: OT → todos los estados + repuestos reservados | ✅ | 4 OTs (diagnostico/aprobado/en_reparacion/facturado) · 4 repuestos reservados |
| T-06 | Importación liquidada + anticipo cruzado | ✅ | 5 importaciones liquidadas · 2 anticipos cruzados |
| T-07 | Compras con retención → CxP registradas | ✅ | 5 CxP total · 1 pendiente · saldo=$45 (datos pre-existentes) |
| T-08 | Datafast lote liquidado → liquidación con retención IR | ✅ | 3 lotes liquidados · 1 liquidación con IR>0 |
| T-09 | Dashboard con datos reales (no ceros) | ✅ | ventas_mes=$4,071 · CxC=$5,842 · stock_crítico=9 · asientos=48 |
| T-10 | Secuenciales auto-creados por SecuencialService | ✅ | FAC→8 · NC→2 (auto-creados al primer uso) |

---

## Conflictos de merge resueltos

### 1. `resources/js/types/index.ts`
- **Problema:** Dev2 (`LiquidacionCalculo`) y Dev1 (`TallerIngreso`) ambos agregaron interfaces al final del archivo. Git dejó el único `}` de cierre fuera del bloque de conflicto.
- **Solución:** Conservar ambas interfaces con `}` explícito para cada una.

### 2. `routes/web.php`
- **Problema:** Ambas ramas agregaron `use` imports en la misma zona del archivo.
- **Solución:** Conservar los 9 imports: 4 de Dev2 (RRHH, Manuales, Reportes) + 5 de Dev1 (Taller controllers).

### 3. `resources/js/Components/shared/Sidebar.tsx`
- **Zona A (Compras):** Conservar versión Dev2 con `Devoluciones` + `Importaciones`.
- **Zona B (RRHH):** Conservar versión Dev2 con los 6 ítems incluyendo `Préstamos/Anticipos` y `Liquidaciones`.

---

## Bugs encontrados durante integración

| # | Descripción | Estado |
|---|---|---|
| B-01 | Migración `create_liquidaciones_table` faltaba — causaba `SQLSTATE[42P01]` en `/rrhh/liquidaciones` | ✅ Corregido: creada `2026_07_06_000002_create_liquidaciones_table.php` |
| B-02 | `taller_ingresos` no tiene columna `updated_at` — Eloquent timestamps causaría error en ORM | ✅ Corregido (2026-07-06): `TallerIngreso` ya tenía `const UPDATED_AT = null` — Laravel omite `updated_at` en CREATE/UPDATE/SAVE. Verificado con tests tinker: create/update/save/query-builder sin errores. |
| B-03 | `taller_equipos` no tiene `empresa_id` — acceso cruzado entre empresas en `show()` methods | ✅ Corregido (2026-07-06): Ningún query filtra `empresa_id` directo en `taller_equipos` (los listados index ya filtran vía `taller_ingresos.empresa_id`). Agregado `abort_if(empresa_id mismatch, 403)` en todos los métodos con route model binding: `IngresoController::show`, `OrdenTrabajoController::show/cambiarEstado`, `DiagnosticoController::create/store/aprobar`, `LiquidacionController::show/liquidar/agregarRepuesto`. |
| B-04 | `datafast_lotes` pre-existentes (id=1,2) sin registro en `datafast_liquidaciones` — inconsistencia de datos legacy | ⚠️ Data legacy — no bloquea nuevos flujos |
| B-05 | **[CRÍTICO] Liquidaciones wizard completamente bloqueado** — 4 bugs encadenados que impedían avanzar de paso 1 a paso 2 | ✅ Corregido (2026-07-06): commits `1d22283`, `0b7790f`, `2469caf`, `91d1b61` |

---

## B-05 — Detalle: Liquidaciones wizard (bugs encadenados)

### Causa raíz: CSRF 419 en `bootstrap.js`
`resources/js/bootstrap.js` solo configuraba el header `X-Requested-With`. En Laravel 12 la cookie `XSRF-TOKEN` viaja encriptada; axios la enviaba como `X-XSRF-TOKEN` pero el servidor la rechazaba. El token plano del `<meta name="csrf-token">` no se leía.

**Fix (commit `91d1b61`):**
```js
const csrfMeta = document.head.querySelector('meta[name="csrf-token"]');
if (csrfMeta) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfMeta.getAttribute('content');
}
```

**Alcance:** Solo Liquidaciones usa `window.axios.post()` directamente. Todos los demás módulos usan el `router.*` de Inertia (que maneja CSRF internamente) o `fetch()` con `X-CSRF-TOKEN` hardcoded en cada llamada — **ningún otro módulo fue afectado**.

### Bugs secundarios destapados en el proceso de diagnóstico

| Orden | Síntoma | Causa | Fix |
|---|---|---|---|
| 1 | `"All Inertia requests must receive a valid Inertia response"` | `calcular()` y `guardarBorrador()` usaban `router.post()` (espera respuesta Inertia) pero el backend devuelve `response()->json()` | Convertir ambas funciones a `window.axios.post()` (`1d22283`) |
| 2 | Botón "Calculando..." atascado indefinidamente | Cadena `.then().catch().finally()` — si la llamada lanza síncronamente, `.finally()` no corre | Convertir a `async/await` con `try/catch/finally` — garantiza que `finally` corre siempre (`0b7790f`) |
| 3 | Frontend mostraba "Error al calcular. Verifique los datos." sin mensaje específico | Respuesta HTML 419 → `err.response.data` es string; acceder `.message` sobre string = `undefined` | Backend: `try/catch` retorna JSON siempre. Frontend: verificar `typeof raw === 'object'` antes de leer propiedades (`2469caf`) |
| 4 | 419 CSRF Mismatch (causa de todo lo anterior) | Ver arriba | Fix `bootstrap.js` (`91d1b61`) |

### Cálculos verificados post-fix (tinker)

| Colaborador | Motivo | Fecha salida | Total calculado |
|---|---|---|---|
| Colaborador ID 6 | Renuncia | 07/07/2026 | **$788.61** (vacaciones $888.61 − anticipos $100) |
| Colaborador ID 7 | Despido | 07/07/2026 | **$6,587.32** (décimos + vac + FR, sin anticipos) |
| Colaborador ID 1 | — | — | Anticipo activo: $200 — se descontaría correctamente |

---

## Estado final del sistema

```
Migraciones:    100/100 Ran  ✅
npm run build:  0 errores    ✅
php artisan:    OK           ✅
Tests:          10/10        ✅
Bugs B-02/B-03: ✅ Corregidos (2026-07-06)
Bug  B-05:      ✅ Corregido (2026-07-06) — wizard Liquidaciones operativo
```

**Rama lista para revisión final antes de push a `origin/feature/dev2-contabilidad-compras`.**
