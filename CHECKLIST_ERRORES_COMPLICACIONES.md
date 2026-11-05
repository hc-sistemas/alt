# Checklist — Errores y complicaciones (pruebas Vendedor/Bodeguero/Técnico)

Origen: `E:\Altamira\Implementación\Errores y complicaciones Sistema contable.pdf` (equipo de pruebas, perfiles Vendedor/Bodeguero/Técnico).
Auditoría de verificación: Fase 1, 2026-09-26 (solo lectura, código + BD local).

Estado de cada ítem:
- ☐ Pendiente — no se ha tocado nada.
- 🔶 En decisión — esperando respuesta del cliente antes de tocar código.
- 🛠️ En progreso — se está implementando.
- ✅ Resuelto — implementado y probado (indicar commit/fecha).
- 🚫 Descartado — se decidió no hacerlo (indicar por qué).

Cada fila se actualiza en el momento en que se decide o se implementa algo. No reordenar los IDs; el orden de aparición ya es el orden de prioridad de trabajo.

---

## Etapa 1 — Bloquea vender/facturar hoy

- [x] **A3** — Bodega "Principal UIO" no existe (nombre fijo roto): impide facturar productos físicos en Matriz e Import, prefacturas y facturar desde proforma.
  - Veredicto: CONFIRMADO (bug). Evidencia: `ResuelveBodegasFijas.php:26-31`, `FacturaController.php:50-65`.
  - Decisión/estado: ✅ Resuelto (2026-09-27). `ResuelveBodegasFijas` ahora resuelve la bodega Principal por `empresa_id` + `tipo='general'` (respaldo por nombre `Principal`/`Bodega Principal UIO`) y la Reservas por `tipo='reserva'`, igual que ya hacía `ResuelveBodegaTaller`. Se eliminó la copia duplicada del método en `FacturaController` (ahora usa el trait). Verificado contra BD local: Matriz → Principal id 1, Import → Principal id 10 (ambas tienen también "Muestra" tipo `general`; se toma la de menor id).
