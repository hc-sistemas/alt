# Revisión — Copia devuelta por el profesor (`alt.rar`)

**Fecha:** 2026-07-24
**Origen:** `alt.rar` en `Descargas`, extraído (sin fusionar) a `Descargas\alt_profesor_extraido\alt` para comparación de solo lectura.
**Rama comparada:** `feature/dev2-contabilidad-compras` (estado limpio, `git status` sin cambios pendientes antes de comparar).
**Método:** `diff -rq` recursivo excluyendo `node_modules/`, `vendor/`, `.git/`, `storage/`, caché/build, seguido de `diff` línea por línea en los archivos de lógica de negocio más relevantes.

---

## a. Resumen ejecutivo

**Hallazgo principal, antes que nada: esto NO es "el profesor retocó unos archivos sobre tu último commit".** Son dos líneas de trabajo que se separaron hace tiempo y nunca se fusionaron:

1. **Tu rama (`feature/dev2-contabilidad-compras`) avanzó sola** en todo lo de esta sesión — el volumen de prueba, los índices de rendimiento, y el módulo de Importaciones completo (Revertir, Peso, Factor de Importación, Previsualización de precios). Nada de eso está en la copia del profesor porque sus archivos son de fechas anteriores (16 al 22 de julio) — es normal, no es que el profesor lo haya borrado.
2. **La copia del profesor avanzó sola** en una dirección que tu rama nunca tocó: un sistema de permisos re-arquitecturado (multi-empresa, sin Spatie), una capa de aplicación de esos permisos en el frontend (~20 páginas), un flujo formal de "aprobación especial" para castigar cartera, herramientas de migración de datos legados (conexión a la BD vieja `altamira2`), y un generador de manuales PDF más completo. Nada de eso existe en el historial de git de tu rama — no es que lo hayan quitado, es trabajo que nunca llegó a fusionarse.

**Conteo de diferencias** (excluyendo `node_modules`, `vendor`, `.git`, build/caché):
- **~80 archivos con contenido distinto**, de los cuales:
  - **1 revierte un bug real** en tu repo actual (`InventarioSaldo` / reporte de Kárdex-Saldos — ver sección de Inventario).
  - **~60 son el mismo patrón repetido**: la copia del profesor usa el hook `usePermiso()` para ocultar botones de Crear/Editar/Eliminar según permiso; tu repo tiene el hook pero no lo usa en esas páginas.
  - El resto son diferencias de arquitectura de permisos/aprobación (Auth, CxC, rutas, seeders) y del módulo de Manuales.
- **~15 archivos existen SOLO en la copia del profesor**: en su mayoría, herramientas de migración de datos legados y refuerzos del sistema de permisos (detalle abajo).
- **~10 archivos existen SOLO en tu repo actual**: casi todos son tu trabajo de esta sesión (Importaciones, migraciones nuevas, `AUDITORIA_IMPORTACIONES.md`, `VALIDACION_VOLUMEN.md`, `resources/js/lib/axios.ts`) más archivos de configuración de IDE sin relevancia funcional.
- Los 4 `.md`/`.pdf` y los 8 scripts `migrate_*.sql` que mencionaste **ya existen, idénticos, en tu propio repo** — no son exclusivos de la copia del profesor (ver sección dedicada).

---

## b. Diferencias por módulo/área

### 1. Sistema de Permisos — cambio de arquitectura (el hallazgo más grande)

