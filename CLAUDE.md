# CLAUDE.md — ERP Altamira

Este archivo guía a Claude Code en cada sesión de trabajo. Léelo completo antes de tocar cualquier archivo.

---

## Qué es este proyecto

ERP completo para **Altamira Light & Sound** (Ecuador) que reemplaza tres aplicaciones PHP legacy. Unifica ventas, inventario, contabilidad, RRHH y taller en una sola app con selector de empresa al iniciar sesión.

**Empresas del sistema:**
- Altamira Matriz (RUC: 1711293454001) — ventas locales
- Altamira Import (RUC: 1755265848001) — importaciones
- Altamira Fix — taller técnico interno (centro de costo de Matriz)

---

## Stack

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12, PHP 8.2+ |
| Frontend | React 19, Inertia.js v2, TypeScript |
| Build | Vite 7 + `@vitejs/plugin-react@5.1.4` (NO v6 — requiere Vite 8) |
| Estilos | Tailwind CSS v4 (sin `tailwind.config.js`, config en `app.css`) |
| BD | PostgreSQL — base de datos `altamira` |
| Auth | Laravel session + Spatie Permission v6 |
| Estado | Zustand (tema, empresa activa) |
| Tablas | TanStack Table v8 |
| Gráficos | Recharts |
| Iconos | Lucide React |
| Rutas tipadas | Ziggy v2 (`Tighten\Ziggy`, NO `Tightenco\Ziggy`) |

---

## Comandos esenciales

```bash
# PHP correcto (Laragon tiene PHP 8.1 por defecto en PATH — siempre especificar)
php artisan serve                    # desde terminal de Laragon (tiene PHP 8.2 en PATH)

# Frontend
npm run dev                          # desarrollo con hot reload
npm run build                        # compilar para producción

# Base de datos
php artisan migrate                  # correr migraciones
php artisan db:seed                  # datos iniciales
php artisan migrate:status           # ver estado de migraciones

# Limpieza de caché
php artisan config:clear
php artisan cache:clear
```

---

## Base de datos — CRÍTICO

La BD `altamira` es la **base de datos de producción del sistema legacy**. Las tablas ya existen con datos reales. El schema tiene nombres de columnas diferentes al estándar de Laravel:

### Diferencias clave del schema real vs convención Laravel

| Tabla | Columna real | Lo que esperarías |
|---|---|---|
| `empresas` | `cod_establecimiento` | `codigo_establecimiento` |
| `empresas` | `cod_punto_emision` | `codigo_punto_emision` |
| `empresas` | `agente_retencion` | `numero_resolucion_agente_retencion` |
| `empresas` | `firma_electronica_path` | `firma_electronica` |
| `empresas` | `ambiente_sri` | smallint (1/2), no enum |
| `permisos` | `puede_ver`, `puede_crear`, etc. | `ver`, `crear`, etc. |
| `limites_descuento` | `descuento_maximo_pct` | `porcentaje_maximo` |
| `limites_descuento` | `descuento_aprobacion_max_pct` | `porcentaje_aprobacion_max` |
| `limites_descuento` | requiere `empresa_id` NOT NULL | sin empresa_id |
| `log_sesiones` | `username`, `ip`, `fecha` | `email`, `ip_address`, `created_at` |
| `modulos` | `codigo` | `clave` |
| `secuenciales` | `secuencial` | `siguiente` |

### Tablas SIN timestamps
`perfiles`, `permisos`, `tipos_aprobacion`, `limites_descuento`, `log_sesiones`, `log_documentos`, `log_cambios_criticos`, `configuraciones`, `secuenciales`

En estos modelos siempre poner: `public $timestamps = false;`

### Reglas para migraciones nuevas

1. **Siempre** usar `Schema::hasTable()` antes de `Schema::create()`
2. **Siempre** usar `Schema::hasColumn()` antes de agregar columnas
3. Usar `firstOrCreate()` en seeders, nunca `create()` sin verificar
4. Las migraciones deben ser idempotentes (pueden correr múltiples veces sin error)

```php
// Patrón correcto para migraciones en este proyecto
public function up(): void
{
    if (!Schema::hasTable('nueva_tabla')) {
        Schema::create('nueva_tabla', function (Blueprint $table) {
            // ...
        });
    }
}
```