- [x] **A1** — Precio de la factura editable sin validar contra precio de lista (servidor confía en el precio del navegador); bypass total vía Proforma/Prefactura.
  - Veredicto: CONFIRMADO (bug + regla de negocio). Evidencia: `Facturas/Form.tsx:1060-1067`, `FacturaController.php:254,303-309`, `ProformaController.php:211`, `PrefacturaController.php:200`.
  - Decisión/estado: ✅ Resuelto (2026-09-27). Decisión del cliente: el precio NUNCA es editable por el usuario; solo el descuento cambia el valor final de la línea. Implementado en **Facturas, Proformas y Prefacturas** (las tres comparten la misma regla, ya que Proforma/Prefactura eran la ruta de bypass documentada en A1):
    - Frontend: el input de precio quedó `readOnly` en los tres formularios; ya no dispara ningún handler de edición.
    - Backend: `FacturaController::store`, `ProformaController::store` y `PrefacturaController::store` ya NO usan `$detalle['precio']` del request — el precio sale siempre de `productos.pvp` resuelto en el servidor (`$preciosProductos`). El campo `precio` que sigue mandando el frontend en el payload es ahora decorativo; un POST manipulado no tiene ningún efecto sobre el precio guardado.
    - En Facturas, si el propio precio de lista de un producto queda por debajo del costo (error de configuración del maestro), se sigue pidiendo aprobación de supervisor — el disparo se movió de "al escribir el precio" a "al seleccionar el producto" (`seleccionarProductoLocal`), porque ya no hay tecleo de precio que capturar.
    - Pendiente fuera de esta decisión (no tocado): `graba_iva` sigue viniendo del request sin comparar contra el producto (riesgo #4 del informe de Fase 1); es un hallazgo aparte, no pedido en esta ronda.
- [x] **A4** — Falta botón "+" visible para agregar formas de pago (backend y estado ya soportan varias).
  - Veredicto: PARCIAL (solo falta UI). Evidencia: `Facturas/Form.tsx:534` (se agrega con Enter oculto).
  - Decisión/estado: 🚫 Descartado (2026-09-27). No se implementa botón; se capacita al usuario en que presionando Enter en la última fila de pago (con monto > 0) se agrega una fila nueva. Sin cambios de código.
- [ ] **A6** — Secuenciales por empresa: columnas `cod_establecimiento`/`cod_punto_emision` no existen en BD (son `codigo_*`); Configuración→Empresa no puede editar establecimiento/punto de emisión ni el secuencial inicial de Import.
  - Veredicto: PARCIAL (bug de mapeo + funcionalidad UI faltante). Evidencia: `Empresa.php:21,66`, `SecuencialService.php:23-24`, `EmpresaController.php:45-52`, `Empresa/Index.tsx:199-234`.
  - Decisión/estado: 

## Etapa 2 — Seguridad (corregible ya, sin decisión de negocio)

- [x] **A2** — El vendedor ve el costo del producto (props de Inertia + mensajes de error) en Facturas/Proformas/Prefacturas.
  - Veredicto: CONFIRMADO. Evidencia: `FacturaController.php:167-168`, `ProformaController.php:125`, `PrefacturaController.php:136`, `Form.tsx:1242-1251`.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Se quitó `costo` del `select()` de productos en Proformas y Prefacturas (no lo usaban para nada). En Facturas (donde sí dispara la aprobación de precio bajo costo) el servidor ya no manda el número: calcula `bajo_costo` (booleano) en `FacturaController::create()` y oculta `costo` con `makeHidden()`. El frontend usa esa bandera para abrir el modal de aprobación (ahora disparado al seleccionar el producto, no al teclear un precio — ver A1) y el modal/los mensajes de error ya no muestran ninguna cifra de costo, solo texto genérico.
- [x] **`clienteGuardar` sin permiso** (hallazgo fuera del PDF) — cualquier vendedor puede reescribir `tiene_credito`/`cupo_maximo`/`dias_credito` de un cliente existente desde el modal de factura.
  - Veredicto: CONFIRMADO. Evidencia: `FacturaController.php:977-1006`.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Decisión del cliente: mantener el comportamiento actual (crear y editar todo, incluido crédito) pero solo para quien tenga permiso — como no existe un módulo `Clientes`/`Personas` en la tabla `modulos`, se gateó por perfil (`super_admin`/`admin`/`contador`), igual que ya hace `AsistenciaController::es_admin`. Si el cliente (por identificación) ya existe y quien llama no es uno de esos tres perfiles, la petición se rechaza con 403 y un mensaje claro — **pero solo para editar un cliente que ya existe**; crear un cliente nuevo sigue abierto para cualquier vendedor con acceso a Facturas, porque bloquearlo también habría roto el flujo normal de venta (registrar un cliente nuevo en el momento de facturar). Si se quiere bloquear también la creación, es una vuelta de tuerca aparte — avisar si hace falta.
- [x] **C1** — Vendedor ve y puede crear/editar/borrar Proveedores vía `/personas/proveedores` (sin middleware de permiso).
  - Veredicto: CONFIRMADO. Evidencia: `web.php:455-473`, `Sidebar.tsx:11` (`personas` en `MODULOS_SIN_PERMISO`).
  - Decisión/estado: ✅ Resuelto (2026-09-29). Se protegió `/personas/proveedores` con el mismo criterio que ya usaba `/compras/proveedores`: ver (index/show/create/edit/reportes) exige `permiso:compras,ver`, crear exige `compras,crear`, editar `compras,editar`, eliminar `compras,eliminar`. En el Sidebar, el hijo "Proveedores" dentro de "Personas" ahora solo se muestra si `permisos.compras.ver === true` (nuevo campo `permiso` en `NavHijo`). Clientes y Transportistas quedan como estaban (abiertos), sin cambios.
- [x] **C2** — Vendedor y Bodeguero pueden ver Reportes SRI (menú y URL directa, sin middleware).
  - Veredicto: CONFIRMADO. Evidencia: `web.php:686-692`, `Sidebar.tsx:11` (`reportes` en `MODULOS_SIN_PERMISO`).
  - Decisión/estado: ✅ Resuelto (2026-09-29). Se sacó `reportes` de `MODULOS_SIN_PERMISO` en `Sidebar.tsx` (ahora respeta `permisos.reportes.ver`) y se protegieron las rutas `/reportes/sri/*` con `permiso:reportes,ver`. Se corrigió también el dato: Vendedor tenía `reportes.ver=true` en ambas empresas (migración `2026_09_29_124344_corregir_permisos_vendedor_bodeguero` + `PermisoSeeder.php` actualizado). Bodeguero ya tenía `reportes.ver=false`.
- [x] **C3** — Bodeguero: Dashboard no se puede ocultar por perfil; puede editar/borrar Proveedores igual que C1; sí puede crear Transportistas (correcto, pero por accidente de que todo Personas está abierto).
  - Veredicto: PARCIAL. Evidencia: `LoginController.php:65`, mismo bug de C1.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Decisión del cliente: aterrizar en el primer módulo que sí vean. Se sacó `dashboard` de `MODULOS_SIN_PERMISO`; `DashboardController::index()` ahora redirige (vía la nueva clase `App\Support\LandingRoute`) al primer módulo con `ver=true` en este orden: ventas → inventario → compras → taller → bancos → contabilidad → rrhh → reportes → configuración (o se queda en el Dashboard si no tiene ninguno). `Bodeguero.dashboard.ver` se puso en `false` en ambas empresas (mismas migración/seeder de C2), así que hoy aterriza en Inventario. Proveedores ya queda cubierto por C1 (mismo permiso `compras`). Login/selección de empresa no se tocaron: siguen apuntando a la ruta `dashboard`, que ahora decide sola si redirige.
- [x] **Datos de permisos inconsistentes entre empresas** (hallazgo fuera del PDF) — el perfil Bodeguero de Import tiene `ventas`/`compras`/`taller` en ver y el de Matriz no.
  - Veredicto: CONFIRMADO (dato mal cargado). Evidencia: matriz de permisos en BD.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Alineado al de Matriz (que era el correcto): `ventas`, `compras` y `taller` en `false` para Bodeguero en ambas empresas. Misma migración que C2/C3.

## Etapa 3 — Datos que se pierden o quedan inconsistentes

- [x] **B1** — `FacturaPago::$fillable` no incluye `plazo`, `unidad`, `banco`, `num_cheque`: se capturan en el formulario y se descartan en silencio al guardar.
  - Veredicto: CONFIRMADO (bug), con un matiz encontrado al implementar: `banco` y `num_cheque` YA estaban en `$fillable` (el bug real ahí era que el controller nunca los mandaba a `create()`, no que faltaran en el modelo). `plazo`/`unidad` no son columnas reales de `factura_pagos` — la tabla ya tiene `dias_credito` para exactamente este propósito.
  - Decisión/estado: ✅ Resuelto (2026-09-29). `FacturaController::store()` ahora manda `banco`/`num_cheque` a `FacturaPago::create()` (se perdían siempre) y usa la columna real `dias_credito` en vez de las inexistentes `plazo`/`unidad`. Además, la `fecha_vencimiento` de la CxC generada ahora usa el plazo digitado en esa forma de pago si vino, y solo cae al `dias_credito` del cliente (o 30) si no vino nada — antes siempre ignoraba lo digitado.
- [x] **A7 (parte bug)** — Al anular una factura a crédito, la CxC generada no se cierra ni se marca; la cartera queda inflada. (La afirmación "valor 0" del documento es FALSA: los totales no se tocan al anular.)
  - Veredicto: CONFIRMADO (bug). Evidencia: `FacturaController.php:746-749` (anular no toca `cuentas_cobrar`).
  - Decisión/estado: ✅ Resuelto (2026-09-29). Mismo candado que ya usaba `destroy()`: si la CxC de la factura ya tiene cobros registrados, `anular()` ahora lo rechaza con un error explícito (antes no lo revisaba). Si no tiene cobros, al anular se marca la CxC como `estado='anulada'`, `saldo=0` (no se borra, igual que la propia factura solo cambia de estado). `'anulada'` no estaba en la lista `$activos` de `CuentaCobrarController`, así que ya queda fuera de los totales de cartera vigente sin tocar nada más ahí.
- [x] **NC/Retención/Guía no revalidan que la factura esté activa** (hallazgo fuera del PDF) — se pueden emitir sobre una factura ya anulada.
  - Veredicto: CONFIRMADO. Evidencia: `NotaCreditoController.php:109` (comentario "deshabilitado temporalmente"), `RetencionController.php:68`, `GuiaRemisionController.php:78-137`.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Se agregó `if ($factura->estado !== 'activa')` en `store()` de las tres (NotaCredito ya lo tenía en `create()` pero no en `store()`, el POST real; Retención no lo tenía en ninguno; Guía tampoco, y además no validaba que `factura_id` existiera). **No se tocó** el otro chequeo deshabilitado de NotaCredito (`estado_sri='autorizada'`, comentado "para pruebas sin SRI") — eso es aparte, de integración SRI, no de este hallazgo. La Guía de remisión sigue permitiendo crearse sin ninguna factura (transporte que no es venta), la validación de estado solo aplica cuando sí se manda un `factura_id`.
- [x] **Doble conteo contable Datafast** (hallazgo fuera del PDF, verificado solo por código — 0 lotes en BD local) — `storeLote` vuelve a acreditar Ventas + IVA que la factura ya registró.
  - Veredicto: CONFIRMADO por código, no confirmado con datos reales. Evidencia: `DatafastController.php:112-134` vs `AsientoService.php:~706`.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Confirmaste que en producción todavía no se ha usado Datafast (0 lotes reales), así que no hizo falta ninguna migración de corrección contable, solo el fix de código: `storeLote()` ya no crea ningún asiento — el lote queda solo como el registro de que ese total está pendiente de depositarse. La única reclasificación contable real (vouchers → banco, neto de comisión/retenciones) sigue ocurriendo en `liquidar()`, que no se tocó porque ya estaba bien.
- [x] **C4** — No existe módulo `asistencia` propio; además la tabla `colaboradores` está vacía en BD local (nadie está vinculado, por eso "no se encuentra asistencia para nadie").
  - Veredicto: CONFIRMADO. Evidencia: `web.php:584,617-623`, tabla `modulos` sin `asistencia`.
  - Decisión/estado: ✅ Resuelto — alcance completo (2026-09-29). Se agregó `asistencia` como módulo propio (id 11), separado de `rrhh`: rutas `/rrhh/asistencia` ahora piden `permiso:asistencia,ver`/`,crear` en vez de `rrhh,*`; el ítem "Asistencia" salió de los hijos del menú RRHH y pasó a ser un ítem de primer nivel propio (así lo ven Vendedor/Bodeguero/Técnico, que no tienen `rrhh` en absoluto). Permisos otorgados: Admin acceso total, Contador solo ver, Vendedor/Bodeguero/Técnico ver+crear (para marcar su propia entrada/salida, sin editar ni eliminar marcaciones ajenas). Migración de datos + `ModuloSeeder`/`PermisoSeeder` actualizados para que una instalación nueva ya nazca así.
  - **No resuelto y fuera de mi alcance**: la tabla `colaboradores` sigue vacía en local — eso es un problema de datos (falta vincular usuarios a colaboradores), no de código ni de permisos. Con 0 colaboradores, nadie puede marcar asistencia aunque ya vea la pantalla, sin importar el permiso. Eso solo se resuelve cargando colaboradores reales y su `usuario_id` (en producción, o con datos de prueba si quieres probarlo localmente).

## Etapa 4 — Reglas de negocio pendientes de decisión del cliente

- [x] **A8** — Regalos: ¿línea a precio 0 que descuenta stock? ¿Tope "dos por factura, y si son más de dos"?
  - Veredicto: (i) PARCIAL — no existía el concepto. (ii) DECISIÓN PENDIENTE.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Checkbox "Es regalo" por línea en Facturas; con él activo la línea se factura a $0 (`FacturaController::store()` fuerza precio y descuento a 0 y **salta** la aprobación de precio bajo costo — es intencional, no un error) y el stock se descuenta igual que cualquier otra línea de producto, sin cambios ahí. Columna nueva `factura_detalles.es_regalo` (migración idempotente) para que quede trazable en reportes futuros. Tope de 2 regalos: al marcar el tercero se avisa con un toast pero se deja continuar, tal como decidiste. No se tocó Proformas/Prefacturas (no se pidió).
- [x] **A9** — Guía de remisión obligatoria en toda factura ("se acepte o no"): ¿al emitir o al despachar? ¿aplica a servicios/Taller?
  - Veredicto: PARCIAL — hoy es opcional, sin validar `factura_id` al crear.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Decisión: advertir, no bloquear. `FacturaController::index()` calcula `sin_guia` (factura activa, con algún producto físico — no solo servicios — y sin guía de remisión activa asociada) y `Facturas/Index.tsx` muestra un ícono de aviso junto al número de factura (mismo patrón que el badge "Sin asiento contable" de Compras). No se toca `store()` de la factura ni se bloquea nada.
- [x] **A11** — Altamira Fix: ¿factura con el RUC de Matriz (como hoy), con otro establecimiento del mismo RUC, o con RUC propio?
  - Veredicto: DECISIÓN PENDIENTE. Hoy sale con RUC y secuencia de Matriz.
  - Decisión/estado: ✅ Decidido (2026-09-29) — se deja como está. Fix sigue facturando con el RUC 1711293454001 de Matriz y la misma secuencia 001-001. Sin cambios de código.
- [x] **B2/B3** — Aceptación manual de crédito/tarjeta y enlace a CxC.
  - Veredicto: PARCIAL — el cobro manual de CxC ya existe; la captura de retención/comisión solo existe en Datafast por lote.
  - Decisión/estado: ✅ Decidido (2026-09-29) — no se cambia por ahora. La factura con tarjeta se sigue dando por cobrada al emitirse, igual que el efectivo. Sin cambios de código; queda documentado por si se retoma más adelante.
- [x] **C6** — "Ocultar módulo Movimientos": ¿Inventario→Traslados o Bancos→Movimientos? ¿para quién?
  - Veredicto: DECISIÓN PENDIENTE (ambigüedad del documento).
  - Decisión/estado: ✅ Resuelto (2026-09-29). Es Inventario→Traslados, oculto para todos menos Admin/Contador/Bodeguero (Vendedor y Técnico ya no lo ven). Implementado con un middleware nuevo y genérico `solo_perfiles:admin,contador,bodeguero` (`app/Http/Middleware/SoloPerfiles.php`) aplicado a las 7 rutas de `/inventario/traslados`, y en el Sidebar el hijo "Movimientos" ahora tiene `perfiles: ['admin','contador','bodeguero']` (mecanismo nuevo, paralelo al `permiso` que ya tenían los hijos como "Proveedores" en C1).
- [x] **D1** — Unir Ingresos y Órdenes de Trabajo en una sola pantalla: ¿un ingreso puede tener varias órdenes?
  - Veredicto: PARCIAL — hoy son casi 1:1 de hecho, pantallas separadas.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Decisión: 1:1 estricto y pantalla única. Se agregó `UNIQUE(ingreso_id)` en `taller_ordenes_trabajo` (migración idempotente, sin datos que corregir en local). `IngresoController::show()` ahora redirige directo a la orden asociada; `OrdenesTrabajo/Show.tsx` (que ya traía cliente/equipo/diagnóstico) se completó con lo que solo vivía en `Ingresos/Show.tsx` — teléfono/email del cliente, imagen del equipo, observaciones, revisión de componentes, y los botones de editar/eliminar/PDF del ingreso. Se borró `Ingresos/Show.tsx` (huérfano, sin referencias). El listado de Ingresos no cambió: su link "ver" sigue apuntando a la misma ruta, que ahora reenvía sola.
- [ ] **D2** — Mano de obra ×1.5: ¿aplica solo a la factura o también al costo interno? ¿aplica a garantías? ¿con qué IVA?
  - Veredicto: CONFIRMADO que no existe hoy ningún multiplicador.
  - Decisión/estado: 🔶 Pendiente — decidiste no implementarlo todavía ("falta definir mejor"). Sin cambios de código.
- [x] **D3** — Garantía genera factura con repuestos a precio de costo: ¿cómo se cobra la mano de obra?
  - Veredicto: PARCIAL — `es_garantia` es un campo huérfano, no se podía liquidar una orden en garantía.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Decisión: mano de obra se cobra normal, solo los repuestos van a costo. `LiquidacionController` ahora acepta liquidar órdenes en estado `garantia` (antes solo `listo`/`entregado`); cuando la orden está en garantía, cada línea de repuesto en la factura usa `costo_unitario` en vez de `precio_venta` (con `[GARANTÍA]` en la descripción), sin tocar el `precio_venta` guardado en el repuesto. `Liquidacion/Show.tsx` (la vista previa antes de confirmar) se actualizó para mostrar el mismo precio que realmente se va a facturar.
- [x] **D4** — Compras externas del taller deben sumar al costo final de la orden.
  - Veredicto: CONFIRMADO que no existía el concepto.
  - Decisión/estado: ✅ Decidido (2026-09-29) — deben ingresar como Compra real en el sistema. **No requirió ningún cambio de código**: el flujo ya existente lo cubre por completo — se registra la Compra con destino la bodega de Taller (`bodega_id` ya es seleccionable libremente en `CompraController`), al confirmarse la recepción el producto queda con stock en esa bodega, y desde ahí `OrdenTrabajoController::agregarRepuesto()` ya solo permite elegir productos con stock ahí (`productosConStockTaller()`). Verificado leyendo el código de ambos controllers, no se necesitó tocar nada.
- [ ] **D5** — "Si compran más de 3 afuera, se hace inventario": regla ambigua.
  - Veredicto: DECISIÓN PENDIENTE (no inventar interpretación).
  - Decisión/estado: 🔶 Pendiente — decidiste no implementarlo todavía, es la opción más segura dado lo ambiguo de la regla. Sin cambios de código.
- [x] **E1** — Selector de tipo de contribuyente en Proveedor (No obligado / Obligado a llevar contabilidad / Sociedad / Contribuyente especial / Gran contribuyente especial).
  - Veredicto: CONFIRMADO que no existía el campo.
  - Decisión/estado: ✅ Resuelto (2026-09-29). Columna `proveedores.tipo_contribuyente` (migración idempotente; los 6 proveedores existentes quedaron en `no_obligado`, el valor recomendado). Selector agregado en **ambos** formularios de proveedor que existen en el sistema — `Personas/Proveedores/Form.tsx` y el modal inline de `Compras/Proveedores/Index.tsx` — y en ambas validaciones de backend (`ProveedorRequest` para Personas, validación inline en `Compras\ProveedorController`). Catálogo centralizado en `Proveedor::TIPOS_CONTRIBUYENTE`. No se usa todavía en el ATS (no se pidió).
- [x] **F1** — Dashboard con vista mensual/trimestral/anual + descarga PDF/Excel.
  - Veredicto: CONFIRMADO que no existía (el dashboard devuelve ceros fijos).
  - Decisión/estado: ✅ Resuelto (2026-09-29). Decisión: Excel/PDF del resumen de ventas del período (no XML del SRI). Se agregó una tarjeta nueva "Resumen de ventas" en el Dashboard, con selector Mensual/Trimestral/Anual y botones PDF/Excel en btn-group al final (convención del proyecto). Backend: `DashboardController::resumenVentas/resumenVentasPdf/resumenVentasExcel`, calculando rangos reales por período (mes/trimestre/año calendario) sobre `facturas` reales — **no se tocaron** los widgets existentes (`VentasDia`, `MetaMes`, etc.), que siguen en ceros porque arreglarlos es un problema aparte, no pedido en esta ronda. Probado de punta a punta contra la BD local: JSON, PDF (879 KB) y Excel generados correctamente con la única factura real.

## Etapa 5 — Mejoras / bajo impacto inmediato

- [ ] **A5** — Cédula/RUC: la factura ya acepta texto libre, pero no hay selector de tipo de identificación y el cliente nuevo queda con tipo RUC por defecto.
  - Veredicto: PARCIAL (mayormente ya funciona, falta el selector).
  - Decisión/estado: 
- [ ] **C7** — Colores distintos por empresa (Import en azul): no existe ninguna infraestructura de tema por empresa hoy.
  - Veredicto: CONFIRMADO que no existe.
  - Decisión/estado: 
- [ ] **G1** — 13 grupos de productos duplicados por nombre (27 productos de 1646); 5 saldos negativos sin movimiento que los explique; kárdex casi vacío (16 movimientos) para reconciliar.
  - Veredicto: PARCIAL. Requiere revisión funcional caso por caso antes de fusionar nada (no tocar `productos` en automático).
  - Decisión/estado: 

---

## Registro de cambios

*(Cada vez que se decida o implemente algo, se agrega una línea aquí con fecha, ítem y qué se hizo.)*

- 2026-09-26 — Fase 1 completada: auditoría de los 39 ítems del documento contra código y BD local. Ningún archivo del proyecto modificado en esta fase.
- 2026-09-27 — **A3 resuelto**: `ResuelveBodegasFijas.php` reescrito para resolver bodega Principal/Reservas por `empresa_id`+`tipo` en vez de nombre fijo; eliminada copia duplicada en `FacturaController.php`. Archivos: `app/Http/Controllers/Ventas/Concerns/ResuelveBodegasFijas.php`, `app/Http/Controllers/Ventas/FacturaController.php`.
- 2026-09-27 — **A1 resuelto**: precio bloqueado a solo lectura en Facturas/Proformas/Prefacturas; servidor ignora el precio del request y usa `productos.pvp`. Archivos: `resources/js/Pages/Ventas/{Facturas,Proformas,Prefacturas}/Form.tsx`, `app/Http/Controllers/Ventas/{FacturaController,ProformaController,PrefacturaController}.php`.
- 2026-09-27 — **A4 descartado**: no se implementa botón "+"; se capacita al usuario (Enter en la última fila de pago agrega una fila). Sin cambios de código.
- 2026-09-29 — **Etapa 2 completa (A2, clienteGuardar, C1, C2, C3, inconsistencia de datos)**. Resumen de archivos:
  - `app/Http/Controllers/Ventas/{FacturaController,ProformaController,PrefacturaController}.php` — A2 (costo oculto) y `clienteGuardar` (permiso).
  - `resources/js/Pages/Ventas/{Facturas,Proformas,Prefacturas}/Form.tsx` — A2 en frontend (`bajo_costo` en vez de `costo`).
  - `routes/web.php` — C1 (`/personas/proveedores` con permiso de Compras) y C2 (`/reportes/sri/*` con permiso de Reportes).
  - `resources/js/Components/shared/Sidebar.tsx` — C1 (hijo "Proveedores" filtrado por permiso propio), C2/C3 (`dashboard`/`reportes` fuera de `MODULOS_SIN_PERMISO`).
  - `app/Support/LandingRoute.php` (nuevo) y `app/Http/Controllers/Dashboard/DashboardController.php` — C3 (redirect al primer módulo visible).
  - `database/seeders/PermisoSeeder.php` y `database/migrations/2026_09_29_124344_corregir_permisos_vendedor_bodeguero.php` — datos: Vendedor sin `reportes`, Bodeguero sin `dashboard`/`ventas`/`compras`/`taller`, alineado entre Matriz e Import. Migración corrida en BD local.
  - Verificado: `php -l` en todos los PHP tocados, `tsc --noEmit` sin errores nuevos, `route:list` confirmando los middlewares esperados.
  - Pendiente: probar manualmente en `http://ALTAMIRA.test` con los 3 perfiles (Vendedor, Bodeguero, Técnico) antes de dar por cerrado. No se ha hecho commit todavía.
- 2026-09-29 — **Etapa 3 completa (B1, A7, NC/Retención/Guía, Datafast, C4)**. Decisiones tuyas: Datafast sin reconocer venta (confirmado, sin datos de producción que corregir); C4 con alcance completo (módulo propio, no solo diagnóstico). Resumen de archivos:
  - `app/Http/Controllers/Ventas/FacturaController.php` — B1 (banco/num_cheque/dias_credito) y A7 (candado de cobros + cierre de CxC al anular).
  - `app/Http/Controllers/Ventas/{NotaCreditoController,RetencionController,GuiaRemisionController}.php` — validación de factura activa.
  - `app/Http/Controllers/Bancos/DatafastController.php` — `storeLote()` sin asiento.
  - `routes/web.php` — grupo `rrhh/asistencia` separado del resto de RRHH, con su propio middleware `permiso:asistencia,*`.
  - `resources/js/Components/shared/Sidebar.tsx` — "Asistencia" como ítem de primer nivel, fuera de los hijos de RRHH.
  - `database/seeders/{ModuloSeeder,PermisoSeeder}.php` y `database/migrations/2026_09_29_135544_crear_modulo_asistencia.php` — módulo `asistencia` (id 11) + permisos por perfil. Migración corrida en BD local.
  - Verificado: `php -l` en los 10 PHP tocados, `tsc --noEmit` sin errores nuevos, `route:list` confirmando `/rrhh/asistencia` con el nuevo permiso, consulta SQL confirmando módulo y permisos creados.
  - Pendiente: probar manualmente en `http://ALTAMIRA.test`. No se ha hecho commit todavía. Nota aparte: `colaboradores` sigue vacía en local, así que el flujo de marcar asistencia en sí no se pudo probar de punta a punta (ver nota en C4 arriba).
- 2026-09-29 — **Etapa 4 completa** (A8, A9, A11, B2/B3, C6, D1, D3, D4, E1, F1 implementados o decididos sin cambio; D2 y D5 quedaron pendientes por decisión tuya de no implementarlos todavía; A10 y C5 siguen sin poder verificarse desde este entorno). Resumen de archivos nuevos/tocados:
  - `app/Http/Middleware/SoloPerfiles.php` (nuevo) + `bootstrap/app.php` — middleware genérico para gates por perfil (C6, y reutilizable a futuro).
  - `routes/web.php` — `/inventario/traslados` con `solo_perfiles`; nuevas rutas `dashboard/ventas-resumen*` (F1).
  - `resources/js/Components/shared/Sidebar.tsx` — hijo "Movimientos" con `perfiles` (C6).
  - `app/Http/Controllers/Ventas/FacturaController.php` — A8 (regalos) y A9 (`sin_guia` en el listado).
  - `resources/js/Pages/Ventas/Facturas/{Form,Index}.tsx` — checkbox de regalo (A8) y badge de guía faltante (A9).
  - `database/migrations/2026_09_29_144311_agregar_es_regalo_a_factura_detalles.php` + `app/Models/FacturaDetalle.php` — columna `es_regalo`.
  - `app/Http/Controllers/Taller/{IngresoController,OrdenTrabajoController,LiquidacionController}.php`, `resources/js/Pages/Taller/OrdenesTrabajo/Show.tsx`, `resources/js/Pages/Taller/Liquidacion/Show.tsx`, `database/migrations/2026_09_29_143649_agregar_unique_ingreso_id_a_ordenes_trabajo.php` — D1 (fusión Ingresos+Órdenes, UNIQUE ingreso_id) y D3 (garantía a costo). Se borró `resources/js/Pages/Taller/Ingresos/Show.tsx` (huérfano).
  - `database/migrations/2026_09_29_142459_agregar_tipo_contribuyente_a_proveedores.php`, `app/Models/Proveedor.php`, `app/Http/Requests/Personas/ProveedorRequest.php`, `app/Http/Controllers/Compras/ProveedorController.php`, `resources/js/Pages/Personas/Proveedores/Form.tsx`, `resources/js/Pages/Compras/Proveedores/Index.tsx`, `resources/js/types/index.ts` — E1 (tipo de contribuyente).
  - `app/Http/Controllers/Dashboard/DashboardController.php`, `app/Exports/VentasResumenExport.php` (nuevo), `resources/views/pdf/dashboard-ventas-resumen.blade.php` (nuevo), `resources/js/Pages/Dashboard/Index.tsx` — F1 (resumen de ventas por período + export PDF/Excel), probado de punta a punta contra la BD local.
  - D4: sin cambios de código — se confirmó leyendo el código que el flujo ya existente (Compra con destino Bodega Taller → recepción → repuesto) ya cumple la decisión.
  - A11, B2/B3: decisiones documentadas, sin cambios de código (se deja el comportamiento actual).
  - Verificado: `php -l` en todos los PHP tocados, `tsc --noEmit` sin errores nuevos, pruebas manuales por `tinker` de `resumenVentas`/PDF/Excel del dashboard contra datos reales. Pendiente: probar manualmente en el navegador con los 3 perfiles. No se ha hecho commit todavía.

## Revisión punto por punto (2026-10)

- [x] **Crédito con autorización** (implementado, pendiente de prueba en pantalla): si el cliente no tiene crédito habilitado o supera su cupo, la factura pide el código de contador/administrador (tipo `credito_excedido`). Solo `super_admin`, `admin` y `contador` pueden autorizar. Cambios en `FacturaController::store()`, `AprobacionController::validar()` y `Ventas/Facturas/Form.tsx`.
- [ ] **Aceptación de tarjetas por lote** — PENDIENTE DE DECISIÓN. Ya existe la liquidación de lote Datafast con su asiento a banco; una aceptación por factura también generaría ese asiento (doble contabilización). Falta decidir si reemplaza la liquidación de lote y qué pasa en modo "banco". Sin cambios de código.
- [x] **C7 — Colores por empresa** (2026-10, revisado): en lugar de cambiar todo el sistema, solo cambia la **barra superior (header)**: Altamira Import la tiene en azul y Matriz queda como estaba. Cambio en `resources/js/Components/shared/Topbar.tsx`. Se revirtió el cambio global de colores que se había hecho.
