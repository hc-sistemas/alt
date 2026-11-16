# Qué falta para habilitar la venta y compra de los productos

**Fecha:** 10 nov 2026
**Fuente:** `Inventario Altamira categoría Final 2026.xlsx` (cliente) comparado con la BD `altamira` y con las 20 subcategorías contables (PC-CAT-01 a 20).
**Estado:** no se modificó ninguna información en la BD; esto es solo el análisis.

---

## 1. Por qué existe el bloqueo

Desde el 4 nov (DEC-012 y DEC-030) las cuentas contables de un producto salen de su **subcategoría**: inventario, venta y costo de ventas. Un producto sin subcategoría con cuentas **no se puede facturar, comprar, ajustar ni devolver**. El sistema lo detiene con el mensaje:

> «No se puede contabilizar: N producto(s) no tienen subcategoría con cuentas contables…»

Hoy, de 1.672 productos, unos 1.209 están en ese caso.

## 2. Resumen: qué cubre el Excel del cliente

| Grupo | Productos | ¿Se habilita con el Excel? |
|---|---:|---|
| A. Caen en subcategorías que ya tienen cuentas | ~1.398 | **Sí**, aplicando el Excel |
| B. Subcategorías nuevas sin cuentas | 242 | No: falta definir cuentas |
| C. «Inventario sin clasificar» | 24 | No: falta decidir destino |
| D. «Servicios» (categoría nueva) | 12 | No: falta mapear |
| E. Productos de la BD que el Excel no trae | 58 | No: falta respuesta del cliente |
| F. Productos del Excel que no están en la BD | 62 | Hay que crearlos |

El Excel trae categoría y subcategoría para los 1.676 productos y no tiene códigos repetidos.

---

## 3. Detalle por grupo, con ejemplos

### Grupo A — Ya tienen cuentas (~1.398)

Basta aplicar el Excel. Ejemplo:

| Código | Producto | Subcategoría | Inventario | Venta | Costo |
|---|---|---|---|---|---|
| DJS.000241 | INPULSE DJCONTROL MIX ULT | Controladores de DJ (PC-CAT-06) | 1.01.04.02.01 | 4.01.02.01 | 5.01.02.01 |

Incluye Cables, Repuestos de Taller, Computadoras, PISOS LED, Regalos y las subcategorías ya definidas de DJ, Iluminación, Parlantes y Video. Las 20 subcategorías tienen sus tres cuentas completas y activas (las dos de Regalos no tienen cuenta de venta a propósito: no se venden).

### Grupo B — 5 subcategorías nuevas sin cuentas (242 productos)

Esas subcategorías **no existen** en el sistema ni entre las 20 del documento 01 §6. Sin cuentas, los productos siguen bloqueados aunque se aplique el Excel.

| Subcategoría nueva | Productos | Con stock en ERP | Ejemplo |
|---|---:|---:|---|
| Micrófonos | 83 | 30 | ACC.000421 CUELLO DE MICROFONO |
| Láseres y efectos LED | 58 | 12 | IML.000025 LASER R/G 250MW 3D |
| Máquinas de efectos y pirotecnia fría | 50 | 18 | ACC.000491 LIQUIDO DE HUMO 4.5 LT |
| Audífonos | 35 | 5 | AYR.000038 DJPRO300 AUDIFONO EURODJ |
| Amplificadores y sistemas inalámbricos de audio | 16 | 6 | SON.000040 PM1 IN-EAR / MONITOR PERSONAL |

**Ejemplo del bloqueo:** ACC.000421 CUELLO DE MICROFONO tiene stock 2. Al intentar venderlo, el sistema responde que no tiene subcategoría con cuentas.

**Qué falta (decisión del contador, por cada subcategoría), una de dos:**
1. **Crear la subcategoría con cuentas propias.** El contador indica el código de cada cuenta de inventario (1.01.04.xx), venta (4.01.xx) y costo (5.01.xx). Si no existen en el plan de cuentas, hay que crearlas.
2. **Asimilarla a una existente.** Ejemplo: Micrófonos → Parlantes Activos (PC-CAT-01), Audífonos → Controladores de DJ (PC-CAT-06). Se cambia la subcategoría en el Excel y no se crean cuentas nuevas.

Plantilla a completar:

| Subcategoría | ¿Cuentas propias o a cuál existente? | Inventario | Venta | Costo |
|---|---|---|---|---|
| Micrófonos | | | | |
| Láseres y efectos LED | | | | |
| Máquinas de efectos y pirotecnia fría | | | | |
| Audífonos | | | | |
| Amplificadores y sistemas inalámbricos de audio | | | | |

### Grupo C — «Inventario sin clasificar» (24 productos)

Esa categoría no existe. Según las reglas vigentes solo Computadoras y PISOS LED pueden quedar «sin clasificar» (cuentas 1.01.04.09 / 4.01.09 / 5.01.08). Ejemplos:

| Código | Producto |
|---|---|
| ACC.000574 | BX-AA2B PILAS ALKALINAS |
| AYR.000292 | DC-S033 TALADRO |
| AYR.000363 | LIQUIDO DE DESINFECCION 4LITRO |
| SON.000173 | PANEL ACUSTICO PIRAMIDE 2023 |
| VID.000016 | TV LED 32INCH |
| VID.000018 | PROYECTOR X412 |

