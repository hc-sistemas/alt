# Auditoría — Módulo de Importaciones (COMEX)

**Fecha auditoría original:** 2026-07-23
**Fecha de corrección de las 3 brechas reales:** 2026-07-23 (misma sesión, segunda pasada)
**Fecha de implementación del método "Factor de Importación":** 2026-07-23 (misma sesión, tercera pasada)
**Fuente de verdad:** `Sistema Altamira.pdf`, secciones "8. Módulo de Compras" y "8.2 Módulos de importación"; y `MIYAKO USA JULIO 14.xlsx` (hoja de cálculo real del cliente usada en producción para prorratear, aportada como referencia — ver sección dedicada más abajo).
**Método:** lectura directa del código (controllers, modelo, rutas, frontend React) — sin asumir nada del documento de especificaciones ni de reportes previos.

## Método de prorrateo "Factor de Importación" (2026-07-23)

El documento del cliente (página 37) menciona 3 métodos de cálculo (Cantidad, Precio Unitario, Peso), pero **en la práctica real el cliente no usa ninguno de los 3** — usa una hoja de cálculo propia ("MIYAKO USA JULIO 14.xlsx") con una lógica distinta: un **Factor de Importación** único, aplicado multiplicativamente al costo FOB original de cada línea, más un cálculo de precios de venta sugeridos (PVD/PVP) con comisión y márgenes configurables. Se implementó como un 4º método, `factor_importacion`, replicando la fórmula exacta de esa hoja.

**Fórmula implementada** (verificada línea por línea contra el Excel real, 39 aserciones, ver validación abajo):
1. `Factor = (Costo FOB Mercadería + Σ todos los costos extra) / Costo FOB Mercadería`
2. `Costo Unitario Nuevo = Precio Unitario Original de la línea × Factor` (multiplicativo, no un prorrateo proporcional entre líneas como cantidad/precio/peso)
3. `Costo con Comisión = Costo Unitario Nuevo × (1 + %comisión/100)` (default 3%)
4. `PVD sugerido = Costo con Comisión / (1 - %margen_PVD/100) × (1 + %IVA/100)` (default margen 20%)
5. `PVP sugerido = Costo con Comisión / (1 - %margen_PVP/100) × (1 + %IVA/100)` (default margen 35%)

**Nota técnica:** matemáticamente, el paso 1-2 (costo liquidado) da el mismo resultado que el método "Precio Unitario" ya existente cuando todas las líneas están en una sola factura (ambos convergen a `precio_unitario × (1 + costos_extra/costo_fob)`). La diferencia real y el valor agregado de este método son: (a) expone el **Factor** como dato explícito para el usuario, (b) no depende de que haya una única factura para dar el resultado correcto (aplica el factor línea por línea sin prorrateo proporcional intermedio), y (c) calcula precios de venta sugeridos (PVD/PVP) — funcionalidad que no existía en ningún otro método.

**Dónde viven los 3 parámetros configurables (%comisión, %margen PVD, %margen PVP):** se evaluó reutilizar el módulo de "Parámetros Contables" (`ParametroContableController`), pero ese módulo solo mapea eventos a cuentas contables (no maneja valores porcentuales), y la tabla genérica `configuraciones` existe en el schema pero no tiene ningún controller ni página que la use — construir un CRUD nuevo desde cero para esto era una expansión de alcance no pedida. Se implementó tal como pide la propia especificación del frontend: **campos editables en el momento de liquidar**, precargados con los defaults (3% / 20% / 35%), sin necesidad de una pantalla de configuración global separada.

**Archivos:**
- `app/Http/Controllers/Compras/ImportacionController.php`: `liquidar()` con rama `factor_importacion` (lógica multiplicativa, sin el prorrateo proporcional de los otros 3 métodos) + helper `aplicarCostoLinea()` extraído para reusar el snapshot/reversión entre todos los métodos; `resultadoLiquidacion()` calcula PVD/PVP sugeridos a partir del costo ya liquidado + comisión/margen/IVA (guardados en `snapshot_liquidacion`, sin necesidad de columnas nuevas).
- `resources/js/Pages/Compras/Importaciones/Index.tsx`: opción "Factor de Importación" en el selector de método, 3 campos editables (comisión/margen PVD/margen PVP) que aparecen solo con este método, Factor mostrado en el resumen de "Resultado de Liquidación", y precarga automática de PVP/PVD con los precios sugeridos en esa misma vista (en vez de dejarlos en blanco o con el precio anterior).

