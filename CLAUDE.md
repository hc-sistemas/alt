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

## Colas (queue:work) — requerido para exportación en segundo plano de Asientos

`QUEUE_CONNECTION=database` (la tabla `jobs` ya está migrada). A diferencia de
los 4 Jobs de alertas (`AlertaVencimientoCxP`, `AlertaVouchersNoLiquidados`,
`AlertaAtrasosRecurrentes`, `RecordatorioCierreNomina`), que solo se disparan
vía `Schedule::job()` en `routes/console.php` y por eso no necesitan un worker
persistente para funcionar en desarrollo (el scheduler los ejecuta inline en
su propio tick), **`App\Jobs\ExportarAsientosJob`** (exportación de Asientos
Contables cuando el reporte es demasiado grande para generarse al instante —
ver `AsientoContableController::exportarSegundoPlano()`) se dispara desde una
acción real del usuario y **se queda esperando en la tabla `jobs` para
siempre si no hay un worker corriendo**.

```bash
# Requerido para que la exportación en segundo plano de Asientos funcione:
php artisan queue:work

# En producción, correr esto bajo Supervisor (o systemd) para que se
# reinicie solo si el proceso muere. No hay Procfile ni supervisor.conf en
# este repo todavía — agregarlo es responsabilidad del deploy.
```

El Job de limpieza `LimpiarExportacionesAsientosJob` (borra archivos de
`storage/app/private/exportaciones-asientos/` con más de 48h) SÍ está
programado vía `Schedule::job()->dailyAt('03:00')`, así que ese no necesita
un worker aparte — pero el propio `schedule:run` sí necesita correr (cron o
`php artisan schedule:work` en desarrollo).

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
