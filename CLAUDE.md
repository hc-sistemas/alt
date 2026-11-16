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

### Plan de cuentas 2025 (2026-10-05) — reemplaza el plan NIIF anterior

Migración `2026_10_05_100000_reemplazar_plan_cuentas_2025` carga el "PLAN DE CUENTAS 2025 ALTAMIRA" (`docs/`, formato `1.01.01.01`, clase 5 = Costos, clase 6 = Gastos, persona natural). Actualiza en sitio las cuentas equivalentes (conserva ids), fusiona las que no tienen equivalente directo, reapunta asientos/bancos/parámetros y elimina las sobrantes sin movimientos (con movimientos quedan inactivas, prefijo `OBS-`). Se agregaron 3 cuentas "(General)" (`4.01.06`, `5.01.05`, `1.01.04.05`) porque el Excel solo trae venta/costo/inventario por línea de producto y el sistema contabiliza en una sola cuenta. `AsientoService::FALLBACK_PLAN` y `PARAMETROS` de la migración deben mantenerse en sincronía. Los reportes (`ReporteContableController`) clasifican por prefijo: costos `5.`, financieros `6.01.10`, no deducibles `6.02`. `productos.cuenta_*` no se tocó. Pendiente de decisión contable: anticipos de clientes/proveedores, Datafast e IESS personal/patronal comparten cuenta por no existir una propia en el Excel.

### IVA vigente: 15 % (confirmado por el usuario, 2026-11-03)

- La tarifa general de IVA del sistema es **15 %**. Todo producto, servicio o línea nueva que grave IVA nace al 15 %.
- **Productos nuevos del Excel de inventario** (`inventario:cargar-excel`) se crean con `porcentaje_iva = 15` (constante `IVA_NUEVOS` del comando). No usar 12 %.
- **Servicio técnico de Taller:** el IVA de la mano de obra es configurable por empresa en Taller → Configuración (clave `taller_servicio_iva_pct`) y su valor por defecto es 15 %.
- **Productos migrados del legacy:** 1.644 de los 1.646 originales siguen con 12 % en `productos.porcentaje_iva` (dato heredado, anterior al 15 %). Para llevarlos al IVA vigente existe `php artisan altamira:actualizar-iva-productos` (simula por defecto, `--iva=15` es el valor por omisión, `--aplicar` ejecuta; solo toca productos que ya gravan IVA, los de 0 % no se tocan). **Se ejecuta en producción, no en local** (decisión del usuario, 2026-11-03). Pasos para el despliegue: `php artisan altamira:actualizar-iva-productos` (simula y muestra cuántos cambian), revisar y luego `--aplicar`.
- No escribir 12 ni 15 a mano en el código nuevo: leer `porcentaje_iva` del producto, o del parámetro/configuración correspondiente.

### Cuentas contables por subcategoría del producto — modo ESTRICTO (2026-11-04)

Decisión del usuario (módulo B, DEC-012 y DEC-030): la **venta, el costo y el inventario** de cada producto salen de su **subcategoría** (`subcategoria_cuentas`, PC-CAT-01 a 20), no de parámetros genéricos.

- `AsientoService::cuentasProductos($ids, $empresaId)` resuelve las cuentas y es **estricto**: un producto sin subcategoría con cuentas **no se factura, no se compra, no se ajusta y no se devuelve** (se bloquea con un mensaje que lista los productos). Solo Computadoras (PC-CAT-19) y PISOS LED (PC-CAT-20) usan «sin clasificar», porque así está en su fila. Los productos tipo `servicio` venden en `cta_ingresos_servicios` y no mueven inventario; los regalos no tienen cuenta de venta.
- Se validan **antes** de guardar: `FacturaController::store`, `CompraController::store/activar` y `RecepcionController::confirmar`. `facturaAutorizada()`, `compraRegistrada()`, `notaCreditoEmitida()` y la devolución de compra contabilizan **por línea** (varias cuentas en un mismo asiento).
- **Ya no existe cuenta de gasto por defecto** (DEC-012): una línea de compra sin producto exige elegir su cuenta. Se eliminaron los parámetros `cta_gasto_compras_default`, `cta_ventas_locales`, `cta_costo_ventas`, `cta_costo_ventas_importadas` y `cta_inventario_mercaderia` (migración `2026_11_04_100000`).
- **Consecuencia operativa:** al 04/11/2026 unos 1.209 de 1.672 productos están en una categoría con subcategorías sin elegir una (el Excel del cliente no trae categorías) y **no se pueden vender ni comprar hasta clasificarlos**. Herramientas: `inventario:sugerir-reclasificacion` genera el CSV; el contador/bodega lo revisan escribiendo SI en «confirmar»; `inventario:aplicar-reclasificacion` lo aplica (simula por defecto; `--aceptar-sugerencias` da por buenas las 428 sugerencias automáticas; unos 786 productos necesitan clasificación manual).