---

## Estructura de carpetas

```
app/
  Http/
    Controllers/
      Auth/           ← LoginController, EmpresaController
      Dashboard/      ← DashboardController
      Configuracion/  ← UsuarioController, PermisoController, EmpresaController
      Ventas/         ← (Fase 2) FacturaController, etc.
    Middleware/
      HandleInertiaRequests.php   ← comparte auth, empresa_activa, ziggy, flash
      VerificarUsuarioActivo.php  ← verifica usuario.estado = true en cada request
      SetEmpresaActiva.php        ← inyecta empresa activa desde sesión
  Models/             ← 14 modelos, todos adaptados al schema real
  Services/
    AuditoriaService.php          ← registra log_sesiones y log_documentos
  Observers/
    UsuarioObserver.php           ← registra cambios críticos en log_cambios_criticos

resources/js/
  Pages/
    Auth/             ← Login.tsx, SeleccionarEmpresa.tsx
    Dashboard/        ← Index.tsx + widgets/
    Configuracion/    ← Usuarios/, Permisos/, Empresa/
    Ventas/           ← (Fase 2) crear aquí
  Layouts/
    AppLayout.tsx     ← Layout principal con sidebar y topbar
    AuthLayout.tsx    ← Layout para login
  Components/
    ui/               ← Button, Input, Label, Badge (componentes base)
    shared/           ← Sidebar, Topbar, PageHeader, SkeletonCard, ConfirmModal
  Stores/
    themeStore.ts     ← dark/light mode con Zustand
    empresaStore.ts   ← empresa activa
  Hooks/
    usePermiso.ts     ← hook: usePermiso('ventas').puede('crear')
  lib/
    utils.ts          ← cn(), formatMoneda(), formatFecha()
  types/
    index.ts          ← todas las interfaces TypeScript del proyecto
```

---

## Autenticación y contexto de empresa

- La empresa activa se guarda en `session('empresa_activa_id')` (server-side)
- `HandleInertiaRequests` la comparte como `empresa_activa` a todos los componentes React
- El middleware `SetEmpresaActiva` la inicializa automáticamente si no hay ninguna
- Para cambiar de empresa: `POST /empresa/cambiar` con `{ empresa_id: X }`

**Acceder a la empresa activa en un Controller:**
```php
$empresaId = session('empresa_activa_id');
$empresa = Empresa::findOrFail($empresaId);
```

**Acceder en React:**
```tsx
const { empresa_activa, auth } = usePage<PageProps>().props
```

---

## Convenciones del proyecto

### PHP / Laravel

```php
// Casts en modelos — usar método, no propiedad (Laravel 12)
protected function casts(): array {
    return ['estado' => 'boolean'];
}

// Registrar middleware en bootstrap/app.php (NO existe Kernel.php en Laravel 12)
$middleware->web(append: [...]);

// Auditoría en cada controller action importante
$this->auditoria->documento('crear', 'ventas', 'facturas', $factura->id, "Factura {$factura->numero} creada");
```

### TypeScript / React

```tsx
// Tipos — siempre definir interface, nunca usar `any`
import type { PageProps } from '@/types'

// Página Inertia — siempre usar usePage() para props
const { auth, empresa_activa } = usePage<Props>().props

// Estilos — usar CSS variables, no clases Tailwind hardcodeadas para colores principales
style={{ color: 'var(--text-main)', background: 'var(--bg-card)' }}

// Clases utilitarias con cn()
import { cn } from '@/lib/utils'
className={cn('base-class', condicion && 'conditional-class')}
```

### CSS variables disponibles

```css
--bg-main        /* fondo principal de la página */
--bg-card        /* fondo de cards y paneles */
--text-main      /* texto principal */
--text-muted     /* texto secundario/gris */
--border         /* color de bordes */
--primary        /* #F59E0B — dorado Altamira */
--primary-hover  /* #D97706 */
--sidebar-bg     /* fondo del sidebar */
--sidebar-active /* #F59E0B — ítem activo */
```

---

## Cómo agregar un módulo nuevo (ejemplo: Ventas)

