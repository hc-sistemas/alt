# Evaluación — Sistema de Permisos por Empresa (copia del profesor)

**Fecha:** 2026-07-24
**Alcance:** análisis puro, sin implementar ni fusionar nada. Complementa `REVISION_PROFESOR.md` (donde se identificó este cambio por primera vez) con una revisión a fondo, ya que es el cambio de mayor superficie de todos los que trajo la copia del profesor.
**Corrección sobre `REVISION_PROFESOR.md`:** ese primer reporte daba a entender que el flujo de aprobación especial (`AprobacionController`, `TipoAprobacion`, tabla `aprobaciones_especiales`) era exclusivo de la copia del profesor. **No es así** — verificado ahora que los tres ya existen, idénticos, en tu repo actual. Lo único exclusivo del profesor ahí es la fila `castigo_cartera` en el seeder de tipos de aprobación, y el uso de ese flujo específicamente para Castigo de Cartera.

---

## Hallazgo que cambia el análisis de raíz: Spatie Permission está muerto en tu propio repo

Antes de comparar nada, verifiqué si Spatie realmente se usa en tu código actual (`grep` de `Role::`, `Permission::`, `->hasRole(`, `->can(`, `@can`, `assignRole`, `givePermissionTo`, `hasPermissionTo` en todo `app/` y `resources/views/`):

```
app/Models/Usuario.php:10:use Spatie\Permission\Traits\HasRoles;
```

**Es la única coincidencia.** El trait `HasRoles` está declarado en el modelo `Usuario` pero **no se llama en ningún lugar del código**. El middleware que de verdad gatea cada ruta (`VerificarPermiso.php`) consulta directamente la tabla `permisos` por SQL crudo (`DB::table('permisos')->join('modulos', ...)`) — cero relación con Spatie. Confirmé lo mismo en la copia del profesor: su migración `drop_spatie_permission_tables.php` trae el comentario *"las tablas de Spatie Permission no se usaban en la app (el sistema real de roles/permisos es el custom perfiles/permisos/modulos)"*.

**Conclusión práctica: "reemplazar Spatie" no es un reemplazo de nada activo — es borrar código muerto.** El sistema de autorización real, en AMBAS copias, siempre fue el custom `perfiles`/`permisos`/`modulos` (que además son tablas del schema original, `create_permisos_table` está fechada 30 de mayo, antes de que estas dos copias se separaran). Esto reduce enormemente el riesgo de esa parte específica del cambio.

---

## FASE 1 — Cómo funciona el sistema del profesor

### 1. Archivos involucrados

| Archivo | Rol |
|---|---|
| `database/migrations/2026_05_30_100050_create_permisos_table.php` | Tabla original — **idéntica en ambos repos**. `perfil_id`, `modulo_id`, booleans `ver/crear/editar/eliminar/anular`. |
| `database/migrations/2026_07_18_000000_add_empresa_id_to_permisos_table.php` | **Solo en la copia del profesor.** Agrega `empresa_id` a `permisos`, cambia el unique a `(perfil_id, modulo_id, empresa_id)`. |
| `database/migrations/2026_07_18_000001_drop_spatie_permission_tables.php` | **Solo en la copia del profesor.** Elimina las tablas de Spatie (nunca usadas, ver arriba). |
| `app/Models/Permiso.php` | Diferencia pequeña: agrega `empresa_id` al fillable + relación `empresa()`. |
| `app/Models/Usuario.php` | Quita `use Spatie\Permission\Traits\HasRoles;` y el trait; agrega el filtro `empresa_id` a la resolución de permisos en el método que ya existía. |
| `app/Http/Middleware/VerificarPermiso.php` | Agrega `->where('permisos.empresa_id', session('empresa_activa_id'))` a la query — 1 línea. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Mismo patrón, 2 líneas, para el `permisos` que se comparte a React. |
| `app/Http/Controllers/Configuracion/PermisoController.php` | Agrega selector de empresa a la pantalla admin de matriz de permisos (~15 líneas). |
| `resources/js/Pages/Configuracion/Permisos/Index.tsx` | Agrega pestañas de empresa + usa `usePermiso('configuracion')` para deshabilitar edición sin permiso (~30 líneas). |
| `database/seeders/PermisoSeeder.php` | Siembra permisos por cada empresa en vez de un set global. |
| `resources/js/Hooks/usePermiso.ts` | **Idéntico en ambos repos, sin cambios.** Ver punto siguiente. |