**Validación (39 aserciones, escenario real recreado exacto del Excel MIYAKO USA, dentro de `DB::transaction()` con rollback forzado — cero residuos):**
- Proveedor MIYAKO USA + 17 productos con cantidades/precios unitarios FOB exactos del Excel + 10 costos extra exactos (Advalorem, Fodinfa, IVA, Flete Marítimo, Gastos Destino, Factura Honorarios, Almacenaje, ISD, Honorarios Banco, Transporte Nacional) — suma verificada = $5,584.969, igual al Excel.
- **Factor calculado = 1.3596574187724** — coincide con el 1.359657 del Excel.
- **Los 17 productos** (incluido el ítem con FOB=$0, que correctamente da costo 0) — costo unitario nuevo coincide con el Excel en cada uno, con margen de error <0.001.
- **PVD/PVP sugeridos** verificados en 2 productos distintos (ítem 1: $20.13/$24.78; ítem 9, el de mayor valor: $785.09/$966.26) — coinciden exactamente con el Excel.
- Cruce de anticipo (C-08) probado junto con este método: anticipo se cruza contra la CxP de la importación, asiento del cruce generado y balanceado (`debe = haber`).
- Balance de Comprobación general verificado cuadrado antes y después.
- `npm run build` sin errores.

## Estado tras la corrección (2026-07-23)

| Prioridad | Punto | Estado |
|---|---|---|
| 1 | Botón "Revertir" funcional | ✅ **Corregido** — `ImportacionController::revertir()` |
| 2 | Método de prorrateo por Peso | ✅ **Corregido** — columna `productos.peso` + lógica generalizada en `liquidar()` |
| 3 | Botón "Copiar" | ✅ **Corregido** — `ImportacionController::copiar()` |
| Bonus | Fecha Liquidación / Tipo Cálculo fuera del alta | ✅ **Diseño válido, no es un bug** (ver justificación abajo) |

Validado con dos suites de prueba de integración (creadas y ejecutadas dentro de `DB::transaction()` con rollback forzado — cero datos de prueba persistidos, confirmado por conteo `0` de filas `TEST-%` tras la corrida):
- Liquidar por **peso** con 2 productos de peso muy distinto (10 kg vs 1 kg, misma cantidad y mismo precio) → el producto más pesado absorbe correctamente más costo extra proporcional al peso, no a cantidad ni precio.
- **Revertir** una liquidación con cruce de anticipo (C-08) activo → costo de productos restaurado exactamente al valor anterior, `costo_promedio` de `inventario_saldos` restaurado, saldo y estado del anticipo y de la CxP restaurados, asiento original del cruce anulado (`estado=0`, no borrado) y un asiento de reversión nuevo generado y balanceado (`debe = haber`).
- **Candado de seguridad**: se simuló una venta (reducción de `stock_actual`) después de liquidar y se confirmó que `revertir()` **bloquea** la operación con un mensaje explícito, en vez de dejar el costo de ventas ya registrado inconsistente.
- **Re-liquidar** la misma importación por **cantidad** tras revertir → funciona correctamente, confirma que el ciclo liquidar→revertir→re-liquidar con otro método es totalmente operativo.
- **Copiar** una importación → la nueva fila copia nombre (con sufijo "(Copia)"), proveedor, agente aduanero, país y divisa; NO copia fechas, monto FOB (queda en 0) ni estado (siempre nace `en_transito`).
- Balance de Comprobación verificado **cuadrado antes y después** de ambas suites: `DEBE = HABER = $85,582,517.3607`.
- `npm run build` sin errores tras los cambios de frontend.

### Bonus — Fecha Liquidación y Tipo Cálculo fuera del formulario de alta: diseño válido, no bug