| Archivo | Tipo de cambio | Qué hace distinto |
|---|---|---|
| `composer.json` | Arquitectura distinta | Tu repo depende de `spatie/laravel-permission`; la copia del profesor **no la tiene** — no usa Spatie en absoluto. |
| `config/permission.php` | Solo en tu repo | Config de Spatie — coherente con que tu repo sí lo usa. |
| `app/Models/Usuario.php` | Arquitectura distinta | Tu repo usa `use Notifiable, HasRoles;` (trait de Spatie). La copia del profesor usa solo `Notifiable` + lógica propia con `session('empresa_activa_id')` para resolver permisos **por empresa activa**, no globales. |
| `app/Models/Permiso.php` | Feature nueva del profesor | Su versión agrega `empresa_id` al fillable y una relación `empresa()` — el mismo perfil puede tener permisos DISTINTOS según la empresa activa (ej. un Vendedor con más acceso en Matriz que en Import). Tu repo no tiene este campo. |
| `app/Http/Middleware/VerificarPermiso.php` | Feature nueva del profesor | Agrega `->where('permisos.empresa_id', $request->session()->get('empresa_activa_id'))` al query — el candado de permiso se resuelve por empresa. Tu repo no filtra por empresa. |
| `app/Http/Middleware/HandleInertiaRequests.php` | Feature nueva del profesor | Mismo patrón: los permisos compartidos a React se resuelven con `$empresaActivaId` como parte de la key. |
| `database/migrations/2026_07_18_000000_add_empresa_id_to_permisos_table.php` | Solo en la copia del profesor | Agrega la columna `empresa_id` a `permisos`. |
| `database/migrations/2026_07_18_000001_drop_spatie_permission_tables.php` | Solo en la copia del profesor | Elimina las tablas de Spatie (`roles`, `model_has_roles`, etc.) — confirma el abandono deliberado de Spatie a favor del sistema propio. |
| `database/migrations/2026_05_30_223449_create_permission_tables.php` | Solo en tu repo | La migración que CREA las tablas de Spatie — coherente con que tu repo sí las usa. |
| `database/seeders/PermisoSeeder.php` | Feature nueva del profesor | Siembra permisos **por cada empresa** (`foreach ($empresaIds as $empresaId)`), tu repo siembra un solo set global por perfil. |
| `database/seeders/UsuariosPruebaSeeder.php` | Solo en la copia del profesor | Crea un usuario de prueba por perfil (admin, contador, bodeguero, técnico), cada uno atado a UNA sola empresa, específicamente para poder probar que el candado de permisos-por-empresa funciona de forma aislada. |
| `routes/web.php` | Feature nueva del profesor (783 líneas vs 623 en tu repo) | Envuelve muchísimas más rutas con `Route::middleware('permiso:modulo,crear')`, `permiso:modulo,editar`, `permiso:modulo,eliminar`, `permiso:modulo,anular` — permisos granulares por acción. Tu repo en su mayoría solo gatea a nivel de módulo con `permiso:modulo,ver`, sin distinguir crear/editar/eliminar en el routing. |

**~60 páginas de `resources/js/Pages/**` y `Components/`** (Bancos, RRHH, Taller, Ventas, Inventario, Compras, Configuración, Contabilidad — la lista completa está en el diff crudo, mismo patrón en todas): la copia del profesor importa `usePermiso(modulo)` y usa `puede('crear')`/`puede('editar')`/`puede('eliminar')` para **ocultar** botones de acción que el usuario no tiene permiso de usar. Tu repo tiene el mismo hook (`resources/js/Hooks/usePermiso.ts` existe **idéntico** en ambos) pero **no lo está usando** en esas ~60 páginas — los botones se muestran a todos los usuarios autenticados, sin ocultarlos por permiso a nivel de UI (el backend sí puede seguir bloqueando la acción vía middleware, pero la UI no da esa pista visual).

Ejemplos representativos (mismo patrón, confirmado por muestreo en Bancos, RRHH e Inventario):
- `resources/js/Pages/RRHH/Colaboradores/Index.tsx`
- `resources/js/Pages/Bancos/Movimientos/Index.tsx`
- Y ~58 páginas más con el mismo patrón (ver lista completa al final de este documento).

### 2. Login — método de autenticación

| Archivo | Tipo de cambio | Qué hace distinto |
|---|---|---|
| `app/Http/Controllers/Auth/LoginController.php` | Feature que el profesor mantiene y tu repo simplificó | La copia del profesor acepta un campo único `usuario` que puede ser **email o username** (`filter_var($request->usuario, FILTER_VALIDATE_EMAIL) ? 'email' : 'username'`). Tu repo solo acepta `email`. Si en producción hay usuarios pensados para loguearse con un nombre de usuario (no email), tu versión actual los bloquearía. |

### 3. Cuentas por Cobrar — Castigo de Cartera