### 1. Migración (si se necesita tabla nueva)
```bash
php artisan make:migration create_facturas_table
```
Recordar: agregar guard `if (!Schema::hasTable('facturas'))`.

### 2. Modelo
```
app/Models/Factura.php
```
Verificar si la tabla ya existe en el schema legacy antes de definir columnas.

### 3. Controller
```
app/Http/Controllers/Ventas/FacturaController.php
```

### 4. Ruta en `routes/web.php`
```php
Route::middleware('auth')->prefix('ventas')->name('ventas.')->group(function () {
    Route::get('/facturas', [FacturaController::class, 'index'])->name('facturas.index');
    // ...
});
```

### 5. Páginas React
```
resources/js/Pages/Ventas/Facturas/Index.tsx
resources/js/Pages/Ventas/Facturas/Form.tsx
```

### 6. Rebuild del frontend
```bash
npm run build
```

---

## Variables de entorno importantes

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=altamira
DB_USERNAME=postgres
DB_PASSWORD=gbyte              # contraseña local

SESSION_DRIVER=file            # NO usar database — la tabla sessions no existe en el schema legacy
QUEUE_CONNECTION=database      # requiere un worker corriendo — ver sección "Colas" más abajo

APP_URL=http://127.0.0.1:8000  # ajustar según entorno
```

---

## Colas (queue:work) — requerido solo para el ZIP de Nómina

**Decisión (2026-08-03):** el patrón "límite de filas + Job en cola +
notificación" que se había extendido a Facturas de Compra, Cuentas por
Pagar, Movimientos Bancarios, Reportes Contables (Libro Diario/Mayor),
Proveedores y Asientos Contables (Excel/PDF) fue revertido — las seis
exportaciones vuelven a generarse **siempre de forma síncrona**, sin
límite de filas ni Job, con `ini_set('memory_limit', ...)` en el propio
controller para cubrir el mismo margen que antes tenía el Job (DomPDF/
PhpSpreadsheet en tablas grandes no escala bien por debajo de 512M — ver
comentarios en cada método `pdf()`/`excel()`). El único endpoint que
sigue en segundo plano es el **ZIP de roles de pago de Nómina**
(`NominaController::pdfMasivo()` → `App\Jobs\ExportarNominaZipJob`) — así
lo pidió el cliente explícitamente, no tocar esa decisión sin pedirlo.

`QUEUE_CONNECTION=database` (la tabla `jobs` ya está migrada). A diferencia de
los 4 Jobs de alertas (`AlertaVencimientoCxP`, `AlertaVouchersNoLiquidados`,
`AlertaAtrasosRecurrentes`, `RecordatorioCierreNomina`), que solo se disparan
vía `Schedule::job()` en `routes/console.php` y por eso no necesitan un worker
persistente para funcionar en desarrollo (el scheduler los ejecuta inline en
su propio tick), **`App\Jobs\ExportarNominaZipJob`** se dispara desde una
acción real del usuario y **se queda esperando en la tabla `jobs` para
siempre si no hay un worker corriendo**.

```bash
# Requerido para que el ZIP de roles de pago de Nómina funcione:
php artisan queue:work