**Dato clave: el hook `usePermiso()` no sabe nada de empresas.** Solo lee el objeto `permisos` que Inertia comparte y responde `puede('accion')`. Toda la lógica de "por empresa" vive del lado backend (`HandleInertiaRequests` decide QUÉ permisos manda). Esto significa que **son dos decisiones independientes**:
- (A) ¿Hacer que los permisos varíen por empresa? — cambio backend, ~7 archivos.
- (B) ¿Usar `usePermiso()` en las ~60-80 páginas que hoy no lo usan, para ocultar botones sin permiso? — cambio frontend, mecánico, no depende de (A). Se podría hacer con el sistema de permisos ACTUAL (sin empresa_id) exactamente igual.

### 2. Estructura de datos — ¿coincide con la matriz del documento del cliente?

El documento pide una matriz **Perfil × Módulo × Acción** (Súper Admin/Admin/Contador/Vendedor/Bodeguero/Técnico × Dashboard/Ventas/Compras/CxP/CxC/Inventario/Taller/Bancos/Importaciones/Contabilidad/RRHH/Reportes) — **no pide explícitamente que el mismo perfil tenga permisos distintos según la empresa activa.** La tabla del documento es de un solo nivel (perfil → módulo → nivel de acceso), sin columna de empresa.

Es decir: el cambio del profesor es una **extensión razonable, no un requisito explícito del documento**. Vale la pena confirmar con el cliente si de verdad necesita que, por ejemplo, un Vendedor tenga más o menos acceso en Altamira Import que en Altamira Matriz, o si el perfil debe comportarse igual en cualquier empresa (que es lo que tu sistema actual ya hace).

### 3. ¿Esto resuelve el mismo problema que los `abort_if()` de Taller?

**No — son dos problemas distintos, no sustitutos uno del otro.** Ya lo comprobé en código:

```
app/Http/Controllers/Taller/DiagnosticoController.php:24: abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);
app/Http/Controllers/Taller/LiquidacionController.php:38: abort_if(...)
app/Http/Controllers/Taller/OrdenTrabajoController.php:56: abort_if(...)
```

Esos `abort_if()` verifican que un **registro específico** (esta orden de trabajo, este ingreso) pertenece a la empresa activa — evita que alguien con acceso a Matriz edite/vea un registro de Import adivinando la URL (aislamiento de datos por empresa, tipo IDOR). El sistema de permisos por empresa del profesor verifica algo distinto: si el **rol** del usuario tiene el nivel de acceso X en el **módulo** Y para la empresa activa (autorización por acción, no por registro). **Aunque adoptes permisos por empresa, seguirías necesitando los `abort_if()` en cada controller que carga un registro por id** — el uno no reemplaza al otro. Esto es importante: no hay que vender la adopción del sistema del profesor como "ya no hace falta seguir agregando `abort_if()` donde falten".

### 4. Migraciones — ¿idempotentes? ¿qué pasa con los datos existentes?

Ambas migraciones nuevas son idempotentes (`Schema::hasColumn`/nada que se rompa si se corre dos veces). Más importante: **la migración de `empresa_id` no obliga a reconfigurar nada desde cero.** Hace un backfill explícito:

```php
foreach ($empresaIds as $index => $empresaId) {
    foreach ($permisos as $permiso) {
        if ($index === 0) {
            // La primera empresa reutiliza la fila original en vez de duplicarla.
            DB::table('permisos')->where('id', $permiso->id)->update(['empresa_id' => $empresaId]);
        } else {
            DB::table('permisos')->insert([...]); // copia el mismo permiso para las demás empresas
        }
    }
}
```