| Archivo | Tipo de cambio | Qué hace distinto |
|---|---|---|
| `app/Http/Controllers/Ventas/CuentaCobrarController.php` | Mezcla: refactor DRY (profesor) + arquitectura de aprobación distinta | (a) La copia del profesor extrae el cálculo de días de vencimiento a un método privado `diasVencido()` reutilizado en `index()`, `show()` y `castigo()` — tu repo tiene el mismo cálculo **duplicado 2 veces** (mismo comentario sobre `diffInDays()` firmado en Carbon 3 en ambas copias, así que el fix en sí ya lo hiciste tú antes; el profesor solo lo centralizó). (b) Para castigar una deuda, la copia del profesor valida `'aprobacion_especial_id' => 'required|integer'` (referencia a un registro formal en `tipos_aprobacion`, sembrado con la clave `castigo_cartera` — ver `TiposAprobacionSeeder.php`); tu repo valida `'codigo_aprobacion' => 'required|string'` (un código suelto). (c) **Posible bug a verificar**: la copia del profesor compara `$perfilNombre !== 'super_admin'` (con guion bajo), tu repo compara `$perfilNombre !== 'superadmin'` (sin guion bajo) — si el perfil real sembrado en la BD es uno de los dos, la otra versión bloquearía el castigo para TODOS los super admins. Vale la pena confirmar cuál es el valor real en `perfiles.nombre` antes de tocar nada. |
| `database/seeders/TiposAprobacionSeeder.php` | Feature nueva del profesor | Agrega la fila `castigo_cartera` a `tipos_aprobacion` — sustento para el flujo de aprobación formal descrito arriba. Tu repo no la tiene. |

### 4. Inventario — bug real encontrado (⚠️ el hallazgo más accionable)

| Archivo | Tipo de cambio | Qué hace distinto |
|---|---|---|
| `app/Models/InventarioSaldo.php` | **Corrección de bug** | Tu repo tiene `$fillable`/casts apuntando a una columna `cantidad` — **esa columna no existe** en la tabla real `inventario_saldos` (la columna real es `stock_actual`, confirmado esta misma sesión con `Schema::getColumnListing()`). La copia del profesor usa correctamente `stock_actual`. |
| `resources/views/reportes/inventario/saldos.blade.php` | **Corrección de bug, consecuencia directa del anterior** | Tu repo calcula `$saldos->sum(fn($s) => (float)$s->cantidad * ...)` — como `cantidad` no existe en la fila cruda, esto da `0` o `null` silenciosamente. La copia del profesor usa `$s->stock_actual`, el valor real. **El reporte PDF de Saldos de Inventario en tu rama actual probablemente muestra cantidades y valor total en $0 para todo.** Este es el único hallazgo de esta comparación que recomendaría corregir cuanto antes, independientemente de si se adopta algo más de la copia del profesor. |

*(Nota: ya había detectado esta inconsistencia del modelo en una sesión anterior mientras trabajaba en Importaciones, pero no estaba en el alcance de esa tarea así que no la toqué. La copia del profesor confirma que sí es un bug real y que además tiene un efecto visible en un reporte.)*

### 5. Migración de datos legados — herramientas exclusivas de la copia del profesor

| Archivo | Qué hace |
|---|---|
| `config/database.php` (conexión `pgsql_legacy`) | Conexión de solo lectura a la base de datos vieja `altamira2`, usada por los comandos de migración de datos históricos. Tu repo no la tiene configurada. |
| `app/Console/Commands/MigrarInventarioLegado.php` | Comando `inventario:migrar-legado` — migra movimientos de inventario desde `altamira2.erp_i_mov_inv_pt` hacia `inventario_movimientos`/`inventario_saldos`, con un mapa de bodegas legado→nuevo y flags `--dry-run`/`--solo-saldos`. No existe en tu repo. |
| `app/Console/Commands/MigrarProductos.php` | Existe en ambos repos pero con contenido distinto — no se revisó línea por línea a fondo dado el volumen de esta comparación; recomendable revisarlo aparte si se van a migrar productos reales. |
| `database/migrate_clientes_produccion.sql` + `database/export/clientes_migrados.csv` | Script para cargar 4,819 clientes reales (limpios/deduplicados desde `altamira2.erp_i_cliente`) directamente a producción vía `psql \copy`, pensado para ejecutarse en el VPS al momento del corte a producción. Incluye advertencia de hacer `pg_dump` de respaldo antes de correrlo. No existe en tu repo (aunque los `migrate_*.sql` más simples de clientes/productos/etc. sí — ver sección siguiente). |