### Sin banco por defecto (2026-11-04)

Documento del cliente (01 §5, 02-04 §2.2): **el banco se elige al cobrar o pagar; no existe banco por defecto.** El parámetro `cta_bancos_locales` (que apuntaba a Banco Pichincha) se eliminó (migración `2026_11_04_110000`).

- La cuenta contable sale siempre del banco elegido (`bancos_cajas.cuenta_id`). `AsientoService::cuentaBancoElegida()` bloquea con `\DomainException` si no se indicó banco; `cuentaCobroPorForma()` bloquea transferencia/depósito sin banco o con un banco sin cuenta contable.
- **El vendedor elige el banco en la factura** (transferencia: obligatorio, ya validado en `FacturaController`). Cheque en factura: queda en cartera dentro de Clientes hasta depositarse (no toca Bancos). Nota de crédito: devuelve por el mismo banco con que se pagó la factura.
- Pagos y cobros que antes ignoraban el banco elegido y asentaban en Pichincha: CxC (`cobro`), CxP y Cheques (`pagoProveedor`), ajustes de conciliación. Préstamos/anticipos y liquidaciones de RRHH ahora piden elegir banco o caja.
- Pendiente del cliente: DEC-040 (banco obligatorio en depósito y tarjeta) y DEC-038 (caja y banco de Altamira Import).
- Nota: préstamos y liquidaciones asientan contra el banco elegido pero **no** crean movimiento en Bancos (igual que antes); si se quiere que el saldo del banco baje, es un cambio aparte.

### Bancos — libro de movimientos (C.3, 2026-11-04)

El libro (Bancos → Movimientos) muestra fecha, concepto, referencia, debe (ingreso), haber (egreso), saldo corrido, conciliado Sí/No, fecha de conciliación y comentario. El saldo corrido = saldo inicial + movimientos no anulados y **solo se calcula al filtrar un banco o caja**. `fecha_conciliacion` se llena en todos los puntos donde `ConciliacionController` marca `conciliado`. La **referencia es obligatoria** en: movimiento manual por transferencia/cheque/depósito, factura con transferencia, cobro de CxC no efectivo y pago de CxP.

### Bancos — conciliación (C.4, 2026-11-04)

- **Anular conciliación** (`ConciliacionController::anular`, permiso `bancos,anular`): una conciliación cerrada no se modifica sin anularla. Anular la **reabre** (estado `pendiente`), libera los movimientos conciliados, reversa el asiento y el saldo del ajuste por diferencia (6.01.14) y deja el motivo en `observacion`. Solo la conciliación cerrada más reciente de cada banco puede anularse. Las notas del banco (comisión 6.01.10, intereses) son movimientos reales: se conservan, solo vuelven a quedar pendientes.
- **Plantilla CSV** descargable por banco (`bancos.conciliaciones.plantilla`: Fecha; Descripcion; Debito; Credito; Referencia). Los formatos propios de cada banco (Pichincha, ProduBanco, Guayaquil) no están en los documentos: cuando el cliente envíe un extracto de ejemplo de cada uno se ajusta el importador.
- **Pendiente de confirmar con el contador:** la fórmula del documento 02-06 §2.4 (`libro + depósitos en tránsito − cheques no cobrados ± notas = saldo según banco`) está invertida respecto de la lógica contable; el sistema calcula `libro − depósitos en tránsito + cheques no cobrados ± notas`, que es la correcta.

### Bancos — cheques emitidos (C.5, 2026-11-04)