Si se aplica hoy sobre tu BD, cada perfil quedaría con el **mismo nivel de acceso que ya tiene, replicado en las 3 empresas** — nada se pierde, no hay que re-configurar la matriz de permisos manualmente. Solo se diferenciarían después, si se decide que alguna combinación perfil+módulo debe variar por empresa.

La migración de `drop_spatie_permission_tables` no tiene `down()` funcional (comentario explícito: "no se usaban"), consistente con el hallazgo de la sección anterior.

### 5. Castigo de Cartera — ¿depende del sistema de permisos nuevo?

**No depende de él.** Es una pieza separada e independiente:
- `TiposAprobacionSeeder.php`: la copia del profesor agrega una fila `castigo_cartera` — tu repo no la tiene.
- `AprobacionController.php`, `TipoAprobacion.php`, tabla `aprobaciones_especiales`: **ya existen, idénticos, en tu repo actual** (corrección respecto a `REVISION_PROFESOR.md`) — es infraestructura ya construida y probablemente ya usada para otro flujo (Horas Extras tiene su propio `HorasExtrasAprobacion.php`, que también ya existe en ambos).
- Lo único que falta en tu repo es: (a) la fila `castigo_cartera` en el seeder, y (b) que `CuentaCobrarController::castigo()` cree un registro en `aprobaciones_especiales` (usando el patrón ya existente) en vez de validar un `codigo_aprobacion` suelto.

**Se puede adoptar por completo sin tocar nada del sistema de permisos por empresa.**

### 5.1. Bug confirmado, independiente de todo lo anterior

Verifiqué el nombre real del perfil en tu BD:

```
super_admin
admin
contador
vendedor
bodeguero
tecnico
```

Tu `VerificarPermiso.php` y `HandleInertiaRequests.php` ya comparan correctamente contra `'super_admin'` (con guion bajo). Pero `app/Http/Controllers/Ventas/CuentaCobrarController.php::castigo()` en tu repo compara contra `'superadmin'` (**sin** guion bajo) — nunca va a coincidir con el valor real. **Esto bloquea el Castigo de Cartera para absolutamente todos los usuarios, incluido el super admin real**, cada vez que se intente usar esa función hoy. Es un bug aislado, de una sola línea, sin relación con adoptar o no el resto del sistema del profesor — recomiendo corregirlo ya, independientemente de cualquier otra decisión.

---

## FASE 2 — Impacto de fusionar

### 1. Uso real de Spatie a migrar

Ya cubierto arriba: **cero uso real.** Migrar = quitar la dependencia de `composer.json`, el trait de `Usuario.php`, `config/permission.php`, y correr la migración que elimina sus tablas (vacías/sin datos reales). No hay lógica de negocio que reescribir.

### 2. Las páginas frontend — cuánto te toca a ti vs a Darío

De las páginas que la copia del profesor modificó para usar `usePermiso()` (lista completa en `REVISION_PROFESOR.md`), clasificadas por el reparto de `DISTRIBUCION_MODULOS_DEVS.md`:

| Dueño | Módulos | Cantidad aprox. |
|---|---|---|
| **Dev 1 (Darío)** | Ventas (Facturas, Proformas, Prefacturas, NC, Retenciones, Guías, CxC), Inventario (Productos, Kárdex, Traslados, Activos, Listas, Config), Taller, Personas (Clientes/Proveedores/Transportistas) | ~40 páginas |
| **Dev 2 (tú)** | Bancos, Compras/Importaciones, Contabilidad, RRHH | ~26 páginas |
| **Core/Compartido** | Configuración, Dashboard, Auth, Manuales, `Components/shared/*` | ~12 páginas |

**Poco más de un tercio te toca directamente a ti.** El resto es trabajo que, si se decide adoptar, hay que coordinar con Darío (o hacerlo tú mismo si él no está disponible, dado que el patrón es mecánico y repetitivo — no requiere entender la lógica de negocio de cada página, solo envolver los botones de acción en `puede('accion') && (...)`).