### 6. Manuales en PDF — funcionalidad más completa en la copia del profesor

| Archivo | Tipo de cambio | Qué hace distinto |
|---|---|---|
| `app/Http/Controllers/ManualesController.php` | Feature nueva del profesor | Tu repo sirve manuales como PDF **estático** ya generado (`storage_path('app/public/manuales/Manual_Inventario.pdf')`). La copia del profesor los genera **dinámicamente** desde Blade (`tipo: 'dinamico'`) para Inventario, Taller y Ventas. |
| `resources/views/pdf/manual-inventario.blade.php`, `manual-taller.blade.php`, `manual-ventas.blade.php` | Solo en la copia del profesor | Plantillas Blade con portada estilizada (morado/lila) para generar esos 3 manuales al vuelo. No existen en tu repo. |

### 7. Archivos de referencia que mencionaste — ya existen en tu propio repo (no son exclusivos de la copia del profesor)

Verificado directamente: **estos 12 archivos ya están en tu repo actual, con contenido idéntico al de la copia del profesor** (no aparecieron como diferentes ni como "solo en backup"):
`DISTRIBUCION_MODULOS_DEVS.md`, `PARA_DEV1.md`, `VALIDACION_LOGOUT_419.md`, `diagnostico_dev1_altamira.pdf`, `migrate_bodegas.sql`, `migrate_categorias.sql`, `migrate_clientes.sql`, `migrate_kardex.sql`, `migrate_marcas.sql`, `migrate_productos.sql`, `migrate_saldos.sql`, `migración_script.txt` (este último está vacío en ambos lados).

No hace falta recuperar nada de ahí — ya lo tienes.

### 8. Otras diferencias menores / ruido sin relevancia funcional

- `app/Http/Controllers/Inventario/ProductoController.php`, `ListaPrecioController.php`, `RecepcionController.php`, `TrasladoController.php`, `KardexController.php`: difieren, probablemente por el mismo patrón de `usePermiso`/`empresa_id` en permisos aplicado también del lado backend a nivel de controller (no se diffearon línea por línea todos por volumen — se puede profundizar si interesa alguno en particular).
- `resources/js/types/index.ts`: la copia del profesor tiene `marca_fabricante: string | null` en `Producto` (ligado a su migración `add_marca_fabricante_to_productos_table`, que tampoco está en tu repo); tu repo tiene `peso: number | null` (tu propia adición de esta sesión). Ninguno de los dos "quitó" nada del otro — son adiciones independientes al mismo tipo.
- `public/build/*`: son artefactos compilados (JS/CSS con hash), **esperado que difieran siempre** entre dos checkouts construidos en momentos distintos — no aporta nada comparar esto.
- `bootstrap/cache/*.php`: caché de configuración/paquetes de Laravel, regenerable con `php artisan config:cache` — no aporta nada comparar esto.
- `.claude/`, `.vscode/`, `.phpstorm.meta.php`, `_ide_helper.php`, `_ide_helper_models.php`: solo en tu repo, herramientas de IDE/autocompletado, cero relevancia funcional.
- `database/backup_antes_reset_20260621_1148.sql`: solo en tu repo, un respaldo puntual de una sesión anterior — no relacionado con el profesor.
- `resources/js/lib/axios.ts`: solo en tu repo — es el helper que creé esta sesión para el fix de CSRF (`window.axios` reexportado), parte de tu avance reciente, no algo perdido.
- Migraciones de alineación de columnas (`2026_07_21_000001_alinear_columnas_inventario_saldos.php`, `..._000002_alinear_columnas_inventario_movimientos.php`, `..._000003_drop_observacion_inventario_movimientos.php`): solo en la copia del profesor — por el nombre, parecen ser justamente el tipo de fix que resolvería el bug de `stock_actual` vs `cantidad` de la sección 4. Vale la pena leerlas si decides adoptar esa corrección.
- `resources/js/Pages/Inventario/Traslados/DetalleModal.tsx`: componente nuevo, solo en la copia del profesor — no se revisó su contenido a fondo.