- **«Cobrado» solo al conciliar:** ya no se marca a mano. `Cheque::sincronizarConciliacion()` lo pasa a `cobrado` cuando su movimiento se concilia (importación con emparejamiento, cruce manual o cierre) y lo devuelve a `emitido` si se anula esa conciliación. La fecha de cobro prevista se conserva.
- **Anular / protestar** (motivo obligatorio): reversa asiento y movimiento, devuelve el saldo al banco y restituye la cuenta por pagar; el número queda registrado y no se reutiliza (único por banco). La pantalla tiene el botón «Anular», que antes no existía.
- **Post fechado:** cuando la fecha de cobro es posterior a la de emisión el movimiento se marca `es_postfechado` y la lista muestra la insignia. Las columnas legadas `cheques.banco` y `cheques.cuenta` (banco emisor / N° cuenta) ya no se piden en el formulario y no se borraron por ser datos legados.
- «Otro pago» (PC-BAN-18) sigue pendiente de DEC-027; hoy usa la cuenta de contrapartida elegida.

### Bancos — configuración de cobros (C.6, 2026-11-04)

- Se quitó el «banco de transferencias» fijo: el banco de cada transferencia se elige en la factura (01 §5). Quedan cheques (banco donde se depositan, obligatorio), tarjeta (`datafast` = «por liquidar» a 1.01.03.04 `cta_vouchers`, o directo a un banco) y la caja de efectivo por defecto.
- Un cheque en factura entra al banco de cheques configurado (mismo destino del movimiento en Bancos y del asiento); sin configurar, la factura se bloquea con un mensaje claro.
- La pantalla muestra la **cuenta contable** de cada banco/caja elegido antes de guardar y una tabla de **facturas ya registradas por destino**. Los cambios solo afectan a facturas nuevas (la configuración se lee al emitir; las anulaciones usan los movimientos guardados).
- **Caja por defecto eliminada (decisión del usuario, 2026-11-04):** el efectivo entra solo a la caja del centro de costo de la factura; si el centro no tiene caja, ese efectivo no se registra en Bancos (la contabilidad sigue usando la caja general). Migración `2026_11_04_130000` borra las claves `cobro_caja_efectivo` y `cobro_banco_transferencia`.
- Pendiente: caja y banco de Altamira Import (DEC-038, Edwin).

### Bancos — reportes (C.7, 2026-11-04)

- Los 3 reportes existentes cumplen 02-06 §2.7: estado de cuenta (saldo inicial/final y columna conciliado), movimientos (filtros por tipo y documento de origen) y reporte de caja, que ahora incluye la columna **Depósitos** (efectivo que sale de la caja hacia un banco el día del cierre, `documento_tipo=TRANSFERENCIA`).
- Los 8 reportes nuevos (conciliación, cheques pendientes, post fechados recibidos, tarjetas por liquidar, liquidaciones de tarjetas, saldos consolidados, libro frente a mayor, diferencias de conciliación) más comisiones y retenciones de tarjetas generan en PDF y Excel.
- «Cheques post fechados recibidos» lista ingresos con `es_postfechado`; mientras el módulo H no registre los cheques recibidos de clientes, saldrá vacío.

### Bancos — validaciones (C.8, 2026-11-04)

- **Saldo negativo con confirmación (DEC-041 sigue pendiente de Edwin; se implementó la advertencia que pide el documento):** `App\Support\SaldoBancario::advertir()` detiene todo egreso que deja un banco o caja en negativo (movimiento manual, transferencia entre cuentas, cheque, pago de CxP, anticipo a proveedor y pago de nómina) y devuelve la advertencia en el flash `saldo_negativo`. La pantalla (`resources/js/lib/saldoNegativo.ts`) pregunta y reenvía con `confirmar_saldo_negativo=1`; la confirmación queda en `log_cambios_criticos` (campo `saldo_negativo_confirmado`). Antes todos estos flujos bloqueaban con «Saldo insuficiente».
- **Dos decimales:** regla `decimal:0,2` en los montos de movimientos, transferencias, cheques, pago de CxP, cobro de CxC y anticipos a proveedor.
- **Débito = crédito:** ya lo exige `AsientoService::crear()`. **Movimiento conciliado:** no se puede anular (`MovimientoBancarioController::anular`) y el cheque conciliado queda cobrado, sin anulación posible.

