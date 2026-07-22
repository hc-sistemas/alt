# Validación de Volumen — ERP Altamira

**Fecha:** 2026-07-21
**Objetivo:** generar datos ficticios pero realistas a alto volumen (simulando ~2.5 años de operación real, 2024-01-01 a 2026-07-21) y validar que el sistema completo (Dev 1 + Dev 2) se comporta correctamente a esa escala — tanto en integridad de datos como en rendimiento.

**Herramienta:** `php artisan altamira:seedear-volumen` (nuevo comando, `app/Console/Commands/SeedearVolumen.php`) — **solo para entornos de desarrollo/pruebas**, no ejecutar contra producción. Genera datos vía `DB::table()->insert()` en bloques (no Eloquent) para poder producir decenas de miles de registros en tiempo razonable, siempre con partida doble real (nunca asientos sueltos).

---

## FASE 1 — Volumen generado

| Tabla | Cantidad |
|---|---:|
| clientes | 865 |
| proveedores | 233 |
| productos | 587 |
| colaboradores | 35 |
| compras | 2,232 |
| compra_detalles | 5,604 |
| facturas | 5,083 |
| factura_detalles | 13,382 |
| factura_pagos | 4,895 |
| taller_ordenes_trabajo | 1,604 |
| movimientos_bancarios | 3,241 |
| nominas (roles mensuales) | 34 |
| nomina_detalles | 615 |
| prestamos_empleados | 50 |
| inventario_movimientos | 17,526 |
| **asientos_contables** | **11,170** |
| **asiento_detalles** | **28,664** |
| cuentas_cobrar | 762 |
| cuentas_pagar | 1,173 |

Todos los asientos contables se generaron a partir de transacciones reales simuladas (compras, facturas, nómina, taller, movimientos bancarios) — nunca como asientos sueltos. Fechas distribuidas de forma realista en el rango 2024-01-01 a 2026-07-21. Facturas incluyen mezcla de estados: pagadas, crédito activo, crédito vencido en distintos rangos de mora, y ~5% anuladas (sin asiento ni movimiento de inventario, como corresponde).

Durante la construcción y depuración del seeder se encontraron y corrigieron 5 errores antes de la corrida final a escala completa:
1. `inventario_movimientos` no tiene columna `fecha` (solo `created_at`).
2. `inventario_movimientos.usuario_id` es NOT NULL.
3. `asientos_contables.numero` es VARCHAR(20) — el formato inicial lo desbordaba.
4. `nominas.generado_por/procesado_por/pagado_por` son NOT NULL.
5. **Crítico:** el asiento de nómina no descontaba `descuento_atrasos` del lado DEBE (Sueldos), causando un descuadre de $210.73 en el Balance de Comprobación. Corregido netando los atrasos contra el gasto de sueldos.

---

## FASE 2 — Integridad a escala

| Verificación | Resultado |
|---|---|
| Balance de Comprobación (Debe = Haber) | ✅ **$85,582,517.3607 = $85,582,517.3607** — cuadrado exacto, sin descuadre por redondeo a volumen |
| Códigos duplicados en `plan_cuentas` | ✅ 0 duplicados (206 cuentas, todas únicas) |
| Asientos huérfanos (sin transacción de origen real) generados por el seeder | ✅ 0 |
| Transacciones del seeder sin su asiento correspondiente | ✅ 0 |
| CxC — dispersión de mora | ✅ 762 cuentas por cobrar con antigüedad distribuida en varios rangos (al día, 1-30, 31-60, 61-90, +90 días) |

---

## FASE 3 — Rendimiento (medido, no estimado)

Metodología: `DB::enableQueryLog()` + `microtime(true)` alrededor de cada método de controller invocado directamente (usuario autenticado, empresa activa en sesión). Umbral de alerta: ⚠️ 1-3 s, ⚠️ LENTO >3 s.

| Pantalla / Reporte | Antes | Diagnóstico | Después |
|---|---:|---|---:|
| Listado de Facturas (paginado) | 351.3 ms / 4 queries ✅ | — | 23.3 ms / 4 queries ✅ |
| **Balance de Comprobación** | **4,914.9 ms / 304 queries ⚠️ LENTO** | N+1: 2 queries `sum()` por cada una de ~150 cuentas | **530.2 ms / 3 queries ✅** |
| **Estado de Resultados** | **702.1 ms / 102 queries ⚠️** | mismo patrón N+1 | **273.8 ms / 3 queries ✅** |
| **Balance General** | **889.9 ms / 204 queries ⚠️** | mismo patrón N+1 | **272.9 ms / 3 queries ✅** |
| ATS (XML, julio 2026) | 229.7 ms / 5 queries ✅ | — | 78.1 ms / 5 queries ✅ |
| Formulario 103 | 646.3 ms / 2 queries ✅ | — | 583.9 ms / 2 queries ✅ |
| Formulario 104 | 525.8 ms / 5 queries ✅ | — | 564.2 ms / 5 queries ✅ |
| CxC — antigüedad de saldos (paginado) | 20.5 ms / 9 queries ✅ | — | 32.4 ms / 9 queries ✅ |
| **Conciliación Bancaria — auto-match** (523 partidas: 291 sistema + 232 banco) | **1,476.1 ms / 704 queries ⚠️** | `DB::transaction()` individual + 2-3 `UPDATE` por cada uno de los 232 cruces encontrados | **928 ms / 11 queries ✅** |
| Dashboard | 4-8 ms / 1 query ✅ | Widgets sin conectar a datos reales (`stats` hardcodeado en 0, pendiente de Fase 2 del roadmap) — no es un problema de rendimiento, es funcionalidad no implementada aún | — |

