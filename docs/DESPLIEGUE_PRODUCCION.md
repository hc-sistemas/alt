# Despliegue a producción — ERP Altamira (Oct–Nov 2026)

Guía paso a paso del paso de local a producción de todo lo implementado en los módulos A a K. **Nada de esto se ha subido
ni ejecutado en producción.** Ejecutar en orden. Cada paso de datos se simula primero.

> Reglas: solo se toca la BD `altamira` (el Postgres del VPS es compartido: nunca `lideres` ni `sae_alt`). No hay commits ni
> pushes hechos por el asistente: el equipo decide cuándo hacer commit y push.

## 0. Qué se verificó antes de escribir esta guía

Se clonó una copia de producción previa (`altamira_prod_pre`: 1.646 productos, 2 asientos, migraciones hasta el 05/10) a una
base de ensayo y se corrió **toda la secuencia** de abajo. Resultado:

- Las 24 migraciones pendientes corren sin error (la del plan de cuentas necesitó un ajuste, ver §5).
- Los asientos siguen cuadrados; el plan queda con 179 cuentas; ningún parámetro contable apunta a una cuenta que no acepta asientos.
- `inventario:cargar-excel --aplicar` y `altamira:actualizar-iva-productos --aplicar` funcionan y **una segunda corrida no encuentra nada que cambiar**.

Lo que NO se pudo comprobar en ensayo: la copia no tiene facturas, compras ni nómina reales; las pruebas funcionales de cada
módulo se hicieron en local con datos de prueba y rollback.

## 1. Antes de empezar

> **Regla de oro:** después de CADA paso se verifica antes de seguir. Si un resultado no es el esperado, **parar** y no continuar.

### 1.0 Ensayo previo en tu PC con una copia FRESCA de producción (recomendado, sin riesgo)

Es lo que se hizo con la copia del 11/10 y detectó un error real. Repetirlo con un respaldo de hoy confirma que nada cambió:

```bash
# En el servidor: respaldo de la base altamira (formato custom) y traerlo a tu PC
pg_dump -U postgres -d altamira -F c -f altamira_hoy.dump
# En tu PC (psql de PostgreSQL 12): crear una base de ensayo y restaurar ahí. NUNCA restaurar sobre «altamira» de producción.
createdb -U postgres altamira_ensayo
pg_restore -U postgres -d altamira_ensayo --no-owner altamira_hoy.dump
# Revisión de solo lectura y migración SOLO sobre la copia:
DB_DATABASE=altamira_ensayo php artisan altamira:preflight-despliegue
DB_DATABASE=altamira_ensayo php artisan migrate --force
DB_DATABASE=altamira_ensayo php artisan altamira:preflight-despliegue
```
Esperado: antes, 24 pendientes y «sin bloqueos»; después, 0 pendientes. Si el preflight muestra un **BLOQUEO**, no se despliega hasta resolverlo (avisar).

### 1.1 Revisión de solo lectura en producción

```bash
php artisan altamira:preflight-despliegue
```
Solo lee. Debe decir «La base es altamira», listar las migraciones pendientes y terminar en «sin bloqueos». Los **AVISO** se leen; los **BLOQUEO** detienen todo. Si dice que la base no se llama `altamira`, **parar**.

### 1.2 Pasos previos

1. **Respaldo completo de la BD `altamira`** (solo esa):
   `pg_dump -U postgres -d altamira -F c -f altamira_antes_oct2026.dump`
   Guardarlo fuera del servidor y comprobar que no está vacío.
2. Poner la app en mantenimiento: `php artisan down`.
3. Subir el Excel del inventario al servidor **sin pasar por git** (el repositorio es público):
   `docs/INVENTARIO ALTAMIRA JUNTO FINAL.xlsx` (o cualquier ruta; el comando recibe la ruta como argumento).

## 2. Código

```bash
git pull origin produccion            # rama de producción
composer install --no-dev --optimize-autoloader
npm ci && npm run build               # public/build no está en git: se compila en el servidor
```

## 3. Migraciones

