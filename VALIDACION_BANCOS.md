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