### 3. Riesgos de fusión

- **Nombres de rutas/permisos:** verifiqué que `ModuloSeeder.php` es idéntico en ambos repos — las claves de módulo (`compras`, `bancos`, `contabilidad`, etc.) ya coinciden, no hay que inventar ni renombrar nada para que el middleware granular (`permiso:compras,crear`, `permiso:compras,editar`, etc.) funcione.
- **Riesgo sobre lo ya validado esta sesión (Bancos, RRHH, Contabilidad):** el cambio en esas páginas es puramente aditivo a nivel de UI — envolver un botón existente en una condición `puede('crear') && (...)`. No toca la lógica de asientos, saldos, ni cálculos ya probados exhaustivamente. El riesgo real no es "romper la lógica contable", es el riesgo mecánico normal de tocar ~26 archivos tuyos (typos, un `puede()` mal puesto que oculte un botón que sí debería verse) — mitigable con un smoke test module por módulo después de aplicar.
- **Riesgo del lado backend (empresa_id en permisos):** bajo, dado el backfill no destructivo ya revisado. El riesgo real está en decidir SI de verdad quieres que el mismo perfil tenga distinto acceso por empresa — si la respuesta es "no, da igual la empresa", este cambio no aporta nada y solo agrega una dimensión de complejidad a mantener (¿quién configura y recuerda mantener sincronizadas las 3 matrices de permisos?).

### 4. Esfuerzo estimado

| Pieza | Archivos | Esfuerzo |
|---|---|---|
| Corregir bug `superadmin`→`super_admin` en `CuentaCobrarController` | 1 | Minutos — sin dependencias, hacerlo ya. |
| Quitar Spatie (dependencia muerta) | ~4 (`composer.json`, `Usuario.php`, `config/permission.php`, 1 migración) | < 1 hora, riesgo ~nulo. |
| Permisos por empresa (backend) | ~7 archivos + 2 migraciones + 1 seeder | 2-4 horas incl. pruebas, si se decide que sí hace falta. |
| Castigo de Cartera con aprobación formal | 2 archivos (seeder + controller) | 1-2 horas, independiente de todo lo demás. |
| `usePermiso()` en páginas Dev 2 (tuyas) | ~26 páginas | Mecánico pero voluminoso — varias horas repartidas en varias sesiones, probando módulo por módulo. |
| `usePermiso()` en páginas Dev 1/Core (~52 páginas) | ~52 páginas | Coordinar con Darío o asumirlo tú — mismo patrón mecánico, más tiempo por volumen. |

---

## Recomendación

**No es todo-o-nada.** Son 4 piezas independientes que se pueden decidir por separado:

1. ✅ **Adoptar ya, sin duda:** el fix del bug `superadmin` (aislado, gratis, corrige una función rota hoy).
2. ✅ **Adoptar ya, sin duda:** quitar Spatie (confirmado sin uso real, cero riesgo, limpia una dependencia y un trait muertos).
3. ⚠️ **Adoptar con una pregunta previa al cliente:** permisos por empresa — solo tiene sentido si de verdad quieres que el mismo perfil se comporte distinto según la empresa activa. Si la respuesta es "no", no lo adoptes — sería complejidad sin beneficio real, y **de todas formas no reemplaza los `abort_if()` de aislamiento por registro que ya tienes en Taller** (hay que seguir agregándolos donde falten, en cualquier escenario).
4. ✅ **Adoptar, es una mejora aislada y de bajo riesgo:** el flujo formal de aprobación para Castigo de Cartera — reutiliza infraestructura que ya tienes funcionando, no depende de nada de lo anterior.
5. 🔧 **Adoptar como trabajo de fondo, no bloqueante:** `usePermiso()` en las páginas que aún no lo usan — hazlo en tus propios módulos primero (Bancos/Compras/Contabilidad/RRHH, ~26 páginas), coordina con Darío para el resto. No es urgente ni riesgoso, es simplemente volumen.