### Caja (D, 2026-11-04)

- **Solo efectivo:** un movimiento manual de una caja solo admite forma de pago efectivo (backend y pantalla) y no puede apuntar a 4.01.09 ni 5.01.08. En una transferencia entre cuentas el lado de la caja se registra como `efectivo` y el banco que recibe como `deposito`.
- **Saldo negativo en caja:** se **bloquea siempre** (no hay confirmación, a diferencia de los bancos). Lo hace `SaldoBancario::advertir()` según `bancos_cajas.tipo`. DEC-041 sigue pendiente.
- **Apertura / cierre** (`CierreCajaController` + `CajaService`): conteo por denominación solo el primer día; desde el segundo, saldo inicial = efectivo contado del cierre anterior; el cierre calcula facturas, facturado y cobrado en efectivo (excluye anuladas y otras formas de pago); diferencia = conteo − esperado, con observación obligatoria y asiento a 6.01.14 (`CIERRE_CAJA`); un día cerrado no se reabre ni modifica.
- Se quitaron del respaldo del plan (`AsientoService::FALLBACK_PLAN`) las cuentas «sin clasificar» 4.01.09 y 5.01.08 para venta y costo genéricos; una factura sin líneas de producto ya no se contabiliza contra una cuenta genérica.
- Pendiente: caja de Altamira Import (DEC-038) y contrapartida del conteo inicial (DEC-011).

### Tarjetas (E, 2026-11-04)

- **Lote con ventas seleccionadas:** el lote ya no se crea con un total digitado. El contador elige las ventas con tarjeta (de facturas) que el procesador incluyó (`datafast_lote_pagos`, una venta pertenece a un solo lote), ingresa el **bruto del reporte** y el sistema bloquea si no coincide con la suma. Los cobros con tarjeta hechos desde Cuentas por Cobrar no entran todavía a esta lista (son de Ventas, módulo H).
- **Liquidación** (`DatafastController::liquidar`, submódulo `bancos_liquidar_tarjetas`): el reporte manda; los porcentajes del procesador (comisión por tipo, retenciones, **IVA de la comisión configurable** `tarjeta_procesadores.iva_comision_pct`, antes escrito a mano en la pantalla) solo pre-llenan. Diferencia → 6.01.14 con observación y marca de revisión. Si el asiento no cuadra, el lote no se guarda.
- **Tarjeta diferida:** campo «Intereses del diferido» (solo en lotes diferidos) que va a gasto financiero en una línea aparte de la comisión (PC-TAR-04, DEC-024; hoy ambos usan la cuenta 6.01.10).
- **Anulación de una venta con tarjeta** (`FacturaController::anular`): si su lote está pendiente, la venta sale del lote y baja su total (el lote vacío se elimina); si el lote ya se liquidó, se bloquea y pide nota de crédito.
- **Pendiente:** contracargo / devolución del procesador (DEC-025, no se implementó la contabilización) y comprobante de retención del emisor (DEC-023).

### Inventario (F, 2026-11-04)

- **Consolidado — error SON.000232:** las unidades de importaciones no liquidadas que bodega aún no recibió se muestran en la bodega de importaciones de su empresa ("en camino"); lo ya recibido sale del saldo y no se cuenta dos veces (`ConsolidadoController::adjuntarStock`, también en el Excel).
- **Números de serie** (`NumeroSerieController`, Inventario → Números de serie): se registran por unidad (uno por línea, único por producto), con resumen de stock frente a series registradas y listado de inspección (en bodega / vendido / reservado / garantía). Al ingresar una compra con productos que exigen serie se avisa cuántas unidades registrar. **Pendiente en Ventas (H):** elegir la unidad (SN) al facturar para enlazar `factura_venta_id` automáticamente; hoy el estado «vendido» se corrige a mano en la pantalla.
- **Regalos:** máximo 2 unidades de regalo por factura (validación en `FacturaController`); más de 2 requiere aprobación de Gerencia (DEC-033 pendiente) y por ahora se bloquea con un mensaje. El asiento de entrega ya usa 6.01.15 / 1.01.04.08.xx según la subcategoría.
- **Pendiente de decisión:** margen de ganancia superior al 100 % (DEC-034): el cálculo actual es margen sobre precio (`costo / (1 − m)`), que no admite 100 %; cambiarlo a recargo sobre costo cambiaría los precios existentes y no se hizo sin esa decisión. Siguen sin subcategoría los productos de la reclasificación (incluida la categoría «ACCESORIOS (por reclasificar)», 261 productos).

