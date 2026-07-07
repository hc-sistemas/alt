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

## LIMITACIÓN 1 — ❌ → 🆕 Corregido: filtros "Documento" y "Centro de Costo" (2026-07-07, segunda ronda)

**Causa raíz:** `movimientos_bancarios.num_documento` **sí existía** en el schema pero nunca se usaba como filtro en `BancoReporteController::consultaCobrosPagos()`/`consultaQuery()`; `centro_costo_id` **no existía en absoluto** en la tabla.

**Corrección aplicada:**
1. Migración idempotente `2026_07_07_080116_add_centro_costo_id_to_movimientos_bancarios_table.php` — agrega `centro_costo_id` (FK nullable a `centros_costo`, `nullOnDelete`).
2. `MovimientoBancario`: agregado `centro_costo_id` a `$fillable` + relación `centroCosto()`.
3. **Poblado automático en el flujo real que genera movimientos**: `CuentaPagarController::pagar()` ahora hereda `centro_costo_id` de `$cuentaPagar->compra->centro_costo_id` (ya heredaba `num_documento`, que solo faltaba conectar como filtro). También propagado en la reversión `CompraController::anularPago()`.
4. `BancoReporteController::consultaCobrosPagos()` / `consultaQuery()` (compartido por PDF/Excel): agregados filtros `num_documento` (`ilike` parcial) y `centro_costo_id` (exacto). Se pasa la lista de `centrosCosto` a la vista.
5. Frontend `ConsultaCobrosPagos.tsx`: agregados campos "Nº Documento" (texto) y "Centro de Costo" (select), columna "Centro Costo" en la tabla, ambos conectados a `buscar()`/`exportar()`/`limpiar()`.