**El "reemplazo completo de Spatie" que sonaba como el cambio más grande y riesgoso, en la práctica es el más barato y seguro de los cuatro** — porque nunca estuvo realmente en uso.

---

## Plan de fases (si decides avanzar)

- **Fase A (ahora mismo, sin dependencias):** corregir el bug `superadmin`→`super_admin`. Commit aislado.
- **Fase B (bajo riesgo, en paralelo con lo demás):** quitar Spatie — `composer remove spatie/laravel-permission`, quitar el trait de `Usuario.php`, borrar `config/permission.php`, aplicar la migración que dropea sus tablas (vacías). Probar login y matriz de permisos actual siguen funcionando igual (deberían, porque nunca dependieron de Spatie).
- **Fase C (si se decide adoptar permisos por empresa):** aplicar la migración de `empresa_id` (con su backfill automático), actualizar `Permiso.php`, `VerificarPermiso.php`, `HandleInertiaRequests.php`, `PermisoSeeder.php`, `PermisoController.php` y `Permisos/Index.tsx`. Probar exhaustivamente en Bancos/RRHH/Contabilidad (tus módulos ya validados) antes de darlo por bueno, ya que cualquier error aquí bloquearía el acceso a TODO el sistema (es middleware transversal).
- **Fase D (independiente, se puede hacer en cualquier momento):** flujo formal de aprobación para Castigo de Cartera — agregar la fila al seeder, conectar `CuentaCobrarController::castigo()` al patrón ya existente de `AprobacionController`.
- **Fase E (trabajo de fondo, sin fecha límite):** ir agregando `usePermiso()` módulo por módulo, empezando por los tuyos. No bloquea nada de lo anterior ni depende de la Fase C (funciona igual con o sin permisos-por-empresa).

**No se implementó ni fusionó nada en esta pasada — es solo el análisis que pediste.**

---

## REPORTE FINAL — Implementación de las 4 piezas (2026-07-24)

Las 4 piezas confirmadas se implementaron en orden, cada una validada con un script de prueba (transacción con rollback, cero residuos) antes de pasar a la siguiente. 4 commits separados en `feature/dev2-contabilidad-compras`, sin push.

### 1. ✅ Implementado — Fix bug `superadmin` → `super_admin`
**Commit:** `432b24a`
- `CuentaCobrarController::castigo()` y `FacturaController::anular()` (única otra ocurrencia, hallada por grep) corregidos para comparar contra `'super_admin'`.
- Validado con CxC de prueba +360 días de mora logueado como Super Admin real: ya no bloquea.

### 2. ✅ Implementado — Spatie removido (código muerto)
**Commits:** `04ab5a2`, `5f11988`
- Re-confirmado con un grep más amplio (incluyendo `hasAnyRole`, que el análisis original no cubrió): apareció un uso real en `AsistenciaController.php` (2 llamadas), migrado a comparación directa de perfil antes de quitar el trait.
- Trait `HasRoles` quitado de `Usuario.php`; `spatie/laravel-permission` quitado de `composer.json`/`composer.lock`; `config/permission.php` borrado; las 3 tablas de Spatie confirmadas vacías (0 filas) antes de dropearlas en una migración nueva.
- `composer install` limpio y `npm run build` corridos sin errores tras el cambio.