```bash
php artisan altamira:preflight-despliegue   # de nuevo, justo antes de migrar: debe seguir «sin bloqueos»
php artisan migrate --pretend               # revisar el SQL, opcional
php artisan migrate --force
php artisan migrate:status                  # no debe quedar ninguna en "Pending"
php artisan altamira:preflight-despliegue   # debe mostrar 0 pendientes y «sin bloqueos»
```

Las 24 pendientes, en orden (las de 2026_10_12 y 2026_10_13 ya existían en el repo):

| Migración | Qué hace |
|---|---|
| `2026_10_24_100000_fijar_limites_descuento_dec035` | Límites de descuento por rol (DEC-035) |
| `2026_10_24_110000_renombrar_centro_costo_matriz_a_uio` | Centro de costo MATRIZ pasa a «Altamira UIO» |
| `2026_10_24_120000_parametros_contables_compartidos` | Parámetros contables compartidos entre empresas |
| `2026_10_24_130000_cargar_plan_cuentas_final` | **Carga el plan de cuentas V4** (ver §5) |
| `2026_10_24_140000_nombre_comercial_y_etiqueta_empresas` | Nombre comercial «Altamira Light & Sound» + etiqueta UIO/Import |
| `2026_10_24_150000_renombrar_centro_costo_fix` | Nombre del centro FIX |
| `2026_10_25_100000_parametros_y_subcategorias_modulo_b` | Parámetros nuevos, cuenta 4.03.02, subcategorías PC-CAT-01..20 |
| `2026_10_25_110000_parametros_cuentas_por_banco` | Un parámetro por cuenta bancaria |
| `2026_10_25_120000_conciliacion_sentido_y_observacion` | Conciliación bancaria |
| `2026_10_25_130000_cierres_caja_conteo_y_resumen` | Cierre de caja con conteo por denominación |
| `2026_10_25_140000_tarjetas_procesadores_y_liquidacion` | Tarjetas y procesadores |
| `2026_10_25_150000_inventario_categorias_subcategorias_y_bodegas` | Categorías oficiales y bodegas en ambos RUC (no toca `productos`) |
| `2026_10_25_160000_compras_costo_tipo_importacion` | Costos de importación |
| `2026_10_25_170000_factura_pagos_banco_referencia_tarjeta` | Banco/tarjeta en pagos de factura |
| `2026_10_25_180000_rrhh_departamentos_y_ficha_colaborador` | Departamentos oficiales y ficha del colaborador |
| `2026_10_26_100000_taller_parametros_turno_fotos_garantia` | Taller: parámetros, turno, fotos, garantía, condiciones |
| `2026_11_03_100000_submodulos_inventario_en_matriz` | Submódulos de Inventario en Permisos |
| `2026_11_03_110000_submodulos_acciones_sensibles_en_matriz` | Acciones sensibles asignables desde Permisos |
| `2026_11_03_120000_limites_descuento_solo_cero_o_maximo` | Opción «solo 0 o el máximo» por perfil |
| `2026_11_04_100000_eliminar_parametros_genericos_por_subcategoria` | Quita los parámetros genéricos de venta/costo/inventario/gasto: las cuentas salen de la subcategoría del producto |
| `2026_11_04_110000_eliminar_parametro_bancos_locales` | Quita `cta_bancos_locales` (Banco Pichincha por defecto): el banco se elige en cada cobro o pago |
| `2026_11_04_120000_movimientos_bancarios_fecha_conciliacion_comentario` | Agrega fecha de conciliación y comentario al libro de movimientos bancarios |
| `2026_11_04_130000_eliminar_config_caja_y_banco_transferencia_por_defecto` | Borra las claves de caja de efectivo por defecto y banco fijo de transferencias |
| `2026_11_04_140000_tarjetas_lote_ventas_intereses_iva_comision` | Tabla de ventas por lote de tarjetas, intereses del diferido e IVA configurable de la comisión por procesador |
| `2026_11_04_150000_cheques_recibidos_retenciones_evento` | Cheques recibidos en cartera, retenciones en el cobro de CxC y marca de factura de evento |
| `2026_11_04_160000_nominas_anulacion_con_reverso` | Columnas para anular nóminas con reverso y restituir préstamos |
| `2026_11_16_100000_inventario_plan_v51_subcategorias_nuevas` | Plan V5.1 (7 cuentas nuevas, elimina 4.03.02 → 4.04) y subcategorías PC-CAT-21 a 28 (Micrófonos, Audífonos, Láseres, Máquinas de efectos, Amplificadores, Inventario sin clasificar, Servicio técnico, Eventos). Ver §10 |