### Compras e importaciones (G, 2026-11-04)

- **Factura sin productos (gasto, servicio, internacional EXT sin mercadería):** al registrarla se activa sola y llega a Cuentas por Pagar con su asiento (`CompraController::store`). Antes quedaba «pendiente» para siempre porque no tiene recepción de bodega y ninguna pantalla la activaba. Para las ya pendientes, el botón verde de la lista (`confirmarRecepcion`) ahora llama a `compras.facturas.activar` cuando no hay recepción.
- **Decisión del usuario (2026-11-04) — la Cuenta por Pagar nace al REGISTRAR la factura, también con productos** (`CompraController::crearCuentaPagar`, idempotente): así se puede pagar la mercadería antes de que llegue. El **stock y el asiento siguen entrando cuando bodega confirma** la recepción (flujo del 20/09/2026; `aplicarEfectosOperativos` / `RecepcionController::confirmar` no duplican la CxP). Anular, editar o eliminar una factura pendiente retira o regenera su CxP; si ya tiene pagos, se bloquea hasta anularlos. Además se corrigió la edición de facturas pendientes, que fallaba por el orden de borrado entre recepción y líneas.
- **Formulario de compra:** buscador de proveedor por nombre/RUC y descripción de línea expandible (se alarga al enfocarla). El IVA 12 % al buscar un producto viene del dato heredado del producto (`productos.porcentaje_iva`); se corrige en producción con `php artisan altamira:actualizar-iva-productos`.
- Ya cumplían el documento y se re-probaron: validación del FOB contra la suma de productos (IMP-01), tabla de costos extra con el ejemplo oficial (base 8.172,17 e IVA crédito 777,83), gastos con su proveedor, botón verde de abono siempre visible, Anticipos unido a CxP y número de factura de origen en Devoluciones.

### Ventas y cobranza (H, 2026-11-04)

- **Cheques recibidos de clientes (PC-VEN-13/14, DEC-001):** un cheque en una factura, en un cobro de CxC o en el abono de una prefactura **queda en cartera** (tabla `cheques_recibidos`, cuenta `cta_cheques_cartera` 1.01.03.05) y **no es movimiento bancario** hasta que se deposita desde Ventas → Cheques recibidos (`ChequeRecibidoController`: Debe banco / Haber cartera, crea el movimiento y suma el saldo). Un post fechado no se puede depositar antes de su fecha. Anular/eliminar la factura anula sus cheques en cartera; con un cheque ya depositado se bloquea. **Esto reemplaza lo anotado en C.6**: el cheque ya no entra a ningún banco al facturar; «Cheques — banco donde se depositan» en Configuración de cobros solo pre-selecciona el banco en la pantalla de depósito. El reporte «cheques post fechados recibidos» (C.7) ahora sale de la cartera.
- **Retenciones recibidas en el cobro (PC-VEN-12):** el cobro de CxC acepta retención de IVA e IR con su N° de comprobante; la retención también cancela deuda (cobrado + retenciones ≤ saldo) y el asiento acredita Clientes por el total, debitando 1.01.05.03 / 1.01.05.02.
- **Factura de evento (PC-VEN-16, DEC-050):** casilla en la factura; el ingreso de todas sus líneas va a `cta_otros_ingresos` (4.03.02) en lugar de la cuenta del producto (columna `facturas.es_evento`).
- **Guía de remisión:** pantalla con clave de acceso, autorización, transportista y placa, fechas, factura sustento y detalle, más impresión en PDF (`ventas.guias-remision.pdf`). El código QR del SRI queda pendiente de DEC-039.
- **Rótulo de caja:** botón «ROT» en la lista de facturas; se arma con los datos de la factura, se puede editar (destinatario, dirección, bultos…) y se imprime un rótulo por bulto.
- **Prefactura:** no genera movimiento; solo el abono registrado (transferencia → banco elegido; cheque → cartera, ahora con N° de cheque obligatorio).
- No implementado a propósito: devolución en ventas (PC-VEN-05, DEC-029) y aplicación del anticipo a la factura (DEC-028).

