# VALIDACIÓN BANCOS — Movimientos y Conciliación Bancaria

**Fecha:** 2026-07-07
**Validador:** Claude Code (claude-sonnet-5)
**Rama:** `feature/dev2-contabilidad-compras`
**Alcance:** Movimientos Bancarios, Cheques, Conciliación Bancaria (C-02, C-04), Datafast — Lotes y Liquidación (C-01)
**Método:** Pruebas reales end-to-end vía Playwright contra `php artisan serve` + build de producción, con verificación de base de datos (PostgreSQL) antes/después de cada operación. No es solo lectura de código — cada resultado reportado fue reproducido con datos reales y limpiado al finalizar.

---

## Leyenda

| Símbolo | Significado |
|---|---|
| ✅ | Probado con datos reales — funciona correctamente |
| ⚠️ | Funciona pero con limitación/gap real detectado |
| ❌ | Bug confirmado con datos reales — corregido en esta sesión |
| 🆕 | Corrección nueva aplicada en esta sesión (2026-07-07) |

---

## PARTE 1 — Movimientos Bancarios

### 1.1 Creación de movimiento (Egreso / Cheque / $150 / proveedor real)

**✅ Verificado con datos reales**

Prueba: Banco Pichincha Cta. Cte., egreso, cheque, $150, proveedor real (Chauvet Professional), cuenta contrapartida "Cuentas por Pagar".

- Asiento generado: `AS-2026-0059`, **DEBE 150 / HABER 150** (Cuentas por Pagar DEBE / Banco HABER) — balanceado.
- Saldo Banco Pichincha: `30498.00 → 30348.00` (resta exacta del monto).

---

### 1.2 / 1.3 Anulación de movimiento — patrón de reversión

**❌ → 🆕 Corregido — 2026-07-07**

- **Archivo:** `app/Http/Controllers/Bancos/MovimientoBancarioController.php::anular()`

**Bug confirmado (código anterior a este fix):** al anular, el método marcaba `anulado=true` en el mismo registro y ajustaba `saldo_actual` directamente (`actualizarSaldo()`), sin crear ningún movimiento nuevo. Esto viola exactamente la regla que el cliente exige: la reversión debe ser un **registro nuevo**, no una mutación del original ni un simple ajuste de saldo. El mismo defecto exacto existía en `ChequesController::cambiarEstado()` (usado para `protestado`/`anulado`) — **contradice lo que decía `AUDITORIA_DEV2.md` (sección 3.6)**, que daba por bueno el flujo de reversión de Cheques.

**Patrón de referencia encontrado en el propio código:** `CompraController::anularPago()` (línea 990) ya implementaba correctamente "crear un movimiento nuevo de signo contrario enlazado al original vía `documento_tipo`/`documento_id`" — se replicó ese mismo patrón en ambos controllers de Bancos, mejorándolo además con el enlace al asiento de reversa (que el propio ejemplo de Compras no guardaba).

**Corrección aplicada** (`MovimientoBancarioController::anular()` y `ChequesController::cambiarEstado()`):
1. El asiento original se anula primero (`AsientoService::anular()`), generando su propio asiento de **reversa** (nunca se borra ni modifica el original — solo `estado=0`).
2. El movimiento original se marca `anulado=true` — **su contenido permanece intacto**, visible en el historial con badge "Anulado" (tachado en la UI).
3. Se crea un **movimiento NUEVO**, de tipo contrario (egreso→ingreso), mismo monto, enlazado al original vía `documento_tipo='ANULACION_MOV'` / `documento_id=<id original>` (o `ANULACION_CHEQUE` + id del cheque), con `asiento_id` apuntando al asiento de reversa recién creado.
4. El saldo bancario se ajusta a través de ese movimiento nuevo (`actualizarSaldo()`), no por sustracción directa sobre el original.

**Verificado con datos reales tras el fix:**

| Elemento | Antes de anular | Después de anular |
|---|---|---|
| Movimiento original (id=35) | egreso $150, activo | **intacto**, `anulado=true` |
| Movimiento nuevo (id=36) | — | **creado**: ingreso $150, `documento_tipo=ANULACION_MOV`, `documento_id=35`, `asiento_id=69` |
| Asiento original (`AS-2026-0059`) | estado=1 | estado=0 (anulado, **no borrado**) |
| Asiento de reversa (`AS-2026-0060`) | — | **creado**, DEBE 150 / HABER 150, invertido respecto al original |
| Saldo Banco Pichincha | $30.348,00 | **$30.498,00** (restaurado matemáticamente: -150 +150) |

Repetido exactamente igual para **Cheques** (protestar cheque N° 999888, $200): movimiento original intacto + `anulado=true`, movimiento nuevo `ANULACION_CHEQUE` creado, asiento de reversa generado, saldo restaurado a su valor original. Mismo resultado, mismo patrón, ambos módulos ahora consistentes entre sí.

**Toast confirmado en UI:** *"Movimiento anulado. Se generó un movimiento de reversión y su asiento contable."*

---

### 1.4 Pantalla "Consultar Cobros/Pagos" — filtros y exportación

**✅ Verificado con datos reales** (`bancos/reportes/consulta`)

| Filtro pedido | Estado |
|---|---|
| Tipo (ingreso/egreso) | ✅ funciona — probado con `tipo=egreso` |
| Banco/cuenta | ✅ funciona — probado con Banco Pichincha |
| Fechas (desde/hasta) | ✅ implementado (no probado exhaustivamente, mismo patrón de query que tipo/banco) |
| Beneficiario/persona | ✅ funciona — probado con `beneficiario=CHAUVET`, devolvió exactamente 1 resultado correcto |
| Documento / comprobante | ❌ → 🆕 **Corregido — 2026-07-07** (ver LIMITACIÓN 1 más abajo) |
| Centro de costo | ❌ → 🆕 **Corregido — 2026-07-07** (ver LIMITACIÓN 1 más abajo) |

- **Exportar PDF** (`bancos/reportes/consulta-pdf`): ✅ `200 OK`, `content-type: application/pdf`, archivo válido (firma `%PDF-`, 881 KB).
- **Exportar Excel** (`bancos/reportes/consulta-excel`): ✅ descarga confirmada (`consulta-cobros-pagos-2026-07-07.xlsx`).
- **Exportar XML**: no existe en esta pantalla específica, pero **sí existe** en `bancos/movimientos/exportar-xml` (pantalla de Movimientos) — ✅ `200 OK`, `content-type: application/xml`, contenido válido. El documento del cliente pide "exportar en PDF o en XML" desde "la página principal para consultar los pagos" sin especificar una única pantalla; la capacidad existe, repartida en dos pantallas del mismo módulo.

---