## 4. Datos (simular primero, aplicar después)

> **Actualización 16/11/2026:** el inventario inicial se carga ahora con la **carga limpia** (§10), que reemplaza a 4.1 (`inventario:cargar-excel`) y 4.3 (reclasificación): el Excel «Corregidos» ya trae categoría y subcategoría de cada producto. Quedan vigentes 4.2 (IVA) y el borrado de datos de prueba.

```bash
# 4.1 Inventario desde el Excel final: altas, marcas, nombres y precios. NO toca stock ni categorías de existentes.
php artisan inventario:cargar-excel "/ruta/INVENTARIO ALTAMIRA JUNTO FINAL.xlsx"            # simula
#   Esperado con 1.646 productos: 26 nuevos, 1.431 con cambios, 213 marcas nuevas, 1 fila omitida (código inválido).
#   Revisar el CSV de detalle (la ruta se imprime) y luego:
php artisan inventario:cargar-excel "/ruta/INVENTARIO ALTAMIRA JUNTO FINAL.xlsx" --aplicar
#   Deja un respaldo JSON de lo modificado en storage/app/private/respaldos/.

# 4.2 IVA vigente 15 % para los productos que siguen en 12 %
php artisan altamira:actualizar-iva-productos                 # simula (esperado: 1.644 productos)
php artisan altamira:actualizar-iva-productos --aplicar       # deja un respaldo CSV en storage/app/private/backups/
```

Si las cifras de la simulación no coinciden con lo esperado, **parar** y revisar: puede que producción haya cambiado desde la copia usada para el ensayo.

### 4.3 Clasificación de productos (OBLIGATORIA antes de abrir a los usuarios)

El sistema contabiliza por la subcategoría del producto y es **estricto**: un producto sin subcategoría con cuentas no se puede
facturar ni comprar. Al 04/11/2026 unos 1.209 productos están así (el Excel del cliente no trae categorías).

```bash
php artisan inventario:sugerir-reclasificacion                         # genera docs/Oct2026/reclasificacion_productos.csv
# El contador o bodega revisan el CSV: SI en «confirmar» (pueden corregir la categoría/subcategoría sugeridas)
# — o, más cómodo, rellenan docs/Oct2026/Productos_por_clasificar.xlsx (listas desplegables) y se carga con:
#   php artisan inventario:aplicar-reclasificacion docs/Oct2026/Productos_por_clasificar.xlsx --aplicar
php artisan inventario:aplicar-reclasificacion                         # simula con las confirmaciones del CSV
php artisan inventario:aplicar-reclasificacion --aplicar               # aplica (respaldo en storage/app/private/respaldos)
# Opcional, primera pasada: dar por buenas las ~428 sugerencias automáticas (el resto, ~786, se clasifica a mano)
php artisan inventario:aplicar-reclasificacion --aceptar-sugerencias --aplicar
```
Verificación: Inventario → Productos no debe mostrar productos «sin subcategoría»; una factura de prueba de un producto clasificado debe generar su asiento con la cuenta de su subcategoría.

## 5. Plan de cuentas V4 — qué esperar

