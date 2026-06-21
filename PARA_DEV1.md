# Handoff Dev2 → Dev1

**Rama:** `feature/dev2-contabilidad-compras`  
**Fecha:** 2026-06-21

---

## Qué contiene esta rama

Todo el trabajo de Dev2: Contabilidad, Compras, Inventario, Bancos, Personas y RRHH (base).

### Módulos completados

| Módulo | Estado |
|--------|--------|
| Contabilidad / Plan de Cuentas | ✅ |
| Contabilidad / Asientos contables | ✅ |
| Contabilidad / Parámetros contables | ✅ |
| Contabilidad / Reportes (libro diario, mayor) | ✅ |
| Compras / Facturas de compra | ✅ |
| Compras / Etiquetas de productos | ✅ |
| Compras / Proveedores | ✅ |
| Compras / Cuentas por Pagar | ✅ |
| Compras / Anticipos proveedores | ✅ |
| Compras / Importaciones | ✅ |
| Inventario / Productos | ✅ |
| Inventario / Kárdex | ✅ |
| Inventario / Traslados | ✅ |
| Inventario / Activos Fijos | ✅ |
| Bancos / Catálogo bancos y cajas | ✅ |
| Bancos / Movimientos bancarios | ✅ |
| Bancos / Cajas / cierre | ✅ |
| Bancos / Datafast | ✅ |
| Bancos / Conciliaciones | ✅ |
| Bancos / Cheques | ✅ |
| Bancos / Reportes | ✅ |
| Personas / Clientes | ✅ |
| Personas / Proveedores | ✅ |
| Personas / Transportistas | ✅ |
| RRHH / Colaboradores (base) | ✅ |
| RRHH / Asistencia | ✅ |
| RRHH / Horas extras | ✅ |

---

## CRÍTICO: Nombres reales de columnas en BD

El `altamira_schema.sql` tiene nombres **diferentes** a los de la BD de producción real.  
Siempre verificar con `information_schema.columns` o tinker antes de usar un nombre de columna.

### `inventario_movimientos` — columnas reales

| Columna real | Lo que dice el schema (INCORRECTO) |
|---|---|
| `tipo` | `tipo_movimiento` |
| `doc_tipo` | `documento_tipo` |
| `doc_id` | `documento_id` |
| `bodega_id` | `bodega_destino_id` |
| `notas` | `observacion` |
| `created_at` | `fecha` / `hora` |
| **NO existe** | `documento_numero` |

### `inventario_saldos` — columnas reales

| Columna real | Lo que dice el schema (INCORRECTO) |
|---|---|
| `stock_actual` | `cantidad` |

---

## Base de datos

El archivo `database/altamira_dump_dev2.sql` es el dump completo de la BD de producción al 2026-06-21.

Para restaurar en local:
```bash
createdb -U postgres altamira  # si no existe
psql -U postgres -d altamira -f database/altamira_dump_dev2.sql
```

---

## Anulación de compras — flujo implementado

El botón Anular en Facturas de Compra y en Cuentas por Pagar implementa 3 escenarios:

- **Escenario A** — Sin pago y sin inventario afectado (o factura pendiente): anulación simple.
- **Escenario B** — Con pago registrado: revierte movimiento bancario + asiento.
- **Escenario C** — Productos ya vendidos después de esta compra: bloquea la anulación con lista de productos afectados.

El endpoint `GET /compras/facturas/{id}/verificar-anulacion` determina el escenario antes de mostrar el modal.

---

## InventarioService — firma de métodos actualizada

```php
// FIRMA ACTUAL (usar esta)
public function ingresarStock(
    int $productoId, int $bodegaId, float $cantidad, float $costoUnitario,
    string $docTipo, int $docId,
    ?string $docNumero = null, ?string $observacion = null
): void

public function egresarStock(
    int $productoId, int $bodegaId, float $cantidad,
    string $docTipo, int $docId,
    ?string $docNumero = null, ?string $observacion = null
): void
```

---

## Pendiente para Dev1 (Ventas)

- Facturas SRI (serie 001-XXX-XXXXXXX)
- Proformas / Pre-facturas
- Notas de crédito
- Retenciones
- Guías de remisión
- Cuentas por cobrar
- Aprobaciones de descuento

Las rutas de Ventas ya están declaradas en `routes/web.php` — solo falta implementar los controllers y páginas React en `resources/js/Pages/Ventas/`.

---

## Variables de entorno requeridas

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=altamira
DB_USERNAME=postgres
DB_PASSWORD=postgres123

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
APP_URL=http://127.0.0.1:8000
```

---

## Etiquetas de productos (Compras)

### Comportamiento
- **Generar Etiquetas**: modal con desglose por producto, correlativo independiente por prefijo de categoría. Correlativos calculados como `DESDE = max(etiqueta_num) + 1` en `etiquetas_productos`.
- **Reimprimir Etiquetas**: checklist agrupado por producto, selección individual de etiquetas dañadas/extraviadas.
- **Formato**: térmico 90×35 mm, una etiqueta por página, código de barras CODE128.
- **Botón oculto**: si la factura solo tiene líneas de gasto/servicio (`producto_id = NULL`), los botones Etiquetas y Reimprimir se ocultan — no es un error, es correcto.

### Campo en la query del index
`CompraController::index()` usa `withCount` con alias `tiene_productos_codificados` (0 = sin productos, >0 = tiene):
```php
->withCount(['detalles as tiene_productos_codificados' => fn($q) => $q->whereNotNull('producto_id')])
```

---

## Comando reset de datos de prueba

Para revertir todas las facturas `activa` a `pendiente` (uso exclusivo en desarrollo):

```bash
php artisan altamira:reset-facturas-pendiente
php artisan altamira:reset-facturas-pendiente --force  # sin confirmación
```

Qué hace (dentro de una transacción):
1. Elimina movimientos de inventario de esas compras
2. Recalcula `inventario_saldos.stock_actual`
3. Elimina `cuentas_pagar` asociadas
4. Nula `compras.asiento_id` y elimina los asientos contables (cascade a `asiento_detalles`)
5. Revierte `compras.estado = 'pendiente'`
6. `TRUNCATE etiquetas_productos`

**NO exponer como ruta web ni usar en producción.**

---

## Credenciales de prueba

```
Admin: admin@altamira.com / Altamira2026*
Vendedor: vendedor@altamira.com / Vendedor2026*
PIN aprobación: 1234
```