---

## c. Observaciones/feedback en texto

No se encontró ningún comentario, nota, o archivo de retroalimentación explícita del profesor (tipo "revisión.txt" o comentarios `// TODO` firmados) — ni en los `.md` (que resultaron ser copias idénticas de los tuyos, sección 7) ni en el código modificado. Los cambios del profesor son 100% código, sin anotaciones textuales que expliquen su razonamiento. La única "explicación" disponible es inferida del propio código (por ejemplo, el comentario ya existente sobre `diffInDays()` firmado en Carbon 3 en `CuentaCobrarController.php`, que es tuyo, no del profesor).

---

## d. Qué adoptar — prioridad sugerida

**Corrección clara, recomendable independientemente de todo lo demás:**
1. ⚠️ **`InventarioSaldo.php` + `saldos.blade.php`** — `cantidad` no existe como columna real; el reporte de Saldos de Inventario en tu rama actual está probablemente mostrando ceros. Esto no depende de si adoptas el resto de los cambios del profesor — es un bug aislado y concreto.

**Decisiones de arquitectura que requieren tu criterio (no son "correcto vs incorrecto", son alcance/diseño):**
2. Sistema de permisos por empresa (Spatie → custom, `permisos.empresa_id`, middleware/rutas granulares por acción) — es un cambio grande y transversal (~15 archivos backend + ~60 páginas frontend). Adoptarlo significa decidir si de verdad necesitas que los permisos varíen por empresa activa, y planificar la migración de datos de permisos existentes.
3. Aplicar `usePermiso()` en el frontend para ocultar botones sin permiso — más contenido y aislado que el punto 2, se podría adoptar sin necesariamente llevar el resto del cambio de arquitectura de permisos.
4. Flujo formal de aprobación especial (`tipos_aprobacion` + `aprobacion_especial_id`) para Castigo de Cartera — antes de tocarlo, confirmar el valor real de `perfiles.nombre` para resolver la discrepancia `super_admin` vs `superadmin`.
5. Login con usuario-o-email — depende de si en producción existen usuarios pensados para loguearse sin email.

**Solo si vas a migrar datos reales de un sistema legado (no aplica a la lógica de negocio ya construida):**
6. `MigrarInventarioLegado.php`, conexión `pgsql_legacy`, `migrate_clientes_produccion.sql` — herramientas de corte a producción, no cambian el comportamiento del sistema en sí.

**Baja prioridad / mejora de alcance, no urgente:**
7. Refactor DRY de `diasVencido()` en `CuentaCobrarController` — cosmético, sin cambio de comportamiento.
8. Manuales PDF dinámicos — mejora de una feature secundaria (documentación de usuario), no de lógica de negocio.

---

## Lista completa de páginas frontend con diferencia de patrón `usePermiso` (sección b.1)