### Contabilidad (I, 2026-11-04)

- **Cierre mensual con controles** (`CierreMensualService`, `EjercicioContableController::cerrar/controles`): la pantalla de cierre muestra la lista antes de confirmar. **Bloquean:** asientos descuadrados, cajas abiertas y bancos sin conciliar al fin de mes. **Se pueden cerrar solo si el contador los justifica** (queda en `log_cambios_criticos`): tarjetas sin liquidar, cheques pendientes, CxC y CxP frente al mayor, inventario frente al kárdex, importaciones sin liquidar, depreciación y nómina del mes. **Se confirman a mano** porque el sistema no los puede comprobar: IVA (PC-TRI-01), cuentas sin clasificar (sin exigir saldo cero, DEC-031) y anticipos. Cerrar antes del último día del mes exige confirmación expresa; abrir un período con el anterior abierto da aviso.
- **Informes nuevos:** retenciones emitidas y recibidas por período, análisis vertical y análisis horizontal (frente al mismo período del año anterior), por empresa activa (RUC) y centro de costo, en PDF y Excel (`InformesContablesController`). Conciliación y transacciones bancarias viven en Bancos → Reportes. **Pendiente:** talón resumen del anexo (DEC-008).
- **Procesos de origen:** el listado de asientos tiene accesos directos a CxC, CxP, cierre de caja, movimientos/depósitos, conciliación e importaciones; no crean asientos propios.
- **Ya estaba hecho y se respeta:** validaciones del asiento (AS-01 a AS-04: cuadre, debe o haber, respaldo, centro de costo en 4/5/6, naturaleza invertida con confirmación), períodos cerrados, diarios, anulación con reverso (el borrado está bloqueado) y el cierre fiscal anual con `CIERRE_ANUAL`.
- **Pendiente de decisión:** saldos iniciales de bancos y caja como asiento de apertura (contrapartida, DEC-011), renta del cierre anual (DEC-004), retiros del propietario (DEC-010) y catálogo de retenciones SRI (DEC-045, el existente es provisional). **No implementado:** asiento de apertura del año siguiente en el cierre anual (con saldos acumulados duplicaría cifras; hay que definir cómo se quiere) y las comprobaciones previas del cierre anual (inventario físico al 31/12, depreciación, provisiones).

### RRHH, asistencia y nómina (J, 2026-11-04)

- **Nómina procesada: se anula con reverso** (`NominaController::anular`, permiso `rrhh,anular`, columnas `nominas.snapshot_prestamos/motivo_anulacion/anulada_por/fecha_anulacion`): genera el reverso del asiento de proceso, restituye préstamos y anticipos a como estaban (el estado previo se guarda al procesar) y deja motivo, usuario y fecha. Una nómina ya **pagada** no se anula desde aquí (el dinero ya salió); una anulada no se vuelve a procesar ni pagar. Estados: Borrador, Procesada, Pagada (+ Anulada).
- **Rol de pagos (PDF, una página A4 por colaborador):** ahora incluye logo, empleador, RUC, nombre comercial y dirección de la empresa, ingreso y departamento del colaborador, **base de cálculo** de cada ingreso y egreso, **información para la empresa** (aporte patronal 11,15 % y provisiones, `NominaCalculoService::informacionEmpresa`, mismas fórmulas que el asiento) y el neto **en números y en letras** (`App\Support\NumeroALetras`). Con sueldo 482,00 sale IESS personal 45,55, fondos de reserva 40,17 y aporte patronal **53,74** (11,15 % de 482 = 53,743; el documento dice 53,73, que parece error de redondeo).
- Ya estaba hecho y se re-probó: ficha del colaborador con campos obligatorios (IESS, contrato, transferencia con banco y cuenta), servicios profesionales fuera de nómina, departamentos nuevos con descripción obligatoria, generación de nómina mensual sin mezclar RUC, validaciones (sin RUC, transferencia sin banco, neto negativo), asiento cuadrado de proceso y asiento de pago con banco o caja obligatorio, horas extras con tope de 4 h/día y 12 h/semana y vínculo usuario-colaborador.
- **Datos que faltan cargar (no se inventan):** María del Carmen Rondal, Cesar Daniel Rondal y Steeven Arteaga (cédulas, sueldos, etc.); Fabián, Jhonny y Ángel como proveedores por servicio. **Pendientes del contador:** DEC-009, 016, 017 (SBU 460 vs 482 en el décimo cuarto), 018 (divisor 240) y 019.
- **Pruebas de asistencia propuestas:** entrada y salida del mismo día; segunda entrada rechazada; almuerzo sin entrada rechazado; atraso según horario; horas extras desde la marcación (50 % laborable, 100 % fin de semana o feriado); usuario sin colaborador vinculado ve el aviso; resumen del día solo con el permiso `asistencia_resumen_dia`.