La migración del plan **se detiene con un error** si una cuenta que cambia de significado ya tiene asientos, compras o bancos
(para no reinterpretar historia en silencio). En la copia de producción apareció un caso: `1.01.02.01 «Banco Local - Cuenta
Corriente»` pasa a ser cuenta **grupo** en el plan final y tenía 1 línea de asiento (`AS-2026-0002`, préstamo a empleado, de
prueba). La migración ahora resuelve ese caso sola: si la cuenta solo cambia a «grupo», solo tiene líneas de asiento y el plan
final le da **exactamente una** hija de detalle, mueve esas líneas a la hija (aquí `1.01.02.01.01 Banco Pichincha`) y lo deja en
el log. Cualquier otro caso sigue deteniéndose con un mensaje claro: en ese caso **no** forzarla, revisar los movimientos.

## 6. Después de migrar

1. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
2. `php artisan up`
3. **Abrir los períodos contables** del mes en curso para cada empresa (Contabilidad → Ejercicios). Sin período abierto el sistema
   bloquea facturar, comprar, pagar, etc. (es intencional).
4. **Cola de trabajos** (solo el ZIP de roles de pago de Nómina la usa): `php artisan queue:work` bajo Supervisor/systemd.
5. **Programador de tareas**: cron cada minuto `* * * * * php /ruta/artisan schedule:run` (alertas de CxP, vouchers, nómina y limpieza de exportaciones).
6. Revisar **Configuración → Permisos**: aparecen submódulos nuevos (indentados bajo su módulo). Los valores iniciales conservan lo que
   cada rol podía hacer; Admin y Super Admin pueden todo siempre. Al marcar una acción de un submódulo se marca solo «Ver» del módulo padre.
7. Revisar **Taller → Configuración**: texto de las condiciones del servicio e IVA del servicio técnico (15 % por omisión).

## 7. Verificación rápida (humo)

- Iniciar sesión con Super Admin y con un perfil no administrador; comprobar que cada uno ve solo lo que le corresponde.
- Productos: buscar un código nuevo del Excel (p. ej. `SON.000314`) y confirmar marca, precio e IVA 15 %.
- Facturar un producto: el IVA debe salir al 15 %.
- Contabilidad → Plan de cuentas: 179 cuentas, `1.01.02.01.01 Banco Pichincha` acepta asientos.
- Taller: crear un ingreso de prueba, imprimir el documento y borrarlo.

## 8. Reversa

- Código: volver al commit anterior y recompilar.
- Base de datos: restaurar el respaldo del §1 (las migraciones no tienen `down`). Es la reversa limpia y completa.
- Solo datos de inventario: el JSON de `storage/app/private/respaldos/` y el CSV de IVA de `storage/app/private/backups/` guardan los valores anteriores.

## 9. Pendiente de definir antes de abrir a los usuarios (no bloquean el despliegue)

- Borrado de datos de prueba: `php artisan altamira:limpiar-datos-prueba` (simula por defecto; `--ejecutar` pide escribir la frase de confirmación). **Ejecutado solo en la BD local el 03/11/2026 (121 filas); en producción falta**, y debe correrse después de las migraciones y antes de abrir a los usuarios, con respaldo previo (`pg_dump`). No toca saldos de inventario ni la numeración de comprobantes (`secuenciales`): revisar que el siguiente número de factura sea el correcto.
- Existencias del inventario y asiento de apertura: se cargan con la carga limpia (§10). Falta el asiento de los activos fijos ($6.040,96, archivo `Activos_Fijos_ERP_Altamira_UIO.xlsx` pendiente del cliente).
- Reclasificación de los productos que ya existían a las subcategorías oficiales (`docs/Oct2026/reclasificacion_productos.csv`).
- El código `LAS 2 CAJAS VACIAS` del Excel (REPUESTO MIC DINAMICO TH-29, DEZIBEL) hay que pedirlo al cliente.
- Decisiones del cliente aún abiertas: DEC-011, 012, 017, 032, 033, 034, 038, 039, 041, 045, 047, 048, 049, 052, 053, 054.
- Decidir si se carga a los colaboradores faltantes y el SBU (460 en el código frente a 482 en el ejemplo del documento).

## 10. Habilitación del inventario — carga limpia al 31/10/2026 (decisión de Edwin del 12/11/2026)