**Qué falta:** una de dos.
1. El cliente asigna a cada uno una categoría y subcategoría reales. Ejemplo: televisores y proyectores a Pantallas LED y Video; pilas y cargadores a Cables, Conectores y Estructuras.
2. El contador autoriza que esta categoría use las cuentas «sin clasificar», como Computadoras. Es una excepción a la regla actual.

### Grupo D — «Servicios» (12 productos)

Categoría que no existe como tal. Los servicios no mueven inventario; los productos de tipo servicio ya venden en `cta_ingresos_servicios`.

| Subcategoría del Excel | Productos | Ejemplos |
|---|---:|---|
| Servicio técnico | 4 | SER.000001 SERVICIO TECNICO; SER.000002 HONORARIO REPARACION DJ |
| Eventos (otros ingresos) | 8 | SER.000006 SERVICIO DISCOMOVIL 6 HORAS; SER.000012 ALQUILER TARIMA |

**Qué falta:**
- Crearlos como tipo «servicio».
- Confirmar que Servicio técnico usa `cta_ingresos_servicios`.
- Confirmar que Eventos usa 4.03.02 (otros ingresos), igual que la factura de evento (PC-VEN-16, DEC-050). Hoy ese destino se activa con una casilla en la factura, no por producto, así que hay que decidir si se hace por producto.

### Grupo E — En la BD pero no en el Excel (58 productos)

Quedarían sin clasificar si no se resuelve. Distribución actual por categoría:

| Categoría actual | Productos |
|---|---:|
| Artículos y Equipos de DJ (sin subcategoría) | 17 |
| Iluminación Profesional (sin subcategoría) | 13 |
| ACCESORIOS (por reclasificar) | 8 |
| Parlantes y sistemas de sonido (sin subcategoría) | 6 |
| Otras (ya con subcategoría o categoría sin subcategorías) | 14 |

Ejemplos: ILM.000004 BOOM FX-DERBY FLASH STROB, ILM.000022 BEAM 7R 3IN1 + RACK RED, AYR.000070 REPUESTO BEAM 17R FUENTE.

Varios parecen ser los mismos productos con otro código en el Excel (por ejemplo, BEAM 7R 3IN1 + RACK RED aparece como IML.000194). Podrían haberse renumerado.

**Qué falta:** que el cliente indique si son bajas, renumerados o duplicados. Los dados de baja se inactivan y los renumerados se unifican, sin perder el kárdex.

### Grupo F — En el Excel pero no en la BD (62 productos)

Son productos nuevos: REG.000001 a 4 (regalos), SER.000001 a 12 (servicios), IML.000176 a 194 (iluminación), DJS.000241 a 245, CMP.000027 a 30, entre otros.

**Qué falta:** confirmar que se crean desde este Excel, con IVA 15 % (regla vigente) y el tipo correcto (producto o servicio).

---

## 4. Detalles menores

- **Nombre de categoría distinto:** el Excel dice «Parlantes y sistema de sonido» y la BD «Parlantes y sistemas de sonido». Se homologa al aplicar.
- **Inventario físico al 31/10:** el Excel trae cantidad física contra cantidad del ERP. Hay 134 productos con diferencia, por ejemplo ACC.000449 (físico 10, sistema 3). Es un ajuste de inventario aparte, no parte de la clasificación, y requiere su propio asiento y aprobación.

---

## 5. Qué se necesita y de quién

| # | Qué | Quién | Desbloquea |
|---|---|---|---:|
| 1 | Cuentas (o asimilación) de las 5 subcategorías nuevas | Contador | 242 |
| 2 | Destino de los 24 «sin clasificar» | Cliente / Contador | 24 |
| 3 | Mapeo de los 12 servicios | Contador | 12 |
| 4 | Qué pasa con los 58 que el Excel no trae | Cliente | 58 |
| 5 | Visto bueno para crear los 62 nuevos | Cliente | 62 |
| 6 | Aplicar el Excel a los ~1.398 con cuentas | Desarrollo (puede hacerse ya, en simulación primero) | ~1.398 |

## 6. Qué haré del lado del sistema cuando lleguen las respuestas

1. Comando de carga que **simula por defecto** y solo aplica con confirmación: asigna categoría y subcategoría, crea los productos nuevos y deja un informe de lo que quede bloqueado.
2. Crear las subcategorías nuevas con sus cuentas (migración idempotente), si el contador define cuentas propias.
3. Pantalla de solo lectura con las subcategorías y sus tres cuentas en una sola tabla, para que el contador las revise. Hoy las cuentas solo se ven producto por producto (Inventario → Productos → pestaña Contabilidad).
4. Volver a contar los productos sin cuentas hasta llegar a cero.

## 7. Cómo se verifica al terminar

- El filtro «Sin subcategoría» de Inventario → Productos debe mostrar 0.
- Se factura y se compra un producto de cada subcategoría y el asiento sale cuadrado con las cuentas esperadas.
- Un producto sin clasificar (de prueba) sigue bloqueado con el mensaje de error.