### Taller (K, 2026-11-04)

- **Revisado literal por literal (TAL-01 a TAL-08, todos pasan):** repuestos desde «Repuestos e Insumos de Taller» con stock y bloqueo por cantidad, una sola lista de estados (DEC-051 confirmada), turno por orden de llegada, documento de ingreso en una página A4 con hasta 4 fotos y condiciones configurables, campos obligatorios del ingreso, factura automática con servicio (PC-TAL-01) y repuestos (PC-TAL-02) a nombre del RUC de UIO y centro de costo FIX, y garantía sin factura al cliente con reporte de costo por producto.
- **La factura de taller sigue las reglas de cobro de Ventas (H):** transferencia exige banco **y referencia**; el **cheque queda en cartera** (`cheques_recibidos`, N° y fecha del cheque, sin banco) y se deposita desde Ventas → Cheques recibidos. Antes pedía banco para el cheque y su asiento ya no coincidía con Bancos.
- El IVA de la línea de repuestos sale del producto: mientras no se ejecute `altamira:actualizar-iva-productos` en producción, esos repuestos mostrarán 12 %.
- **Pendiente de decisión (no se inventó la regla):** DEC-047 (cuenta y recuperación de garantías), DEC-049 (quién aprueba un diagnóstico), DEC-052 (valor del servicio), DEC-053 (momento de generar la factura), DEC-032 (componentes comprados afuera: unidades o tipos, y cuenta) y DEC-048 (técnico externo como compra). El texto de las condiciones del servicio es configurable (DEC-054).
- **Datos de prueba de Taller:** se borran solo con confirmación del usuario y en producción, con `altamira:limpiar-datos-prueba` (ver `docs/DESPLIEGUE_PRODUCCION.md`); la base local ya no tiene órdenes.

### Habilitación del inventario — plan V5.1 y carga limpia (2026-11-16)

Decisiones del contador y de Edwin (05 al 12/11/2026), documentos en `docs/Oct2026/Corregidos/` (**no van a git**: traen costos y existencias).