Documentos del cliente (carpeta `docs/Oct2026/Corregidos/`, **no van a git**, se pasan por `scp`): `Inventario_Altamira_ERP_Carga.xlsx`
(1.676 filas: 1.664 productos + 12 servicios SER), `Informe_productos_bloqueados_RESUELTO.xlsx`, `Decisiones_pendientes_contador.xlsx`,
`Plan de cuentas V5.1.xlsx`.

**Qué hace `php artisan inventario:carga-limpia <excel> [--aplicar]`** (simula por defecto; una sola transacción; respaldo JSON previo
en `storage/app/private/carga-limpia/`; CSV de detalle de cada corrida):

1. Borra los 13 códigos dados de baja (sin ajuste ni asiento).
2. Renumera 45 productos (columna «Código anterior»; conserva el id; el código viejo queda en la descripción).
3. Actualiza los productos existentes (nombre, marca, descripción, referencia, unidad, tipo, precios, costo, **categoría y subcategoría**) y crea los nuevos (IVA 15 %); los SER se crean como tipo `servicio`, sin stock.
4. Deja en cero todo el stock y kárdex del piloto (sin asientos).
5. Carga el saldo inicial al 31/10/2026 en la bodega Principal (cantidad física × costo unitario).
6. Registra **un** asiento de apertura: Debe → cuentas de inventario por subcategoría; Haber → 3.01.01 Capital Neto (`APERTURA_INVENTARIO`).
7. Desactiva la categoría vacía «ACCESORIOS (por reclasificar)».

**Resultado esperado** (comprobado en la base local el 16/11/2026): 13 bajas, 45 renumerados, 17 nuevos, 1.659 actualizados, 3 marcas nuevas,
734 productos con saldo, **12.032 unidades y $175.015,00**; los 1.676 productos resuelven su cuenta en modo estricto; los servicios de
Eventos acreditan 4.04 y los de Servicio técnico 4.01.08. Si en producción las cifras difieren, **parar y revisar**.

**Guardas (el comando se niega a aplicar si…):** hay filas en facturas, compras, devoluciones, guías, notas de crédito, prefacturas, proformas,
recepciones, traslados o repuestos de taller (borrar antes los datos de prueba con `altamira:limpiar-datos-prueba`); no hay período abierto
en octubre 2026; ya existe el asiento de apertura (la carga ya se aplicó); el valor total no es $175.015,00 (`--valor-esperado`);
una categoría/subcategoría del Excel no existe o no tiene cuentas.

**Procedimiento recomendado** (guiado por `scripts/produccion_carga_inventario.sh <excel>`, que pide confirmación en cada paso):

1. Código desplegado (§2) y Excel copiado al servidor (`scp`, fuera del repositorio).
2. Respaldo `pg_dump -Fc` de la base `altamira` (solo esa; el Postgres del VPS es compartido).
3. Ensayo previo: restaurar la copia en una base `altamira_ensayo` en tu PC y correr los mismos pasos allí (§1.0).
4. `php artisan migrate --force` (incluye `2026_11_16_100000_...`).
5. Abrir el período 2026-10 (y los siguientes) en Contabilidad → Ejercicios si no están abiertos.
6. `altamira:limpiar-datos-prueba` (simula, luego `--ejecutar`) y `altamira:actualizar-iva-productos` (§4.2).
7. `inventario:carga-limpia <excel>` (simula) → comparar con lo esperado → `--aplicar`.
8. Verificar: Contabilidad → Asientos («Apertura de inventario al 31/10/2026», debe = haber = 175.015,00), Inventario → Consolidado, y una factura de prueba de un producto y de un servicio.

**Reversa:** restaurar el respaldo `pg_dump`; el JSON de `carga-limpia/` guarda productos, saldos y kárdex previos.

**Pendiente después de la carga:** asiento de los activos fijos ($6.040,96; falta `Activos_Fijos_ERP_Altamira_UIO.xlsx` con su detalle por cuenta)
y las decisiones DEC aún abiertas del §9.