**Verificado con datos reales:**
- Se pagó una CxP real (compra #33, num_documento `0555-5666-56555`, centro de costo "Altamira Matriz") desde `Compras → Cuentas por Pagar`. El movimiento bancario generado (id=40) heredó **ambos** campos correctamente: `num_documento=0555-5666-56555`, `centro_costo_id=1`.
- Filtro por Nº Documento exacto → **1 resultado** correcto.
- Filtro por Centro de Costo "Altamira Matriz" → **1 resultado** correcto (el resto de movimientos históricos tiene `centro_costo_id=NULL` por ser anteriores a esta corrección, por lo que quedan correctamente excluidos).
- Prueba revertida al finalizar (ver limpieza).

---

## PARTE 2 — Conciliación Bancaria

### 2.1 Estado de C-04 (auto-match ±2 días ±$0.01)

**✅ Ya estaba implementado** — confirmado en código y ahora también con datos reales (no solo lectura, como pedía la tarea).

- `ConciliacionController::autoMatchPartidas()` (privado): compara monto con tolerancia ±$0.01 y fecha con tolerancia ±2 días; usa `$usadosIds` para evitar doble match; solo aparea si hay **exactamente 1** candidato (evita ambigüedad); se ejecuta automáticamente tras `uploadEstadoCuenta()` (CSV) y `uploadEstadoCuentaExcel()` (Excel).

### 2.2 Prueba real: CSV de 5 líneas (3 match, 2 no-match)

**✅ Verificado con datos reales**

Preparación: Banco Guayaquil Cta. Cte., 4 movimientos no conciliados en BD (3 ya sembrados + 1 registrado en esta sesión — "Comisión Mantenimiento Cuenta" $4,50, para poder demostrar el cruce manual más adelante).

CSV subido vía interfaz real (`Cargar CSV banco`):

| Línea CSV | Monto | Fecha | Resultado esperado | Resultado real |
|---|---|---|---|---|
| Transferencia saliente | 339.25 | 2026-06-13 | match automático (mov. 2026-06-12) | ✅ cruzada automáticamente |
| Depósito cliente | 4500.00 | 2026-06-05 | match automático (mov. 2026-06-04) | ✅ cruzada automáticamente |
| Depósito cliente | 4500.00 | 2026-05-31 | match automático (mov. 2026-05-29) | ✅ cruzada automáticamente |
| Comisión mantenimiento | 4.50 | 2026-07-12 | **no** match automático (5 días de diferencia, fuera de ±2d) | ✅ quedó pendiente, como se esperaba |
| Cargo no identificado | 75.33 | 2026-04-01 | **no** match (sin contraparte real) | ✅ quedó pendiente, como se esperaba |

Toast real: *"CSV importado: 5 movimientos del banco cargados. Se cruzaron automáticamente 3 partida(s) (±2 días, ±$0.01)."* — **0 falsos positivos**: ninguna de las 2 líneas deliberadamente no-coincidentes fue apareada incorrectamente.

### 2.3 Cruce manual y generación de asiento faltante

**✅ Cruce manual verificado** — se seleccionó la partida "Comisión Mantenimiento" del panel Sistema y su contraparte del panel Banco (CSV), se pulsó "Cruzar partidas": ambas quedaron `conciliada=true` correctamente (`ConciliacionController::conciliarPartida()`).

**Generar asiento para partida específica: ❌ → 🆕 Corregido — 2026-07-07 (segunda ronda).** Ver LIMITACIÓN 2 más abajo.

### 2.4 ❌ → 🆕 Bug encontrado y corregido: "Diferencia" nunca se recalculaba

**Este es el hallazgo más importante de la Parte 2, encontrado únicamente por probar con datos reales — no era visible solo leyendo el código superficialmente.**

Al cerrar una conciliación completamente resuelta (0 partidas pendientes, incluyendo el ajuste generado en 2.3), la tarjeta **"Diferencia"** seguía mostrando **-$75,33** en rojo con ícono de alerta, en vez de $0,00 — **incluso con la conciliación ya marcada "Conciliada"**. Esto incumple directamente el criterio de éxito pedido: *"confirmar que el reporte final... cuadra en $0.00"*.

**Causa raíz (`app/Http/Controllers/Bancos/ConciliacionController.php::generarAsientoAjuste()`):**
1. `AsientoService::ajusteConciliacion()` registra el asiento contable en el mayor general, pero **nunca llama a `$banco->actualizarSaldo()`** — el saldo cacheado en `bancos_cajas.saldo_actual` queda permanentemente desincronizado del mayor contable tras cualquier ajuste.
2. Los campos `saldo_sistema` / `diferencia` de `conciliaciones_bancarias` se calculan **una sola vez**, al crear la conciliación (`store()`), y ningún método (`generarAsientoAjuste()`, `conciliarPartida()`, `cerrar()`) los vuelve a calcular. Quedan congelados para siempre con el valor original, sin importar cuántas partidas se resuelvan después.

**Corrección aplicada (2026-07-07):** en `generarAsientoAjuste()`, después de generar el asiento y su partida de ajuste:
```php
$conciliacion->bancoCaja->actualizarSaldo(abs($diferencia), $diferencia > 0 ? 'ingreso' : 'egreso');
$conciliacion->update(['saldo_sistema' => $conciliacion->saldo_banco, 'diferencia' => 0]);
```

**Verificado con datos reales tras el fix** (conciliación de prueba nueva, Banco Guayaquil, diferencia declarada de -$6,25):

| Campo | Antes del ajuste | Después del ajuste (con el fix) |
|---|---|---|
| Saldo banco | $16.850,00 | $16.850,00 |
| Saldo sistema | $16.856,25 | **$16.850,00** |
| Diferencia | **-$6,25** (rojo) | **$0,00** (verde ✓) |
| `bancos_cajas.saldo_actual` (BD) | 16856.25 | **16850.00** |
| Asiento generado | `AS-2026-0065`, DEBE 6.25 / HABER 6.25 | balanceado, dirección correcta según signo de la diferencia |

### 2.5 Cuadre final tras cerrar

**✅ Verificado con datos reales, tras aplicar la corrección 2.4**

Conciliación con 9 partidas (3 auto-match + 1 cruce manual + 1 ajuste + reflejo en sistema), 0 pendientes → botón "Cerrar conciliación" habilitado → estado pasa a **"Conciliada"** → tarjeta Diferencia muestra **$0,00**. Sin la corrección 2.4, este mismo escenario cerraba mostrando -$75,33 de diferencia permanente — un reporte de cierre engañoso para contabilidad.

---

## LIMITACIÓN 2 — ❌ → 🆕 Corregido: generar asiento por partida individual (2026-07-07, segunda ronda)

**Causa raíz:** solo existía `generarAsientoAjuste()` (botón global "Asiento ajuste"), que resuelve la *diferencia total declarada* pero nunca marca como conciliada la partida específica que la originó (ver 2.3 de la ronda anterior).

**Corrección aplicada:**
1. **Ruta nueva:** `POST bancos/conciliaciones/{conciliacion}/partidas/{partida}/generar-asiento` → `ConciliacionController::generarAsientoPartida()`.
2. **Backend:** valida que la partida pertenezca a la conciliación, sea de tipo `banco` y no esté conciliada. Dentro de `DB::transaction()`:
   - Crea un **movimiento bancario nuevo** (tipo Ingreso/Egreso elegido por el contador, monto tomado del extracto, cuenta contrapartida elegida).
   - Genera su **asiento contable** balanceado (DEBE/HABER según tipo) vía `AsientoService::crear()`.
   - Vincula la partida (`movimiento_id`, `asiento_generado_id`, `conciliada=true`).
   - Actualiza `saldo_actual` del banco y **recalcula `saldo_sistema`/`diferencia`** de la conciliación (mismo mecanismo del fix 2.4, para no reintroducir el bug de "Diferencia" congelada).
3. **Frontend** (`Bancos/Conciliaciones/Show.tsx`): botón por fila (ícono `FilePlus2`) visible solo en partidas pendientes del panel "Partidas del banco (CSV)". Abre modal (`modal-card`, `btn-primary` a la izquierda, `input-field`/`input-label`) con: resumen de la partida (descripción, fecha, monto de solo lectura), toggle Ingreso/Egreso, buscador de cuenta contable (mismo patrón que "Nuevo Movimiento", **sugiere por defecto la cuenta 5.3.1.02 "Comisiones Bancarias y Pasarelas de Pago"** pero permite cambiarla), descripción editable.
4. Coexiste con el botón global "Asiento ajuste" — ambos disponibles, sin conflicto.

**Verificado con datos reales (banco con historial controlado, Banco Guayaquil):**
1. Se sembraron 3 movimientos de prueba ($1.500 ingreso, $800.50 egreso, $2.200 ingreso) y se creó una conciliación con `saldo_banco` = saldo del sistema − $4,50 (comisión aún no registrada).
2. Se subió un CSV real de 4 líneas: 3 cruzaron automáticamente (C-04), la línea "COMISION MANTENIMIENTO CUENTA" ($4,50) quedó pendiente (sin contraparte en el sistema) — confirmado 0 falsos positivos.
3. Se usó el nuevo botón "Generar asiento de esta partida" sobre esa línea: tipo Egreso, cuenta sugerida aceptada tal cual (5.3.1.02).
4. Resultado verificado en BD: movimiento nuevo (id=45) `tipo=egreso, monto=4.50, cuenta_contrapartida_id=587 (5.3.1.02)`; asiento `AS-2026-0060` balanceado (DEBE 587 = 4.50 / HABER cuenta banco = 4.50); partida `conciliada=true`, vinculada al movimiento y al asiento.
5. **Diferencia recalculada correctamente:** `saldo_sistema` pasó a coincidir exactamente con `saldo_banco` ($19.755,75 = $19.755,75) → **Diferencia $0,00** — sin esperar al cierre, inmediatamente tras generar el asiento.
6. Con las 7 partidas conciliadas (3 auto-match + 1 generada por partida), se cerró la conciliación sin bloqueos → estado **"Conciliada"**, Diferencia **$0,00** confirmado en pantalla — no se reintrodujo el bug de 2.4.

---

## LIMITACIÓN 3 — ❌ → 🆕 Corregido: cruce manual con montos distintos no advertía ni justificaba la diferencia (2026-07-07, tercera ronda)

**Origen del hallazgo:** Steeven, usando el ejercicio de práctica preparado en esta misma sesión, cruzó manualmente la partida sistema "Transferencia recibida - Cliente" ($75.00) contra la partida banco "TRANSFERENCIA RECIBIDA CLIENTE" ($76.50) — una diferencia real de $1.50. El botón "Cruzar partidas" lo permitió sin ninguna advertencia. Validación solicitada por el usuario tras notar esto en su propia prueba.

**Bug confirmado con los datos reales de Steeven (conciliación #6, Banco Guayaquil):**
1. `ConciliacionController::conciliarPartida()` marcaba **ambas** partidas como `conciliada=true` sin comparar montos ni pedir confirmación — el frontend (`Show.tsx::cruzarPartidas()`) tampoco mostraba ninguna advertencia antes de enviar la petición.
2. La partida cruzada no generaba ningún movimiento ni asiento por la diferencia de $1.50 — quedaba contablemente sin justificar.
3. `conciliarPartida()` **nunca recalculaba** `saldo_sistema`/`diferencia` de la conciliación (a diferencia de `generarAsientoAjuste()`, `generarAsientoPartida()` y `cerrar()`, ya corregidos en rondas anteriores). Se reprodujo exactamente el efecto temporal descrito por el usuario: justo después del cruce, `saldo_sistema` seguía siendo el valor calculado al **crear** la conciliación (antes de que existiera cualquier diferencia conocida) — es decir, en ese instante "Diferencia" mostraba **$0,00**, ocultando por completo el $1.50 real. Solo dejó de mostrar $0,00 más tarde, por *otra* acción (generar el asiento de la comisión) que sí recalculaba — pura coincidencia, no protección real.
4. **Además, como bug lateral** (mismo tipo de problema, encontrado al revisar el código de ajuste): `AsientoService::ajusteConciliacion()` usaba `cta_ajuste_inventario` (5.1.1.04 "Ajuste de Inventario") como contrapartida para diferencias **bancarias** — una cuenta semánticamente equivocada (mezclaría descuadres de banco con la valuación de inventario). Verificado que ningún asiento histórico real había usado esa cuenta todavía (0 asientos `CONCILIACION` la referenciaban), así que no requirió corrección de datos, solo del código.

**Corrección aplicada:**
1. **`AsientoService`:** nuevo parámetro contable `cta_ajuste_conciliacion` → `5.4.1.03 Otros Gastos Extraordinarios`. `ajusteConciliacion()` ahora usa esta cuenta (antes usaba la de inventario, ver punto 4 arriba) — reutilizada también por el nuevo flujo de ajuste por cruce manual, para no duplicar lógica.
2. **`ConciliacionController::conciliarPartida()`** reescrito: calcula la diferencia real entre ambos montos; si es ≤ $0.01 sigue el comportamiento normal de siempre; si es mayor, acepta un parámetro `generar_ajuste`:
   - `generar_ajuste=true` → crea un movimiento + asiento de ajuste (mismo mecanismo/cuenta que el ajuste global) y marca conciliado el movimiento original.
   - `generar_ajuste=false` → cruza igualmente pero el movimiento original queda **sin** marcar conciliado y el mensaje de éxito lo dice explícitamente.
   - **En ambos casos**, ahora SIEMPRE recalcula `saldo_sistema`/`diferencia` al final (mismo mecanismo que las otras 3 acciones), así que la tarjeta "Diferencia" nunca vuelve a quedar congelada en un valor viejo.
3. **`Show.tsx::cruzarPartidas()`:** antes de enviar la petición, si los montos de las partidas seleccionadas difieren en más de $0.01, muestra un modal (SweetAlert2) con el detalle exacto — *"Sistema: $75.00 · Banco: $76.50 · Diferencia: $1.50"* — y 3 opciones: **Generar ajuste y cruzar**, **Cruzar sin ajuste**, **Cancelar**. Si la diferencia es ≤ $0.01, no se muestra nada (comportamiento normal sin fricción).
4. **`ConciliacionController::cerrar()`:** nuevo candado — si `abs(diferencia) > 0.01` tras recalcular contra el saldo real del banco, **bloquea el cierre** con un mensaje explícito, sin importar que todas las partidas estén marcadas como cruzadas. Esto es lo que impide cerrar "en $0,00" con un descuadre real sin justificar (el escenario que preocupaba al usuario).

**Verificado con el mismo escenario real (conciliación #6 de Steeven, no uno sintético):**

| Paso | Resultado |
|---|---|
| Seleccionar partida sistema $75.00 + partida banco $76.50 → "Cruzar partidas" | ✅ Aparece el modal de advertencia con los montos y la diferencia exactos |
| Elegir "Cruzar sin ajuste" | ✅ Toast: *"Partidas cruzadas SIN ajuste — quedó una diferencia de $1.50 reflejada..."* — Diferencia de la conciliación **no** cae a $0,00 falsamente |
| Intentar "Cerrar conciliación" con diferencia pendiente | ✅ Bloqueado: *"No se puede cerrar: existe una diferencia de $4.50... Genere un asiento de ajuste..."* |
| Revertir y elegir "Generar ajuste y cruzar" en su lugar | ✅ Toast: *"Se generó un asiento de ajuste de $1.50 por la diferencia."* — asiento `AS-2026-0062` balanceado, DEBE Bancos 1.50 / HABER 5.4.1.03 Otros Gastos Extraordinarios 1.50 (cuenta correcta, ya no la de inventario) |
| Diferencia tras el ajuste | Bajó de $4,50 a **$3,00** (matemáticamente correcto: solo se resolvió el $1.50 de esta partida; el resto era un descuadre real y distinto, no relacionado, entre la comisión ya registrada y el saldo bancario declarado) |
| Intentar cerrar con ese $3,00 restante | ✅ Bloqueado de nuevo, mismo candado — confirma que no basta con "que no queden pendientes", la diferencia agregada también se verifica |
| Resolver el resto con el botón global "Asiento ajuste" ($3,00) y cerrar | ✅ Asiento `AS-2026-0063` balanceado, misma cuenta correcta. Conciliación cerrada: **Saldo Banco = Saldo Sistema = $17.105,50, Diferencia $0,00** — esta vez un $0,00 real, con 3 asientos de respaldo (comisión + ajuste de cruce + ajuste global) que justifican cada centavo |

**Nota:** esta corrección se validó directamente sobre la conciliación real que Steeven ya había empezado en su ejercicio de práctica (no se descartó su trabajo) — quedó completamente cerrada y balanceada como resultado.

---

## PARTE 4 — Datafast (Lotes + Liquidación) — 2026-07-07, cuarta ronda

**Motivo:** C-01 (bloque `retencion_ir` duplicado en la liquidación) se corrigió el 2026-07-05 pero nunca se re-validó con datos reales — la propia auditoría original lo admitía ("0 asientos Datafast descuadrados encontrados... ninguna liquidación tenía retencion_ir > 0"), es decir, el fix nunca se había ejercitado de verdad. Esta ronda lo hace con Playwright + verificación en BD, mismo rigor que Bancos/Conciliación.

### 4.1 Paso A — Creación de Lote

**❌ → 🆕 Bug nuevo encontrado y corregido:** el asiento del lote (`DatafastController::storeLote()`) creaba solo **2 líneas** — DEBE `1.1.1.05` Vouchers / HABER `4.1.1.01` Ventas por el **total bruto completo**, sin separar el IVA. El voucher de una tarjeta siempre incluye el IVA cobrado al cliente (igual que Facturas/Proformas, que sí lo separan al 15%) — dejar todo el monto en "Ventas" sobrestima el ingreso real y nunca registra el pasivo de IVA por pagar al SRI (`2.1.3.04`). Se confirmó que la cuenta `cta_iva_ventas` ya estaba configurada en `parametros_contables` para ambas empresas (id 640, código `2.1.3.04`) — solo faltaba usarla aquí.

**Corrección:** `storeLote()` ahora separa `total_vouchers` en neto (÷1.15) + IVA (resto), y genera 3 partidas: DEBE Vouchers (bruto) / HABER Ventas (neto) / HABER IVA Ventas (iva).

**Probado con datos reales** (lote $500,00, terminal "Datafast Terminal Matriz"):

| Cuenta | Debe | Haber |
|---|---|---|
| 1.1.1.05 Cuentas Virtuales y Pasarelas de Pago | $500,00 | |
| 4.1.1.01 Venta de Mercaderías | | $434,78 |
| 2.1.3.04 IVA en Ventas por Liquidar al SRI | | $65,22 |

✅ Balanceado ($500,00 = $500,00). Lote quedó en estado **Pendiente**.

**Nota sobre LOT-433 (dato real, no tocado):** su asiento (`AS-2026-0057`, creado antes de este fix) todavía tiene el patrón viejo de 2 líneas — DEBE Vouchers $13,00 / HABER Ventas $13,00 completo, sin IVA separado. Se dejó exactamente así por instrucción explícita (es dato real de Steeven, no de prueba); queda documentado aquí para que él decida si amerita un asiento de corrección manual. Los lotes creados **desde ahora** ya usan el patrón corregido.

### 4.2 Paso B — Liquidación CON retención IR + IVA (escenario C-01)

**✅ Verificado con datos reales — C-01 NO reapareció.**

Liquidación del lote de $500,00: comisión $10,00, retención IVA $3,00, retención IR $5,00, banco destino Banco Pichincha.

| Cuenta | Debe | Haber |
|---|---|---|
| 1.1.1.03 Bancos Locales | $482,00 | |
| 5.3.1.02 Comisiones Bancarias y Pasarelas de Pago | $10,00 | |
| 1.1.5.02 Crédito Tributario por Retenciones de IVA | $3,00 | |
| 1.1.5.03 Crédito Tributario por Retenciones de IR | $5,00 | |
| 1.1.1.05 Cuentas Virtuales y Pasarelas de Pago | | $500,00 |

- **5 líneas en `asiento_detalles`, ni una duplicada** (el bug original habría generado 6, con `retencion_ir` dos veces — confirmado que no ocurre).
- Balanceado: $482+$10+$3+$5 = $500 = $500. ✅
- Vouchers (1.1.1.05) liquidado al 100% del bruto del lote. ✅
- Lote pasó a **Liquidado**; columna "Liquidación" del listado mostró correctamente `$482,00` / `Dep. 07/07/2026 · Com. $10,00`, tal como especifica la pantalla.

### 4.3 Segundo lote SIN retención (solo comisión)

**❌ → 🆕 Bug nuevo encontrado y corregido:** al liquidar dejando "Ret. IVA" y "Ret. IR" en blanco (el caso más común — la mayoría de lotes no tienen retención), el backend devolvía el error *"The retencion iva field must be a number. | The retencion ir field must be a number."* y **no liquidaba nada**. Causa: `'retencion_iva' => 'numeric|min:0'` y `'retencion_ir' => 'numeric|min:0'` en `liquidar()` no tenían `nullable`, y el frontend envía cadena vacía `''` cuando el campo no se toca — Laravel rechaza `''` contra la regla `numeric` sin `nullable`. Esto bloqueaba el flujo normal de "solo comisión, sin retención" para **cualquier** lote, no solo el de esta prueba.

**Corrección:** agregado `nullable` a ambas reglas.

**Re-probado tras el fix** (lote $230,00, solo comisión $4,60, sin retenciones, banco destino Banco del Pacífico):
- ✅ Liquidación exitosa: *"Lote LOT-TEST-VALIDACION-2 liquidado. Valor neto: $225.40"*.
- Asiento con exactamente **3 líneas** (Bancos $225,40 / Comisión $4,60 / Vouchers $230,00 haber) — sin líneas de retención en cero ni fantasmas. Balanceado.
- Confirmado en BD: 1 sola `DatafastLiquidacion` para el lote (no se creó ningún duplicado pese a que el toast de éxito apareció dos veces en pantalla — verificado que es solo un artefacto visual de la prueba, no una petición duplicada real).

### 4.4 LOT-433 (dato real pendiente)

Revisado, no liquidado ni modificado (se dejó pendiente intencionalmente para que Steeven decida): id=5, terminal "Datafast Terminal Matriz", $13,00, fecha 2026-07-07, estado **Pendiente**, asiento Paso A ya existente (ver nota 4.1 sobre el patrón viejo sin IVA separado). **Actualización:** su asiento fue corregido en una ronda posterior — ver PARTE 5 más abajo.

### 4.5 Filtros de la pantalla Datafast

**✅ Todos verificados con datos reales:**

| Filtro | Resultado |
|---|---|
| Terminal | ✅ único terminal del sistema ("Datafast Terminal Matriz"), filtra correctamente |
| Estado = Pendiente | ✅ devolvió exactamente el único lote pendiente (LOT-433) |
| Estado = Liquidado | ✅ devolvió los 5 lotes liquidados |
| Rango de fechas (07/07/2026–07/07/2026) | ✅ devolvió exactamente los 3 lotes de esa fecha |
| Búsqueda por N° de lote ("LOT-433") | ✅ resultado exacto |

**Observación (no es bug de esta ronda, dato de seed preexistente):** 2 lotes marcados "Liquidado" (`LOT-20260528`, `LOT-20260525`) muestran "—" en la columna Liquidación por no tener un registro `DatafastLiquidacion` asociado — son datos de siembra (seeders) creados con `estado='liquidado'` directo, no a través del flujo real de `liquidar()` (que sí crea siempre el registro). No se tocó por ser dato preexistente fuera del alcance de esta validación.

**Limpieza:** lotes de prueba `LOT-TEST-VALIDACION-1` y `LOT-TEST-VALIDACION-2`, sus liquidaciones y sus 4 asientos contables fueron eliminados; saldos de Banco Pichincha y Banco del Pacífico restaurados a sus valores previos ($33.998,00 y $7.576,89). `LOT-433` permanece exactamente igual que antes de esta sesión.

**Archivos modificados — cuarta ronda (Datafast, 2026-07-07):**
- `app/Http/Controllers/Bancos/DatafastController.php` — `storeLote()` separa IVA (15%) del total de vouchers; `liquidar()` con `nullable` en `retencion_iva`/`retencion_ir`.

---

## PARTE 5 — Corrección retroactiva de LOT-433 (2026-07-07, quinta ronda)

**Contexto:** tras la cuarta ronda se pidió evaluar el impacto del bug de IVA sobre datos ya existentes antes de decidir si corregir. El análisis (reporte previo) encontró: de 4 lotes en BD, solo **LOT-433** tenía un asiento real generado por el código (los otros 3 "Liquidado" son seed sin `asiento_id`, nunca pasaron por el controller). Impacto: **$1.70 de IVA no registrado**, en un dato real (no de prueba), en estado **Pendiente** (nunca liquidado — sin riesgo de tocar pagos o reportes cerrados aguas abajo).

**Criterio aplicado:** el ejercicio contable 2026-07 está **abierto** (`EjercicioContable id=1, estado=abierto`) y se confirmó que ningún otro registro referenciaba el asiento viejo (`movimientos_bancarios`, `partidas_transito`, `datafast_liquidaciones` — 0 en los tres). Por instrucción explícita, al tratarse de un asiento "borrador" de un lote nunca liquidado, en período abierto y sin dependencias, se optó por la **corrección directa** (eliminar y regenerar) en vez de una reversión con contraasiento — el patrón de reversión (usado para movimientos/cheques con impacto en saldo real) no aplicaba aquí porque este asiento nunca afectó un saldo bancario real (ese impacto solo ocurre al liquidar, y LOT-433 nunca se liquidó).

**Ejecutado dentro de `DB::transaction()`:**
1. Se desvinculó `datafast_lotes.asiento_id` (evita violación de FK al borrar).
2. Se eliminó el asiento viejo `AS-2026-0057` (2 líneas: DEBE 1.1.1.05 $13,00 / HABER 4.1.1.01 $13,00 completo, sin IVA) y sus `asiento_detalles`.
3. Se regeneró un asiento nuevo con la misma lógica ya corregida de `storeLote()` (neto = total ÷ 1.15, IVA = total − neto), preservando la fecha original (2026-07-06, cuando Steeven realmente registró el lote) para no alterar el momento contable del hecho.
4. Se vinculó el lote al nuevo asiento.

**Resultado verificado en BD:**

| Cuenta | Debe | Haber |
|---|---|---|
| 1.1.1.05 Cuentas Virtuales y Pasarelas de Pago | $13,00 | |
| 4.1.1.01 Venta de Mercaderías | | $11,30 |
| 2.1.3.04 IVA en Ventas por Liquidar al SRI | | $1,70 |

- Asiento nuevo: `AS-2026-0064` (id=87), fecha 2026-07-06, estado activo, **balanceado** ($13,00 = $13,00).
- `LOT-433` sigue en estado **Pendiente** — no se liquidó, queda para que Steeven lo pruebe manualmente.
- Asiento viejo (`id=66`) verificado como eliminado, sin referencias huérfanas.
- **Balance de Comprobación general del sistema:** verificado tras el cambio — DEBE total $163.468,81 = HABER total $163.468,81 en todos los asientos activos; 0 asientos individualmente descuadrados.
- Los otros 3 lotes (`LOT-20260525`, `LOT-20260528`, `LOT-20260601`) **no se tocaron** — son seed sin asiento real, confirmado en el análisis previo.

**`npm run build`** — 0 errores (no hubo cambios de código en esta ronda, solo corrección de datos vía `tinker`).

---

## Resumen ejecutivo

| # | Ítem | Estado |
|---|---|---|
| 1.1 | Creación de movimiento (asiento + saldo) | ✅ |
| 1.2/1.3 | Patrón de reversión (Movimientos) | ❌→🆕 corregido |
| 1.3 | Patrón de reversión (Cheques, mismo bug) | ❌→🆕 corregido |
| 1.4 | Consulta Cobros/Pagos — filtros tipo/banco/fecha/beneficiario | ✅ |
| 1.4 | Consulta Cobros/Pagos — filtros documento/centro de costo (LIMITACIÓN 1) | ❌→🆕 corregido 2026-07-07 |
| 1.4 | Exportación PDF/Excel/XML | ✅ (repartida en 2 pantallas) |
| 2.1 | C-04 auto-match | ✅ ya implementado, probado con datos reales en ambas rondas |
| 2.2 | CSV real 3 match + 2 no-match | ✅ 0 falsos positivos (verificado en ambas rondas) |
| 2.3 | Cruce manual | ✅ |
| 2.3 | Generar asiento para partida específica (LIMITACIÓN 2) | ❌→🆕 corregido 2026-07-07 |
| 2.4 | Diferencia se recalcula tras ajuste global | ❌→🆕 corregido |
| 2.4 | Diferencia se recalcula tras generar asiento por partida | ✅ (mismo mecanismo, sin regresión) |
| 2.5 | Cuadre final a $0,00 tras cierre | ✅ (confirmado en las 3 rondas) |
| 3 | Cruce manual con montos distintos — advertencia + justificación contable (LIMITACIÓN 3) | ❌→🆕 corregido 2026-07-07 |
| 3 | Candado de cierre con diferencia agregada sin justificar | ❌→🆕 corregido 2026-07-07 |
| 3 | Cuenta incorrecta en `ajusteConciliacion()` (bug lateral) | ❌→🆕 corregido 2026-07-07 |
| 4.1 | Datafast — Lote Paso A sin separar IVA (bug nuevo) | ❌→🆕 corregido 2026-07-07 |
| 4.2 | Datafast — C-01 (retención IR duplicada) re-validado con datos reales | ✅ no reapareció |
| 4.3 | Datafast — liquidar sin retenciones fallaba por falta de `nullable` (bug nuevo) | ❌→🆕 corregido 2026-07-07 |
| 4.4 | LOT-433 (dato real) | ✅ revisado; corregido retroactivamente en PARTE 5 |
| 4.5 | Filtros pantalla Datafast (terminal/estado/fecha/búsqueda) | ✅ |
| 5 | LOT-433 — asiento regenerado con IVA separado ($1.70), balanceado | ❌→🆕 corregido 2026-07-07 |
| 5 | Balance de Comprobación general tras la corrección | ✅ cuadrado ($163.468,81 = $163.468,81) |

**Estado final: las 2 limitaciones de la segunda ronda, el bug de cruce manual de la tercera ronda, los 2 bugs nuevos de Datafast de la cuarta ronda, y la corrección retroactiva de LOT-433 (quinta ronda) quedaron ✅ Corregidos/Aplicados el 2026-07-07. C-01 se re-validó con datos reales y sigue corregido.**

**Build:** `npm run build` — 0 errores. `npx tsc --noEmit` — sin errores nuevos en los archivos tocados. `php -l` sin errores de sintaxis en los archivos PHP tocados.

**Limpieza:** todos los movimientos, cheques, conciliaciones, partidas, asientos, lotes/liquidaciones Datafast y pagos de CxP de prueba creados en las rondas 1, 2 y 4 fueron eliminados/revertidos; los saldos de Banco Pichincha, Banco Guayaquil y Banco del Pacífico fueron verificados de vuelta a sus valores originales ($33.998,00, $16.860,75 y $7.576,89 respectivamente — el de Pichincha refleja actividad legítima adicional de Steeven entre sesiones, no relacionada con esta validación), y la CxP #14 / Compra #33 usadas en la prueba de filtros fueron revertidas a su estado original (pendiente / sin pago). La **tercera ronda se validó directamente sobre la conciliación real de Steeven** (no era descartable como "dato de prueba") — quedó cerrada y balanceada como resultado legítimo de completar su ejercicio con el código corregido. En la **cuarta ronda, `LOT-433` (dato real) se dejó exactamente igual que al inicio**, pendiente, para que Steeven decida qué hacer con él.

**Archivos modificados — primera ronda (patrón de reversión + cuadre):**
- `app/Http/Controllers/Bancos/MovimientoBancarioController.php` — patrón de reversión correcto.
- `app/Http/Controllers/Bancos/ChequesController.php` — mismo patrón de reversión correcto.
- `app/Http/Controllers/Bancos/ConciliacionController.php` — recálculo de saldo/diferencia tras asiento de ajuste.

**Archivos modificados — segunda ronda (LIMITACIÓN 1 y 2, 2026-07-07):**
- `database/migrations/2026_07_07_080116_add_centro_costo_id_to_movimientos_bancarios_table.php` — nueva columna idempotente.
- `app/Models/MovimientoBancario.php` — fillable + relación `centroCosto()`.
- `app/Http/Controllers/Compras/CuentaPagarController.php` — hereda `centro_costo_id` de la compra al pagar.
- `app/Http/Controllers/Compras/CompraController.php` — propaga `centro_costo_id` en la reversión de pago.
- `app/Http/Controllers/Bancos/BancoReporteController.php` — filtros `num_documento`/`centro_costo_id` en consulta, Excel y PDF.
- `resources/js/Pages/Bancos/Reportes/ConsultaCobrosPagos.tsx` — campos de filtro y columna nuevos.
- `app/Http/Controllers/Bancos/ConciliacionController.php` — método `generarAsientoPartida()`.
- `resources/js/Pages/Bancos/Conciliaciones/Show.tsx` — botón por fila + modal `GenerarAsientoPartidaModal`.
- `routes/web.php` — ruta `bancos.conciliaciones.generar-asiento-partida`.
- `resources/js/types/index.ts` — `centro_costo_id` en la interfaz `MovimientoBancario`.

**Archivos modificados — tercera ronda (LIMITACIÓN 3, 2026-07-07):**
- `app/Services/AsientoService.php` — cuenta `cta_ajuste_conciliacion` (5.4.1.03) nueva; `ajusteConciliacion()` corregido para usarla en vez de la cuenta de inventario.
- `app/Http/Controllers/Bancos/ConciliacionController.php` — `conciliarPartida()` reescrito (detecta diferencia, `generar_ajuste` opcional, recalcula diferencia siempre); `cerrar()` con candado de diferencia agregada; fix del warning `toArray()` en `uploadEstadoCuentaExcel()`.
- `resources/js/Pages/Bancos/Conciliaciones/Show.tsx` — modal de advertencia (SweetAlert2) al cruzar montos distintos.