`Components/Ventas/DescuentoEspecialModal.tsx`, `Components/shared/BuscadorProductoModal.tsx`, `Components/shared/PageHeader.tsx`, `Components/shared/Sidebar.tsx`, `Components/shared/Topbar.tsx`, `Layouts/AuthLayout.tsx`, `Pages/Auth/Login.tsx`, `Pages/Bancos/BancosCajas/Index.tsx`, `Pages/Bancos/Cajas/Index.tsx`, `Pages/Bancos/Cheques/Index.tsx`, `Pages/Bancos/Conciliaciones/Index.tsx`, `Pages/Bancos/Conciliaciones/Show.tsx`, `Pages/Bancos/Datafast/Index.tsx`, `Pages/Bancos/Movimientos/Index.tsx`, `Pages/Compras/Anticipos/Index.tsx`, `Pages/Compras/Compras/Index.tsx`, `Pages/Compras/Compras/Show.tsx`, `Pages/Compras/CuentasPagar/Index.tsx`, `Pages/Compras/Devoluciones/Index.tsx`, `Pages/Compras/Proveedores/Index.tsx`, `Pages/Compras/Recepcion/Index.tsx`, `Pages/Configuracion/Empresa/Index.tsx`, `Pages/Configuracion/Permisos/Index.tsx`, `Pages/Configuracion/Usuarios/Form.tsx`, `Pages/Configuracion/Usuarios/Index.tsx`, `Pages/Contabilidad/Asientos/Index.tsx`, `Pages/Contabilidad/Asientos/Show.tsx`, `Pages/Contabilidad/Ejercicios/Index.tsx`, `Pages/Contabilidad/Parametros/Index.tsx`, `Pages/Contabilidad/PlanCuentas/Index.tsx`, `Pages/Dashboard/Index.tsx`, `Pages/Inventario/Activos/Form.tsx`, `Pages/Inventario/Activos/Index.tsx`, `Pages/Inventario/Activos/Show.tsx`, `Pages/Inventario/Configuracion/Bodegas/Index.tsx`, `Pages/Inventario/Configuracion/Categorias/Index.tsx`, `Pages/Inventario/Configuracion/Marcas/Index.tsx`, `Pages/Inventario/Kardex/Ajuste.tsx`, `Pages/Inventario/Kardex/Index.tsx`, `Pages/Inventario/Kardex/Saldos.tsx`, `Pages/Inventario/ListasPrecio/Index.tsx`, `Pages/Inventario/Productos/Form.tsx`, `Pages/Inventario/Productos/Index.tsx`, `Pages/Inventario/Recepciones/Index.tsx`, `Pages/Inventario/Recepciones/Show.tsx`, `Pages/Inventario/Traslados/Form.tsx`, `Pages/Inventario/Traslados/Index.tsx`, `Pages/Inventario/Traslados/Show.tsx`, `Pages/Manuales/Index.tsx`, `Pages/Personas/Clientes/Index.tsx`, `Pages/Personas/Proveedores/Index.tsx`, `Pages/Personas/Transportistas/Index.tsx`, `Pages/RRHH/Asistencia/Index.tsx`, `Pages/RRHH/Colaboradores/Index.tsx`, `Pages/RRHH/HorasExtras/Index.tsx`, `Pages/RRHH/Liquidaciones/Index.tsx`, `Pages/RRHH/Nomina/Index.tsx`, `Pages/RRHH/Nomina/Show.tsx`, `Pages/RRHH/Prestamos/Index.tsx`, `Pages/Taller/Diagnosticos/Form.tsx`, `Pages/Taller/Ingresos/Form.tsx`, `Pages/Taller/Ingresos/Index.tsx`, `Pages/Taller/Liquidacion/Show.tsx`, `Pages/Taller/OrdenesTrabajo/Show.tsx`, `Pages/Taller/TiposEquipo/Index.tsx`, `Pages/Ventas/CxC/Index.tsx`, `Pages/Ventas/Facturas/Form.tsx`, `Pages/Ventas/Facturas/Index.tsx`, `Pages/Ventas/GuiasRemision/Form.tsx`, `Pages/Ventas/GuiasRemision/Index.tsx`, `Pages/Ventas/NotasCredito/Form.tsx`, `Pages/Ventas/NotasCredito/Index.tsx`, `Pages/Ventas/Prefacturas/Form.tsx`, `Pages/Ventas/Prefacturas/Index.tsx`, `Pages/Ventas/Prefacturas/Show.tsx`, `Pages/Ventas/Proformas/Form.tsx`, `Pages/Ventas/Proformas/Index.tsx`, `Pages/Ventas/Proformas/Show.tsx`, `Pages/Ventas/Retenciones/Form.tsx`, `Pages/Ventas/Retenciones/Index.tsx`.

*(Nota: no se diffeó línea por línea cada uno de estos ~70 archivos individualmente por volumen — se confirmó el patrón por muestreo en 3 de ellos (RRHH/Colaboradores, Bancos/Movimientos, Inventario/Productos) más la revisión directa de otros 2. Si quieres el diff completo de alguno específico, pídemelo y lo reviso a fondo.)*

---

**No se copió, fusionó, ni sobrescribió ningún archivo en esta pasada.** Este documento es solo diagnóstico, como pediste.