# En producción, correr esto bajo Supervisor (o systemd) para que se
# reinicie solo si el proceso muere. No hay Procfile ni supervisor.conf en
# este repo todavía — agregarlo es responsabilidad del deploy.
```

El Job de limpieza `LimpiarExportacionesNominaJob` (borra archivos de
`storage/app/private/exportaciones-nomina/` con más de 48h) SÍ está
programado vía `Schedule::job()->dailyAt('03:25')`, así que ese no necesita
un worker aparte — pero el propio `schedule:run` sí necesita correr (cron o
`php artisan schedule:work` en desarrollo).

**Link de descarga en la notificación:** se arma con el host REAL de la
request que disparó el Job (`$request->getSchemeAndHttpHost()`, capturado en
el controller y pasado al Job), nunca con `route()` a secas ni con
`config('app.url')` — dentro de un Job no hay request activa, así que
`route()` cae al host fijo de `config/app.php`, que puede no coincidir con
el que realmente sirvió la petición. La construcción está centralizada en
el trait `App\Jobs\Concerns\ConstruyeUrlDescargaExportacion` (método
`urlDescarga()`) — cualquier Job nuevo que notifique un link de descarga
debe usar este trait en vez de repetir la concatenación a mano.

**`max_execution_time`:** ya está en 36000s en el php.ini activo de este
entorno (Laragon) — muy por encima de lo que tarda cualquiera de estas
exportaciones (peor caso medido: ~3 min con el histórico completo de
Asientos sin filtro). No fue necesario subirlo. Verificar el valor real en
el servidor de producción cuando exista, ya que php.ini no viaja con el
repo.

**Hallazgo residual sin resolver — Mayor Contable / Libro Diario / Asientos
Contables (reporte PDF) en el caso extremo sin ningún filtro:** DomPDF
(`Cellmap::resolve_border()`) escala peor que lineal con el número de FILAS
DENTRO DE UNA MISMA `<table>`. Confirmado con datos reales y con una prueba
mínima (tabla de texto plano sin ningún estilo, ~4,800 filas en una sola
tabla, revienta memory_limit igual) — no es una consulta N+1 ni un índice
faltante (verificado con `EXPLAIN ANALYZE` real: la query del rango de un
año de Libro Diario tarda 4-7ms; la hidratación completa vía Eloquent con
eager load tarda 0.6s para 4,438 asientos). El costo real está 100% en el
render de DomPDF.

- Mayor Contable de la cuenta más activa (1.1.4.01, 6,218 líneas, histórico
  completo) agota memory_limit tanto en 2560M como en 4096M.
- Libro Diario de un año completo (4,438 asientos) no completó en 600s
  (10 min) con `timeout` real, aun con la plantilla usando el patrón
  correcto (una `<table>` por asiento — ver nota en
  `resources/views/pdf/libro-diario.blade.php`).
- Asientos Contables (reporte PDF, no Excel) sin ningún filtro (11,177
  asientos) no completó en 300s (5 min) con memory_limit en 4096M.

**IMPORTANTE — no "optimizar" fusionando en una sola tabla:** se intentó
fusionar `libro-diario.blade.php` en una única `<table>` continua para el
documento completo (menos objetos que arma DomPDF) y midió MUCHO PEOR que
el patrón original de muchas tablas chicas (una por asiento) — una tabla
continua de ~4,800 filas sin ningún estilo ya revienta memory_limit por
defecto (512M), mientras que 1,051 tablas chicas (3 meses) completan en
~100s. `Cellmap::resolve_border()` escala con el total de filas de UNA
tabla, así que partir el documento en muchas tablas chicas es lo que
realmente lo hace escalar, no al revés — quedó documentado en un comentario
dentro del propio blade para que no se repita el error.

Con un rango acotado mayor al límite viejo que existía antes de la
reversión (ej. Mayor con 1,176 líneas / 6 meses: 14s: Libro Diario con
~2,700 líneas / 3 meses: 98s; Asientos PDF con ~2,100 asientos / 6 meses:
90s) genera bien. El caso 100% sin filtro (todo el historial multi-año)
sigue sin funcionar en un tiempo razonable en ninguno de los 3 reportes —
es un límite real del motor de render (DomPDF), no algo que se resuelva
con más memoria o restructurando el HTML. Si el cliente necesita
específicamente ese caso extremo, las opciones son: (a) forzar un rango de
fechas por defecto razonable en el selector (ej. el ejercicio fiscal
actual) en vez de "sin fecha = todo el historial" — esto es un valor por
defecto en el filtro, no un bloqueo, el usuario puede seguir ampliándolo
si quiere esperar; o (b) cambiar de motor de PDF / paginar el render
manualmente (trabajo mayor, no evaluado). No se impuso ninguna de las dos
sin confirmar con el cliente.

---

## Lo que está hecho (Fase 1)

- ✅ Autenticación completa con rate limiting
- ✅ Selección y cambio de empresa
- ✅ AppLayout: sidebar colapsable, topbar, dark/light mode
- ✅ Dashboard con 6 widgets (Recharts)
- ✅ Configuración → Usuarios (CRUD completo)
- ✅ Configuración → Permisos (matriz por perfil)
- ✅ Configuración → Empresa (datos SRI, secuenciales)
- ✅ AuditoriaService (log_sesiones, log_documentos)
- ✅ 18 migraciones idempotentes
- ✅ Seeders completos

## Pendiente (Fase 2)

- ⏳ Ventas: facturas SRI, proformas, CxC, notas de crédito
- ⏳ Compras: órdenes, proveedores, CxP, importaciones
- ⏳ Inventario: productos, kárdex, bodegas, traslados
- ⏳ Contabilidad: asientos, plan de cuentas, períodos
- ⏳ Bancos: movimientos, cajas, Datafast, conciliación
- ⏳ RRHH: nómina, colaboradores, asistencia, préstamos
- ⏳ Taller: órdenes de trabajo, equipos, diagnósticos
- ⏳ Reportes

---

## Credenciales de prueba

```
Super Admin:
  email: admin@altamira.com
  password: ver credenciales compartidas por canal seguro con el equipo
  PIN aprobación: ver credenciales compartidas por canal seguro con el equipo
  empresas: Altamira Matriz + Altamira Import