- **Plan de cuentas V5.1** (migración `2026_11_16_100000_inventario_plan_v51_subcategorias_nuevas`, idempotente): 7 cuentas nuevas (Micrófonos, Audífonos, sus ventas y costos, y `1.02.02.05` depreciación de herramientas de taller); la `4.03.02 Otros ingresos` desaparece y `cta_otros_ingresos` pasa a **4.04**.
- **Subcategorías nuevas PC-CAT-21 a 28:** Micrófonos y Audífonos con cuentas propias; Láseres, Máquinas de efectos e «Inventario sin clasificar» con las cuentas «sin clasificar» (1.01.04.09 / 4.01.09 / 5.01.08, excepción aprobada el 05/11 y 12/11); Amplificadores usa las de Parlantes Activos; **Servicio técnico (4.01.08) y Eventos (4.04) son servicios** sin cuenta de inventario ni de costo (`subcategoria_cuentas.cuenta_inventario_id/cuenta_costo_id` ahora admiten NULL).
- **Servicios:** `AsientoService::cuentasProductos()` toma la cuenta de venta del servicio **de su subcategoría** (`CuentasProductoService::paraProducto`), no de la casilla «factura de evento»; solo cae en `cta_ingresos_servicios` si no tiene subcategoría con cuenta. Los 8 servicios de Eventos acreditan siempre 4.04.
- **Carga limpia** (`php artisan inventario:carga-limpia`, clase `CargaLimpiaInventario`): reemplaza a `inventario:cargar-excel` e `inventario:aplicar-reclasificacion` para el arranque. Simula por defecto; con `--aplicar` hace, en una transacción y con respaldo JSON, las bajas (13 códigos fijos en la constante `BAJAS`), el renumerado (columna «Código anterior»), el alta/actualización con categoría y subcategoría, el stock en cero, el saldo inicial al 31/10/2026 en bodega Principal y **un asiento de apertura** (Debe inventario por subcategoría / Haber 3.01.01, `documento_tipo=APERTURA_INVENTARIO`). Se niega a correr si hay documentos con productos o si el asiento de apertura ya existe. Probado en local: 12.032 unidades, $175.015,00, 1.676 productos con cuentas resueltas. Procedimiento de producción: `docs/DESPLIEGUE_PRODUCCION.md` §10 y `scripts/produccion_carga_inventario.sh`.
- **Pendiente:** asiento de activos fijos ($6.040,96; falta `Activos_Fijos_ERP_Altamira_UIO.xlsx` con el detalle por cuenta), DEC-011 (contrapartida del conteo inicial: se usó 3.01.01 Capital Neto, como indican las instrucciones del cliente) y las DEC abiertas ya listadas.


### Revisión del módulo de Inventario (2026-11-16)

- **Traslados:** `confirmar()` recorre siempre el detalle del propio traslado (antes iteraba lo que mandaba el navegador: un envío vacío aceptaba el traslado sin mover stock). Lo recibido es entero y ≤ lo enviado; la diferencia es un **faltante** con asiento `Debe 5.01.09 / Haber inventario de la subcategoría` (`documento_tipo=TRASLADO`), validado antes de confirmar. Traslado bloqueado con `lockForUpdate` (no se confirma dos veces). Las observaciones de recepción y rechazo se agregan a la original.
- **Ajustes:** `AsientoService::ajusteInventario($empresaId, 'faltante'|'sobrante', $lineas, …)` contabiliza por producto (cuenta de su subcategoría) contra 5.01.09. El ajuste **positivo** con costo ahora también genera asiento (sobrante: Debe inventario / Haber 5.01.09); antes el stock subía sin contabilidad. Los dos sentidos exigen período abierto y subcategoría con cuentas (modo estricto). El negativo no puede consumir stock reservado. `redirect_to` solo acepta rutas internas.
- **Recepciones:** solo se confirma **completa**; con unidades faltantes devuelve error y la recepción sigue pendiente (antes quedaba «parcial», sin stock y sin poder seguir escaneando). Ingreso por línea de compra (mismo producto en dos líneas) y sin `try/catch` silencioso. El escaneo asigna la unidad a la primera línea incompleta.
- **Costo de compra en el kárdex:** `CompraDetalle::costoUnitarioNeto()` = subtotal (neto de descuento) / cantidad, el mismo valor que contabiliza el asiento (antes `precio_unitario` bruto).
- **Consolidado:** «en camino» solo cuenta facturas de importación **pendientes**; una factura activa ya está en el saldo.
- **Otros:** `InventarioService` bloquea la fila en ingresos y reservas; `liberarReserva` no baja de 0. Producto con movimientos no se elimina (se desactiva) ni pasa a servicio con stock. Bodega con stock no se desactiva; con movimientos no se elimina. Series registradas ≤ stock de la bodega. Activo totalmente depreciado sigue `activo` (ya no pasa a «dado de baja»). Ruta `inventario.activos.reporte.lista` agregada (el botón PDF fallaba). Las pantallas del módulo tratan cualquier respuesta no-OK como error (antes un 500 mostraba «eliminado correctamente»).
- **Pendiente (no se inventó la regla):** la depreciación de activos fijos no genera asiento (falta definir el mapa activo → depreciación acumulada 1.02.02.xx / gasto 6.01.08; además aún no hay activos cargados). Los traslados no mueven los números de serie de bodega (habría que elegir el SN al trasladar).