**Nota Dashboard:** no requiere optimización porque no está consultando datos reales todavía (`DashboardController::index()` devuelve `ventas_hoy/ventas_ayer/meta_mes/ventas_mes` en 0 fijo). Queda fuera del alcance de esta validación de rendimiento; es trabajo pendiente de Fase 2 ya documentado en `CLAUDE.md`.

---

## FASE 4 — Correcciones aplicadas

### 1. Fix N+1 en reportes contables (`app/Http/Controllers/Contabilidad/ReporteContableController.php`)
`balanceComprobacion()`, `balanceGeneral()` y `estadoResultados()` iteraban sobre cada cuenta del plan de cuentas ejecutando 2 `sum()` independientes (con `whereHas`) por cuenta. Reemplazado por una sola consulta `JOIN asientos_contables` + `GROUP BY cuenta_id` que trae todas las sumas de una vez, indexada en memoria por `cuenta_id` para el mapeo final. Reduce ~300 queries a 2-3 por reporte.

### 2. Batch de actualizaciones en Conciliación Bancaria (`app/Http/Controllers/Bancos/ConciliacionController.php`)
`autoMatchPartidas()` hacía un `DB::transaction()` con 2-3 `UPDATE` individuales por cada partida cruzada. Reemplazado por 3 `UPDATE ... WHERE id IN (...)` al final del proceso (misma lógica de emparejamiento, ningún cambio de comportamiento — solo se difiere la escritura a BD).

### 3. Índices de base de datos faltantes (2 migraciones idempotentes nuevas)
- `2026_07_21_201753_add_indices_rendimiento_volumen.php`:
  - `asiento_detalles.cuenta_id`, `asiento_detalles.asiento_id` — **sin índice previo**, causaba full table scan en cada consulta agregada (28,664 filas). Es la causa raíz principal de la lentitud del Balance de Comprobación.
  - `asientos_contables.estado`
  - `facturas.cliente_id`, `facturas.fecha_emision`, `facturas.estado`
  - `compras.proveedor_id`, `compras.fecha_emision`, `compras.estado`
  - `cuentas_cobrar.cliente_id`, `cuentas_cobrar.fecha_vencimiento`, `cuentas_cobrar.estado`
  - `cuentas_pagar.proveedor_id`, `cuentas_pagar.fecha_vencimiento`, `cuentas_pagar.estado`
  - `taller_ordenes_trabajo.tecnico_id`, `taller_ordenes_trabajo.fecha_inicio`, `taller_ordenes_trabajo.estado`
- `2026_07_21_201851_add_indice_partidas_transito_conciliacion.php`:
  - `partidas_transito.conciliacion_id`

Ambas migraciones son idempotentes (verifican existencia del índice vía `pg_indexes` antes de crearlo, siguiendo el patrón `Schema::hasTable/hasColumn` del resto del proyecto) y tienen `down()` funcional.

### 4. Paginación real — verificada, sin cambios necesarios
Confirmado `->paginate()` en los listados de Facturas, Compras y Cuentas por Cobrar (no cargan el total de filas al frontend). El único `->get()` sin paginar detectado (`CompraController::pdf()`) es un export de PDF filtrado por fecha/estado — comportamiento esperado para un reporte, no una regresión.

**Sin cambios de frontend** en esta fase — no aplica `npm run build`.

---

## FASE 5 — Datos de prueba

**Decisión (consultada con el usuario):** se **mantienen** los datos de volumen generados en la base de datos local para poder seguir usándolos en pruebas de carga/estrés posteriores. El comando `altamira:seedear-volumen` es una herramienta de desarrollo — no debe ejecutarse contra producción.

---

## Resumen ejecutivo

- Volumen generado y validado: ~30,000 registros nuevos en 15+ tablas relacionadas, con partida doble real y balance exacto a $85.58M.
- 0 problemas de integridad atribuibles a los datos generados.
- 1 cuello de botella real encontrado (Balance de Comprobación, Estado de Resultados, Balance General — N+1 idéntico en los tres) y corregido: **~90% de reducción en tiempo de carga**, de hasta 4.9 s a bajo 0.6 s.
- 1 mejora adicional aplicada (Conciliación Bancaria auto-match): de 1.5 s / 704 queries a 0.9 s / 11 queries.
- 9 índices nuevos agregados vía migraciones idempotentes, ninguno rompe el schema existente.
- Todo el trabajo de esta fase (seeder + correcciones) está commiteado localmente; **no se ha hecho push**, pendiente de autorización.