### 3. ✅ Implementado a nivel backend/middleware — Permisos por empresa
**Commit:** `e4dcbf7`
- Migración idempotente `add_empresa_id_to_permisos_table` con backfill no destructivo: los 60 permisos existentes se replicaron a las 2 empresas reales (Matriz + Import — Altamira Fix es centro de costo de Matriz, no tiene fila propia en `empresas`), quedando 120 filas, mismo nivel de acceso en ambas hasta que se configure lo contrario.
- **Corrección sobre el borrador original:** el `down()` de la migración del profesor intentaba restaurar el unique `(perfil_id, modulo_id)`, algo que **siempre falla** en cuanto hay más de 1 empresa con filas de permisos (violación de unicidad, confirmado con una prueba real de rollback). Se corrigió para descartar primero las copias de empresas no-primarias antes de revertir.
- `VerificarPermiso.php` y `HandleInertiaRequests.php` ahora filtran por `empresa_activa_id` de sesión (super_admin/admin mantienen su bypass total, sin cambios).
- `Configuración > Permisos` (`PermisoController` + `Permisos/Index.tsx`) ahora tiene selector de empresa; `actualizar()` exige y usa `empresa_id`; `PermisoSeeder.php` siembra por empresa.
- **Validado:** con un Vendedor de prueba, diferenciar manualmente `compras.crear` entre Matriz e Import y confirmar que el middleware bloquea en una y permite en la otra para el mismo usuario, solo cambiando `empresa_activa_id` de sesión; conteo de permisos y Balance de Comprobación idénticos antes/después.
- **PENDIENTE (fuera de esta pasada, a propósito):** aplicar `usePermiso()` en las páginas frontend — ver desglose completo más abajo.

### 4. ✅ Implementado — Aprobación formal de Castigo de Cartera
**Commit:** `0f12ba1`
- `castigo_cartera` agregado a `TiposAprobacionSeeder` (18vo tipo).
- `CuentaCobrarController::castigo()` reescrito para usar `aprobaciones_especiales` vía el mismo patrón que `descuento_excedido` (antes insertaba en columnas inexistentes — `SQLSTATE[42703]`, nunca había funcionado). Se agregó también el candado de `dias_vencido > 360` (ausente en el código original) y las partidas reales del asiento (antes `partidas: []`, un asiento vacío).
- **Corrección sobre la instrucción original:** la cuenta `1.1.2.05` indicada no existe en el plan de cuentas real; se usó la cuenta correcta y verificada `1.1.3.05` ("(-) Provisión Cuentas Incobrables"), que sí coincide con el PDF de especificación original.
- Bugs adicionales encontrados y corregidos en `CxC/Index.tsx` (sin los cuales el botón Castigar era inoperable incluso con el backend ya arreglado): `esSuperAdmin` comparaba contra `'Super Admin'` en vez de `'super_admin'`, y `handleCastigar` llamaba a la ruta inexistente `ventas.cxc.castigar` (POST) en vez de `ventas.cxc.castigo` (PATCH).
- **Validado:** flujo completo end-to-end (código incorrecto rechazado, código correcto genera aprobación pendiente, castigo sin aprobación rechazado, castigo exitoso deja la CxC en `castigada`/saldo 0 con asiento balanceado DEBE 5.2.4.01/HABER 1.1.3.05, aprobación no reutilizable, candado de 360 días funcional).

---

### Pendiente: aplicar `usePermiso()` en las páginas frontend (~78 páginas)

No se tocó ninguna de estas en esta pasada (fuera de `Permisos/Index.tsx`, que se modificó como parte de la Pieza 3 porque ES la pantalla de configuración del sistema de permisos). El middleware backend (`VerificarPermiso`) ya protege todas las rutas aunque el frontend no oculte los botones — es defensa en profundidad, no un hueco de seguridad.

**Nota importante:** al revisar el estado actual del repo (no solo la copia del profesor), 6 páginas **ya usan `usePermiso()` de forma independiente** — no vinieron de la copia del profesor, ya existían en esta rama:
`Inventario/Productos/Index.tsx`, `Inventario/Recepciones/Index.tsx`, `Ventas/Facturas/Index.tsx`, `Ventas/GuiasRemision/Index.tsx`, `Ventas/Prefacturas/Index.tsx`, `Ventas/Proformas/Index.tsx`.
Estas ya están fuera de la lista de pendientes — no hace falta tocarlas de nuevo.

#### Tuyas (Dev 2 — Bancos, Compras/Importaciones, Contabilidad, RRHH) — 26 páginas, para que las hagas tú