Vendedor:
  email: vendedor@altamira.com
  password: ver credenciales compartidas por canal seguro con el equipo
  empresas: solo Altamira Matriz
```

---

## Errores conocidos y sus soluciones

| Error | Causa | Solución |
|---|---|---|
| `Class "Tightenco\Ziggy\Ziggy" not found` | Namespace incorrecto | Usar `Tighten\Ziggy\Ziggy` |
| `SESSION_DRIVER=database` → 500 | Tabla `sessions` no existe en schema legacy | Usar `SESSION_DRIVER=file` |
| `PHP version >= 8.2.0 required` | PATH apunta a PHP 8.1 | Usar terminal de Laragon o ruta completa a PHP 8.2 |
| `empresa_id NOT NULL en log_sesiones` | log_sesiones no tiene esa columna | No insertar empresa_id en log_sesiones |
| `updated_at en perfiles` | perfiles no tiene timestamps | Agregar `public $timestamps = false` |

---

## Decisiones de diseño intencionales (no son bugs)

### Compra sin asiento contable si el período está cerrado

**Comportamiento:** en `CompraController::store()` y en el flujo de confirmación de recepción (`RecepcionController`), la generación del asiento contable automático (`AsientoService::compraRegistrada()`) está envuelta en un `try/catch` que **no bloquea la operación**. Si el asiento falla — típicamente porque la `fecha_emision` de la Compra cae dentro de un período contable ya cerrado (ver `Ejercicios Contables` y `AsientoService::crear()`) — la Compra se guarda igual, con `asiento_id = null`.

**Por qué es intencional:** no se quiere que un problema de configuración/candado contable bloquee por completo la operación comercial (recibir mercadería, registrar la factura del proveedor). Esta decisión fue confirmada explícitamente por el cliente/usuario del sistema (2026-07-26) — **no cambiar esta lógica** sin que te lo pidan explícitamente.

**Cómo queda visible (para que no sea un huérfano silencioso):**
1. La columna `compras.asiento_error` (texto, nullable) guarda el mensaje de la excepción cuando esto ocurre. Se limpia (`null`) si el asiento se genera exitosamente después.
2. `AsientoService::notificarAsientoFallido()` inserta una notificación (tabla `notificaciones`, campanita del Topbar) para cada usuario con perfil `super_admin` o `contador` con acceso a la empresa, y una entrada en `log_documentos` (acción `asiento_fallido`) para auditoría.
3. La UI muestra un badge naranja "Sin asiento contable" (con el motivo en tooltip) tanto en `Compras/Compras/Index.tsx` (icono junto al número de documento) como en `Compras/Compras/Show.tsx` (badge junto al estado + línea de detalle).

**Alcance real de esta excepción — NO se extiende a Pagos ni a Nómina:** `CuentaPagarController::pagar()` (pago a proveedor) y `NominaController::procesar()` **no** tienen este patrón de "guardar igual si falla" — ahí la generación del asiento ocurre dentro de un único `DB::transaction()` sin `catch` silencioso, así que si el período está cerrado la operación completa se revierte y el usuario ve un error inmediato (no queda un pago o una nómina huérfana sin asiento). Si en el futuro se decide extender el patrón "guardar sin asiento" a Pagos o Nómina, es una decisión de diseño nueva — no asumir que ya funciona igual que Compras.

### ACTUALIZACIÓN (2026-09-20) — la decisión anterior fue REEMPLAZADA: contabilidad lista antes de operar

**Reemplaza** la sección "Compra sin asiento contable si el período está cerrado" de arriba, a pedido explícito del usuario. Ahora el sistema **bloquea** (no guarda igual) las operaciones que generan asientos si la parte contable no está lista.

- `AsientoService::validarConfiguracion($empresaId, $codigos, $fecha)` verifica (1) que haya un período abierto y que el mes de la fecha no esté cerrado, y (2) que cada parámetro contable requerido resuelva a una cuenta. Lanza `\DomainException` con un mensaje que dice qué hacer (Contabilidad → Ejercicios / Parámetros Contables). `AsientoService::codigosCompra($tipoAsiento, $retIR, $retIVA)` da los parámetros que usa `compraRegistrada()` — mantener en sincronía con esa función.
- Se valida en: `CompraController::store()` y `activar()`, `RecepcionController::confirmar()`, `ImportacionController::liquidar()` (solo período) y `CuentaPagarController::pagar()` (`cta_proveedores_locales` + `cta_bancos_locales`). Todos devuelven `back()->with('error', ...)`. `pagar()` además atrapa cualquier excepción del asiento y devuelve un error legible en vez de un 500.
- `compras.asiento_error` y el badge "Sin asiento contable" siguen existiendo para facturas antiguas; no deberían generarse nuevas.
- No cubierto aún: `CompraController::update()` (edición de factura activa) y Anticipos (este último ya devuelve error controlado desde su propio try/catch).

### Auditoría y corrección del módulo de Contabilidad (2026-09-20)

Revisión completa del módulo. Lo que cambió y **no debe revertirse**:

**Plan de cuentas — formato de códigos.** El plan real del cliente mezcla dos
formatos: clases 1, 2, 3 y 5.1 usan un dígito en el último segmento
(`1.1.1.1` Caja General), mientras que 4, 5.2, 5.3 y 5.4 usan dos
(`4.1.1.01`, `5.2.2.06`). El mapa `AsientoService::FALLBACK_PLAN` estaba
escrito íntegramente con dos dígitos, así que **26 de 42 cuentas no existían**
y ningún asiento automático llegaba a generarse. Se corrigieron los códigos y
se agregó `AsientoService::normalizarCodigo()` / `buscarCuentaPorCodigo()`,
que comparan ignorando los ceros a la izquierda. **No "normalizar" el plan de
cuentas en la BD** — se resuelve en código.

Además había códigos semánticamente equivocados ya persistidos en
`parametros_contables` (el aporte patronal apuntaba a "Comisiones y Bonos", la
utilidad del período a "Ganancias Acumuladas", las ganancias acumuladas a
"Superávit por Revaluación PPE"). Los corrige la migración
`2026_09_20_110001_corregir_parametros_contables_mal_mapeados`.

**Costo de ventas.** `facturaAutorizada()` ahora registra las dos mitades del
inventario permanente: el ingreso *y* `DEBE Costo de Ventas / HABER
Inventario`. El costo se lee del kárdex con
`AsientoService::costoSalidaDocumento()` para que contabilidad e inventario
no puedan divergir. Antes no se registraba nunca: el inventario solo crecía y
el Estado de Resultados mostraba costo cero.

**Períodos contables.** Un asiento pertenece al ejercicio de **su propia
fecha** (antes se colgaba del último período abierto). Se permiten varios
meses abiertos a la vez y `reabrir()` funciona de verdad (super_admin o
contador), bloqueado solo si el año ya tiene cierre fiscal. El candado
permanente es el Cierre Fiscal Anual, no el cierre mensual.

**Balance General.** Es un corte acumulado a una fecha (`fecha_hasta`), no un
mes, e incluye la línea "Resultado del ejercicio en curso"
(`resultadoAcumulado()`). Ese método **debe** incluir los asientos
`CIERRE_ANUAL` en su suma: así devuelve la utilidad pendiente antes del cierre
y 0 después, evitando contarla dos veces contra Ganancias Acumuladas.

**Numeración.** `AsientoContable::generarNumero()` filtra por el prefijo
`AS-{año}-` y calcula el máximo sobre el secuencial como entero. Con el
`max('numero')` de texto anterior, el asiento `CIERRE-2026` ganaba la
comparación y el siguiente número saltaba a `AS-2026-2027`. Hay índice único
`(empresa_id, numero)` y `crear()` reintenta ante colisión.

**Reportes.** Los cuatro estados financieros agregan con un solo `GROUP BY`
(antes: 1-2 consultas por cuenta, ~400 por PDF). Todos imprimen el período que
cubren. El Mayor trae saldo anterior y saldo corrido; el Estado de Resultados
llega hasta la utilidad neta pasando por utilidad bruta, 15% de participación
y 25% de IR (referencial, no es la conciliación tributaria del SRI).

### Recepción de bodega: se crea al guardar la factura (2026-09-20)

Flujo con dos roles: el administrador registra la factura/importación; el **bodeguero** (perfil con permiso `inventario` pero solo `ver` en Compras) confirma el ingreso con la pistola de código de barras desde Inventario → Recepciones.

- `CompraController::store()` crea la recepción pendiente (`crearRecepcionPendiente()`, idempotente) si la compra tiene productos y bodega. `update()` de una factura pendiente la regenera (borra recepción, escaneos y etiquetas); `destroy()` y `anular()` de una factura pendiente la eliminan. `RecepcionController::confirmar()` rechaza compras anuladas; `activar()` rechaza facturas con recepción pendiente (evita doble ingreso de stock).
- Las etiquetas se generan también desde la pantalla de la recepción (`inventario.recepciones.etiquetasData` / `etiquetasPdf`, permiso `inventario,editar`), que reutilizan `CompraController::etiquetasData()` y `generarEtiquetasPdf()`. Sin etiquetas generadas no hay nada que escanear (`escanear()` busca `EtiquetaProducto` por `compra_id`).
- Hasta que bodega confirma: la factura queda *Pendiente*, no entra stock y no existe la Cuenta por Pagar.

### Bancos conectado a Ventas y Nómina (2026-09-20)

- **Cobros de CxC** (`CuentaCobrarController::registrarCobro`) y **pago de Nómina** (`NominaController::pagar`) exigen elegir el banco/caja; crean un `MovimientoBancario` (`documento_tipo` `COBRO_CXC` / `NOMINA`) y actualizan el saldo. Cheques (`ChequesController::store`) pagan una CxP (`documento_tipo=CXP`) o "otro pago" con cuenta de contrapartida. Transferencias entre cuentas: `MovimientoBancarioController::transferir` (dos movimientos `TRANSFERENCIA` enlazados; se anulan en pareja).
- **Facturas de venta** (`CobroBancoService`): al emitir, cada forma de pago (menos crédito) genera un ingreso en Bancos (`FACTURA_VENTA`); anular/eliminar la factura lo revierte (`ANULACION_FACTURA`). **Es configurable por el usuario** en Bancos → Configuración de cobros (tabla `configuraciones`, claves `cobro_*`): caja de efectivo por defecto, banco de transferencias, cheques y tarjeta, y modo de tarjeta (`banco` | `datafast`). El efectivo entra a la caja del mismo `centro_costo_id` de la factura. **Si no hay configuración, la factura funciona como antes y no crea movimientos** (nunca bloquea la venta).
- **Cierre de caja**: `total_facturado` sale de las ventas del día del centro de costo de la caja (`CobroBancoService::ventasEsperadas`, sin anuladas ni crédito) y los montos del cierre se precargan con eso. La caja toma el centro de costo de `bancos_cajas` si no se indica al abrir.
- Los movimientos con `documento_tipo` `DATAFAST`, `NOMINA`, `COBRO_CXC`, `CXP` y `FACTURA_VENTA` no se anulan desde Movimientos (se hace desde su módulo de origen).