Releyendo el propio documento del cliente con más atención (página 35): *"...la fecha de liquidación **(cuando ya se prorratea los precios)**..."* — la propia especificación aclara entre paréntesis que ese campo solo cobra sentido *una vez que ya se prorratearon los precios*, es decir, en el momento de liquidar, no de crear. Y la sección de liquidación (página 37) describe explícitamente que el método de distribución ("Cantidad, Precio Unitario o peso") se selecciona **en la pantalla de Liquidar Importación**, no en el alta. La captura de pantalla del sistema legado (un único formulario con todos los campos, la mayoría en blanco hasta liquidar) es un artefacto de la UI vieja, no un requisito funcional de que esos 2 campos deban ser editables en el alta. **Se deja el flujo actual como está** (Fecha Liquidación y Método de prorrateo se definen en el Tab "4. Liquidación", no en el alta) — es el diseño correcto según la propia narrativa del documento, no una brecha.

---

**Archivos auditados:**
- `app/Http/Controllers/Compras/ImportacionController.php`
- `app/Models/Importacion.php`
- `app/Http/Controllers/Compras/AnticipoProveedorController.php`
- `app/Services/AsientoService.php` (método `cruciarAnticipo`)
- `resources/js/Pages/Compras/Importaciones/Index.tsx`
- `resources/js/Pages/Compras/Anticipos/Index.tsx`
- `resources/js/Components/shared/Sidebar.tsx`
- `routes/web.php` (grupo `compras/importaciones` y `compras/anticipos`)
- `database/altamira_schema.sql` (referencia — puede diferir del schema real, ver CLAUDE.md)

---

## Resumen de veredictos (auditoría original)

| # | Punto | Veredicto original | Estado actual |
|---|---|---|---|
| 1 | Campos del formulario "Crear importación" | ⚠️ Existe parcialmente | ✅ Diseño válido (ver Bonus) |
| 2 | Estado con 3 valores (En tránsito / En aduana / Liquidada) | ✅ Existe | ✅ Sin cambios |
| 3 | 12 tipos de costos extra tipificados | ✅ Existe (con matiz) | ✅ Sin cambios |
| 4 | 3 métodos de distribución (Cantidad, Precio, **Peso**) | ⚠️ Existe parcialmente | ✅ **Corregido** |
| 5 | Botón "Revertir" tras liquidar | ⚠️ Existe parcialmente (placeholder deshabilitado, sin backend) | ✅ **Corregido** |
| 6 | Botón "Copiar" (duplicar importación) | ❌ No existe | ✅ **Corregido** |
| 7 | Botón "Registrar Anticipo Internacional" en UI de Compras | ✅ Existe (con matiz de nombre) | ✅ Sin cambios |
| 8 | Cruce automático de anticipo al liquidar (C-08) | ✅ Existe, intacto | ✅ Sin cambios (revertir() lo respeta) |

---

## Detalle punto por punto

### 1. Formulario "Crear importación" — ⚠️ Existe parcialmente