```
Bancos/BancosCajas/Index.tsx
Bancos/Cajas/Index.tsx
Bancos/Cheques/Index.tsx
Bancos/Conciliaciones/Index.tsx
Bancos/Conciliaciones/Show.tsx
Bancos/Datafast/Index.tsx
Bancos/Movimientos/Index.tsx
Compras/Anticipos/Index.tsx
Compras/Compras/Index.tsx
Compras/Compras/Show.tsx
Compras/CuentasPagar/Index.tsx
Compras/Devoluciones/Index.tsx
Compras/Proveedores/Index.tsx   (verificar si es duplicado de Personas/Proveedores/Index.tsx — hay dos rutas con ese nombre)
Compras/Recepcion/Index.tsx
Contabilidad/Asientos/Index.tsx
Contabilidad/Asientos/Show.tsx
Contabilidad/Ejercicios/Index.tsx
Contabilidad/Parametros/Index.tsx
Contabilidad/PlanCuentas/Index.tsx
RRHH/Asistencia/Index.tsx
RRHH/Colaboradores/Index.tsx
RRHH/HorasExtras/Index.tsx
RRHH/Liquidaciones/Index.tsx
RRHH/Nomina/Index.tsx
RRHH/Nomina/Show.tsx
RRHH/Prestamos/Index.tsx
```

#### De Dario (Dev 1 — Ventas, Inventario, Taller, Personas) — 36 páginas pendientes de 42, para coordinar por separado

*(6 ya hechas y excluidas de esta lista: Productos/Index, Recepciones/Index, Facturas/Index, GuiasRemision/Index, Prefacturas/Index, Proformas/Index — ver nota arriba)*

```
Components/Ventas/DescuentoEspecialModal.tsx
Inventario/Activos/Form.tsx
Inventario/Activos/Index.tsx
Inventario/Activos/Show.tsx
Inventario/Configuracion/Bodegas/Index.tsx
Inventario/Configuracion/Categorias/Index.tsx
Inventario/Configuracion/Marcas/Index.tsx
Inventario/Kardex/Ajuste.tsx
Inventario/Kardex/Index.tsx
Inventario/Kardex/Saldos.tsx
Inventario/ListasPrecio/Index.tsx
Inventario/Productos/Form.tsx
Inventario/Traslados/Form.tsx
Inventario/Traslados/Index.tsx
Inventario/Traslados/Show.tsx
Personas/Clientes/Index.tsx
Personas/Proveedores/Index.tsx
Personas/Transportistas/Index.tsx
Taller/Diagnosticos/Form.tsx
Taller/Ingresos/Form.tsx
Taller/Ingresos/Index.tsx
Taller/Liquidacion/Show.tsx
Taller/OrdenesTrabajo/Show.tsx
Taller/TiposEquipo/Index.tsx
Ventas/CxC/Index.tsx
Ventas/Facturas/Form.tsx
Ventas/GuiasRemision/Form.tsx
Ventas/NotasCredito/Form.tsx
Ventas/NotasCredito/Index.tsx
Ventas/Prefacturas/Form.tsx
Ventas/Prefacturas/Show.tsx
Ventas/Proformas/Form.tsx
Ventas/Proformas/Show.tsx
Ventas/Retenciones/Form.tsx
Ventas/Retenciones/Index.tsx
```

#### Core/Compartido — 11 páginas pendientes de 12 (Configuración, Dashboard, Auth, Manuales)

*(1 ya hecha: `Configuracion/Permisos/Index.tsx`, en esta misma pasada — Pieza 3)*

```
Components/shared/BuscadorProductoModal.tsx
Components/shared/PageHeader.tsx
Components/shared/Sidebar.tsx
Components/shared/Topbar.tsx
Layouts/AuthLayout.tsx
Auth/Login.tsx
Configuracion/Empresa/Index.tsx
Configuracion/Usuarios/Form.tsx
Configuracion/Usuarios/Index.tsx
Dashboard/Index.tsx
Manuales/Index.tsx
```

Estas últimas 12 (11 pendientes) no tienen dueño único por el reparto Dev1/Dev2 — decidir entre los dos quién las toma, o dividirlas por quien más las use.