**Archivo:** [`resources/js/Pages/Compras/Importaciones/Index.tsx:106-211`](resources/js/Pages/Compras/Importaciones/Index.tsx#L106-L211) (`CrearModal`), validado en [`app/Http/Controllers/Compras/ImportacionController.php:65-91`](app/Http/Controllers/Compras/ImportacionController.php#L65-L91) (`store()`).

| Campo pedido | ¿Existe en el formulario de creación? | Dónde |
|---|---|---|
| Nombre | ✅ | Index.tsx:137-141, requerido |
| Agente Aduanero | ✅ | Index.tsx:158-162 |
| Monto (Costo FOB) | ✅ | Index.tsx:176-182, requerido |
| País | ✅ (país de embarque) | Index.tsx:165-169 |
| Fecha Partida | ✅ | Index.tsx:184-188 |
| Fecha Llegada | ✅ | Index.tsx:189-193 |
| Descripción (N° Invoice, según el propio PDF pág. 35) | ✅ | Index.tsx:153-157 (`num_invoice`) |
| Observación | ✅ | Index.tsx:195-199 |
| **Fecha Liquidación** | ❌ **No está en el formulario de creación** | Solo aparece después, en el Tab "4. Liquidación" (Index.tsx:907-910), al momento de liquidar |
| **Tipo Cálculo** | ❌ **No está en el formulario de creación** | Solo aparece en el Tab "4. Liquidación" como "Método de prorrateo" (Index.tsx:898-905) |

**Conclusión:** 8 de los 10 campos existen tal como los pide el documento. Los 2 restantes (Fecha Liquidación y Tipo Cálculo) no están ausentes del sistema — existen, pero diferidos al momento de la liquidación en vez de estar presentes desde la creación, como sí lo muestra el formulario legado de la captura de pantalla del PDF (pág. 35), que los tiene todos juntos en una sola ventana. Es una decisión de diseño razonable (no se puede prorratear ni fijar fecha de liquidación antes de tener facturas y costos cargados), pero no coincide literalmente con el layout del sistema legado que el documento usa como referencia.

---

### 2. Estado con 3 valores — ✅ Existe

**Archivos:**
- `database/altamira_schema.sql:1491`: `estado VARCHAR(20) DEFAULT 'en_transito' -- 'en_transito','en_aduana','liquidada'`
- [`app/Models/Importacion.php:64-82`](app/Models/Importacion.php#L64-L82): `getEstadoColorAttribute()` / `getEstadoLabelAttribute()` mapean exactamente los 3 estados.
- [`app/Http/Controllers/Compras/ImportacionController.php:101`](app/Http/Controllers/Compras/ImportacionController.php#L101): `'estado' => 'nullable|in:en_transito,en_aduana,liquidada'`.
- [`resources/js/Pages/Compras/Importaciones/Index.tsx:77-81`](resources/js/Pages/Compras/Importaciones/Index.tsx#L77-L81): `ESTADO_CFG` con badges para los 3 estados (`Plane` para en tránsito, `Anchor` para en aduana, `CheckCircle2` para liquidada).
- [Index.tsx:405-406](resources/js/Pages/Compras/Importaciones/Index.tsx#L405-L406): el `<select>` de edición permite elegir manualmente entre "En Tránsito" y "En Aduana" — **el paso intermedio "En Aduana" sí existe como opción real**, no es un salto directo tránsito→liquidada. "Liquidada" se alcanza únicamente vía el botón/acción de liquidar (correcto, no debería ser seleccionable a mano).

**Conclusión:** los 3 estados existen y el estado intermedio funciona como corresponde.

---

### 3. Los 12 tipos de costos extra — ✅ Existe (con matiz)

**Archivo:** [`resources/js/Pages/Compras/Importaciones/Index.tsx:27-41`](resources/js/Pages/Compras/Importaciones/Index.tsx#L27-L41), array `CONCEPTOS_COSTO`, usado en el `<select>` del Tab "3. Costos Extra" (Index.tsx:624-631).

| # | Requerido por el PDF | ¿Existe en el dropdown? |
|---|---|---|
| 1 | ISD | ✅ |
| 2 | 15% IVA | ✅ ("IVA 15%") |
| 3 | Seguro Transporte Internacional | ✅ |
| 4 | Advalorem | ✅ |
| 5 | Fodinfa | ✅ ("FODINFA") |
| 6 | ICE | ✅ |
| 7 | Flete Marítimo | ✅ |
| 8 | Gastos Destino Ecuador | ✅ |
| 9 | Factura Honorarios (Aduanero) | ✅ ("Honorarios Aduanero") |
| 10 | Factura Almacenaje | ✅ ("Almacenaje") |
| 11 | Honorarios Banco | ✅ |
| 12 | Factura Transporte Nacional | ✅ ("Transporte Nacional") |

**Los 12 están presentes**, más una opción 13 "Otro" con campo de texto libre (Index.tsx:632-638).

**Matiz importante:** esto **no está tipificado a nivel de base de datos**. El valor elegido en el dropdown se guarda como texto plano en `compras.concepto` (VARCHAR(500), ver `agregarCosto()` en [`ImportacionController.php:302-367`](app/Http/Controllers/Compras/ImportacionController.php#L302-L367)) — no hay un ENUM, CHECK constraint, ni tabla catálogo `tipos_costo_extra`. Es decir: la lista de 12 existe como una curación en el frontend, no como una restricción real de integridad. En la práctica esto funciona igual para el usuario (siempre ve las mismas 12 opciones), pero un reporte futuro que necesite "sumar por tipo de costo de forma confiable" no puede apoyarse en una restricción de base de datos — depende de que nadie use "Otro" con variantes de texto distintas para el mismo concepto.

---

### 4. 3 métodos de distribución (Cantidad, Precio Unitario, **Peso**) — ⚠️ Existe parcialmente

**Archivos:**
- [`app/Http/Controllers/Compras/ImportacionController.php:120`](app/Http/Controllers/Compras/ImportacionController.php#L120): `'metodo_prorrateo' => 'required|in:cantidad,precio'` — **solo 2 valores aceptados por el backend**.
- [`ImportacionController.php:148-151`](app/Http/Controllers/Compras/ImportacionController.php#L148-L151):
  ```php
  $bases = $comprasProducto->map(fn($compra) => match ($metodo) {
      'cantidad' => (float) $compra->detalles->sum('cantidad'),
      default    => (float) $compra->total,   // 'precio'
  });
  ```
  No hay ninguna rama para `'peso'`.
- [`resources/js/Pages/Compras/Importaciones/Index.tsx:329`](resources/js/Pages/Compras/Importaciones/Index.tsx#L329): `useState<'cantidad' | 'precio'>('cantidad')` — el tipo TypeScript ni siquiera contempla `'peso'`.
- [Index.tsx:900-904](resources/js/Pages/Compras/Importaciones/Index.tsx#L900-L904): el `<select>` de método de prorrateo solo tiene 2 `<option>`: "Por cantidad (unidades)" y "Por precio (valor FOB)".

El propio `altamira_schema.sql:1489` documenta la intención original: `metodo_prorrateo VARCHAR(20) DEFAULT 'cantidad' -- 'cantidad','precio','peso'` — la columna fue diseñada para soportar los 3 valores, pero el método "peso" nunca se implementó ni en el backend ni en el frontend. No existe ningún campo de peso (kg) en `productos`, `compra_detalles` ni en el formulario de importación que pudiera alimentar ese cálculo.

**Conclusión:** confirmado exactamente lo que el checklist anticipaba — solo existen Cantidad y Precio Unitario. El método "Peso" está ausente por completo (ni columna de peso del producto, ni lógica de prorrateo, ni opción en el `<select>`).

#### ✅ Corregido (2026-07-23)

- Migración `2026_07_23_154209_add_peso_y_reversion_a_importaciones.php`: agrega `productos.peso` (decimal, kg).
- `resources/js/Pages/Inventario/Productos/Form.tsx`: nuevo campo "Peso unitario (kg)" en el tab Inventario; validado en `ProductoController::store()/update()`.
- `ImportacionController::liquidar()` reescrito con una función `$baseDetalle` generalizada (`cantidad`, `cantidad × precio_unitario`, o `cantidad × peso del producto`, según el método elegido) que ahora gobierna **tanto** el reparto del costo extra entre facturas **como** el reparto entre líneas dentro de una misma factura — antes el reparto interno estaba hardcodeado a cantidad sin importar el método elegido, un bug adicional descubierto al escribir la prueba de integración.
- Frontend: `<option value="peso">Por peso (kg)</option>` agregado al selector de método de prorrateo, tipo TypeScript actualizado a `'cantidad' | 'precio' | 'peso'`.
- **Probado:** 2 productos con igual cantidad y precio pero peso 10 kg vs 1 kg → el de 10 kg recibe ~9× más costo extra que el de 1 kg, proporcional al peso real (110 kg de base total, no a cantidad ni valor).

---

### 5. Botón "Revertir" tras liquidar — ⚠️ Existe parcialmente (no funcional)

**Archivo:** [`resources/js/Pages/Compras/Importaciones/Index.tsx:802-809`](resources/js/Pages/Compras/Importaciones/Index.tsx#L802-L809):
```jsx
<button
    type="button"
    disabled
    title="Contacte al administrador para revertir manualmente"
    className="w-full py-2 px-4 rounded-lg text-sm font-medium border opacity-40 cursor-not-allowed"
    ...>
    Revertir liquidación (no disponible)
</button>
```

El botón **existe visualmente** en el Tab "4. Liquidación" cuando la importación ya está liquidada, pero está `disabled` de forma permanente y su propio texto dice "no disponible". Confirmado en `routes/web.php` (grupo `compras/importaciones`, líneas 353-360): las únicas rutas son `index`, `store`, `detalle`, `update`, `liquidar`, `agregar-costo`, `crear-factura` — **no existe ninguna ruta ni método de controller para revertir** una liquidación.

**Conclusión:** es un placeholder de UI que documenta la intención, pero no hay ninguna implementación funcional (ni frontend activo ni backend). Si un usuario liquida con el método equivocado, hoy no hay forma de deshacerlo desde la interfaz.

#### ✅ Corregido (2026-07-23)

- `Importacion::liquidar()` ahora captura un **snapshot** (`importaciones.snapshot_liquidacion`, columna JSONB nueva) antes de mutar nada: costo anterior de cada producto tocado, fila y valores anteriores de `inventario_saldos.costo_promedio`, stock al momento de liquidar, estado anterior de la importación, y — para cada cruce de anticipo (C-08) que haya ocurrido — el saldo/estado anterior del anticipo y de la CxP más el `asiento_id` del cruce generado.
- Nuevo método `ImportacionController::revertir()` (ruta `PATCH compras/importaciones/{importacion}/revertir`):
  1. **Candado de seguridad**: si el `stock_actual` actual de algún producto tocado es menor al capturado en el snapshot (indicio de que ya se vendió/movió con el costo liquidado), **bloquea la reversión** con un mensaje explícito — no revierte a ciegas.
  2. Restaura `productos.costo` y decrementa `inventario_saldos.costo_promedio` exactamente por el delta que se había incrementado (no una sobre-escritura ciega).
  3. Para cada cruce de anticipo: restaura saldo/estado del `AnticipoProveedor` y la `CuentaPagar`, y **anula** (no borra) el asiento del cruce vía el mismo patrón ya usado en Bancos/Cheques (`AsientoService::anular()`, que genera un asiento de reversión nuevo con debe/haber invertidos).
  4. Restaura el `estado` de la importación al valor previo a liquidar (`en_transito` o `en_aduana`, el que corresponda — no hardcodeado).
  5. Todo dentro de `DB::transaction()`; si `AsientoService::anular()` falla (p. ej. período contable cerrado), toda la reversión se cancela — no queda un estado a medias.
- Frontend: el botón ya no está `disabled`; ahora abre un `ConfirmModal` (mismo componente ya usado en el resto del sistema) antes de ejecutar la reversión.
- **Probado:** liquidación con cruce de anticipo real → reversión completa y verificada campo por campo (costo, costo_promedio, saldo/estado de anticipo y CxP, asiento anulado + asiento de reversión balanceado) → nueva liquidación con otro método sobre la misma importación, exitosa. Y por separado: candado de stock-ya-vendido probado y confirmado que bloquea correctamente.

---

### 6. Botón "Copiar" para duplicar importación — ❌ No existe

Búsqueda de `copiar`/`duplicar`/`copy`/`duplicate` en `ImportacionController.php` y en `Index.tsx`: **cero coincidencias** (aparte de la palabra "Revertir" ya cubierta en el punto anterior). No hay botón, no hay ruta, no hay método de controller. El listado de acciones por fila en Index.tsx:1041-1066 solo tiene dos botones: Ver/Editar y Liquidar (este último condicionado a que no esté ya liquidada).

**Conclusión:** funcionalidad completamente ausente, sin rastro de intento previo.

#### ✅ Corregido (2026-07-23)

- Nuevo método `ImportacionController::copiar()` (ruta `POST compras/importaciones/{importacion}/copiar`): crea una nueva `Importacion` copiando **nombre** (con sufijo " (Copia)"), **proveedor**, **agente aduanero**, **país de embarque** y **divisa** — explícitamente **sin** copiar fechas, monto FOB (queda en 0) ni estado (la copia siempre nace `en_transito`, sin importar el estado del original).
- Botón con ícono `Copy` (lucide-react) en el listado, junto a Editar/Liquidar, siempre visible sin importar el estado de la importación original.
- **Probado:** copiar una importación en estado `en_aduana` con monto y fechas reales → la copia tiene el nombre correcto, agente/país/proveedor copiados, costo_fob=0, fechas nulas y estado `en_transito`.

---

### 7. Botón "Registrar Anticipo Internacional" en Compras (UI) — ✅ Existe (con matiz de nombre)

**Archivos:**
- `routes/web.php:337-342`: grupo `compras/anticipos` con `index`, `store`, `cxp-pendientes`, `cruzar`, `anular`, todos en `AnticipoProveedorController`.
- `resources/js/Components/shared/Sidebar.tsx:49`: `{ nombre: 'Anticipos Proveedores', href: '/compras/anticipos' }` — **dentro del grupo de navegación de Compras**, junto a Proveedores, Cuentas por Pagar, Devoluciones e Importaciones.
- `resources/js/Pages/Compras/Anticipos/Index.tsx:97,180`: botones "Nuevo Anticipo" (abre el modal) y "Registrar Anticipo" (submit del formulario).
- El modal (`Index.tsx:60-183`) permite elegir cualquier proveedor y, de forma opcional, una **"Importación asociada"** (`Index.tsx:120-133`) — es decir, cubre exactamente el caso de uso de anticipos a proveedores extranjeros ligados a una carpeta COMEX, no solo el método backend `anticipoProveedor()`.

**Matiz:** el botón/página se llama "Anticipos Proveedores" / "Registrar Anticipo", no literalmente "Registrar Anticipo Internacional" como lo nombra el documento, y no está *dentro* del formulario de Importaciones sino como un submódulo hermano dentro de Compras. Funcionalmente cumple el requisito (proveedor + importación asociada + banco de origen), solo difiere el rótulo exacto y la ubicación (submódulo propio vs. botón embebido en la pantalla de Importaciones).

---

### 8. Cruce automático de anticipo al liquidar (C-08) — ✅ Existe, intacto

**Archivo:** [`app/Http/Controllers/Compras/ImportacionController.php:191-253`](app/Http/Controllers/Compras/ImportacionController.php#L191-L253), dentro de `liquidar()`.

Verificado línea por línea:
1. Busca `AnticipoProveedor` con `saldo > 0` y `estado = 'pendiente'` del mismo proveedor de la importación (líneas 194-200).
2. Busca `CuentaPagar` con `saldo > 0` en estado `pendiente`/`parcial`, vinculadas a compras de productos (no gastos) de esa misma importación (líneas 202-208).
3. Cruza ambos por el menor de los dos saldos (`min($anticipo->saldo, $cxp->saldo)`, línea 218), actualiza ambos saldos y estados (líneas 220-232).
4. Genera el asiento contable del cruce vía `AsientoService::cruciarAnticipo()` (línea 236-242), verificado en [`app/Services/AsientoService.php:804-834`](app/Services/AsientoService.php#L804-L834): **Debe cuenta de proveedores (local o exterior según `$proveedor->tipo`) / Haber cuenta de anticipos a proveedores** — coincide exactamente con la regla del PDF ("Laravel debe restar de forma automática el saldo acumulado en la cuenta 1.1.3.3 de ese proveedor, enviando solo la diferencia restante a las cuentas por pagar").
5. Todo el bloque está envuelto en `try/catch` para no bloquear la liquidación si el período contable está cerrado (línea 243-245) — comportamiento correcto y deliberado, no un bug.

**Verificación de que los cambios recientes de rendimiento no lo tocaron:** los commits de esta sesión (`e439aff`, `ff15a39`) solo modificaron `ReporteContableController.php`, `ConciliacionController.php` y agregaron migraciones de índices — ninguno de estos archivos ni las tablas `anticipos_proveedores`/`cuentas_pagar`/`compras` fueron alterados en su lógica. El mecanismo C-08 sigue exactamente igual que antes de esos cambios.

---

## Qué priorizar (auditoría original, ya resuelto)

De los 3 puntos con brecha real:
- **Punto 4 (método "Peso")** era el más señalado explícitamente por el documento del cliente y el que tenía mayor impacto en la exactitud contable si Altamira importa mercadería donde el peso es el criterio de prorrateo correcto (ej. carga a granel, fletes cobrados por kg).
- **Punto 5 (Revertir)** era un riesgo operativo: sin él, un error de método de prorrateo al liquidar requería intervención manual en base de datos.
- **Punto 6 (Copiar)** era la de menor urgencia — comodidad de UX, no afecta integridad contable.

**Las 3 brechas fueron cerradas en esta misma sesión** (ver "Estado tras la corrección" al inicio del documento). Commits separados por prioridad, sin push — pendiente de autorización para subir.
