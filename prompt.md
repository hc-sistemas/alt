# PROMPT FASE 1 — SISTEMA ERP ALTAMIRA
# Para usar en: Claude Code (claude.ai/code)
# Pegar completo al inicio de la sesión

---

## CONTEXTO DEL PROYECTO

Desarrollar el Sistema de Gestión Empresarial Altamira (ERP completo), reemplazando
tres aplicaciones PHP legacy que operan sobre la misma base de datos PostgreSQL:

- **Altamira** (PHP puro, sin framework) — ventas, contabilidad, RRHH, inventario
- **Altamira Import** (copia exacta de Altamira) — misma BD, diferente emisor SRI
- **Soporte/Taller** (Laravel 5.4) — módulo de servicio técnico

Las tres apuntan a la BD `altamira` en PostgreSQL. El nuevo sistema las unifica
en una sola aplicación con selector de empresa al iniciar sesión.

**Empresa:** Altamira Light & Sound — equipos de audio, iluminación y visión profesional.
**País:** Ecuador — legislación laboral ecuatoriana, facturación electrónica SRI.
**Sucursales/Empresas:**
- Altamira Matriz (RUC: 1711293454001) — ventas locales
- Altamira Import (RUC: 1755265848001) — importaciones
- Altamira Fix — centro de costo interno, taller técnico de Altamira Matriz

---

## STACK TECNOLÓGICO

- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** React 19 + Inertia.js v2 + TypeScript + Vite 6
- **Estilos:** Tailwind CSS v4 + shadcn/ui
- **Base de datos:** PostgreSQL 12
- **Auth:** Laravel Sanctum + Spatie Laravel Permission (v6)
- **Auditoría:** Observers de Laravel (NO Spatie Activitylog — tenemos tablas propias)
- **Colas:** Laravel Queues + Redis (para SRI, emails, notificaciones)
- **PDFs:** barryvdh/laravel-dompdf
- **Tablas:** TanStack Table v8
- **Formularios:** React Hook Form + Zod
- **Gráficos:** Recharts
- **Iconos:** Lucide React
- **Estado global:** Zustand
- **Notificaciones:** Laravel Notifications (in-app + email)

---

## BASE DE DATOS

El esquema PostgreSQL completo está en `altamira_schema.sql` (adjunto/en carpeta).
Contiene 76+ tablas organizadas en módulos. Úsalo como referencia exacta para
todas las migraciones. Respetar nombres de columnas del esquema sin excepción.

### Tablas clave para la Fase 1:
- `empresas` — datos de empresa y emisor SRI
- `centros_costo` — Altamira Matriz, Altamira Import, Altamira Fix
- `perfiles` — roles: super_admin, admin, contador, vendedor, bodeguero, tecnico
- `usuarios` — con campo `codigo_aprobacion` (PIN hasheado para aprobaciones especiales)
- `modulos` + `permisos` — control de acceso por módulo y perfil
- `tipos_aprobacion` — catálogo de 17 acciones que requieren aprobación especial
- `aprobaciones_especiales` — registro inmutable de aprobaciones dadas
- `limites_descuento` — % máximo de descuento por perfil sin necesitar aprobación
- `configuraciones` — parámetros del sistema
- `secuenciales` — numeración de documentos SRI
- `presupuestos_metas` — metas mensuales por centro de costo para el dashboard
- `log_sesiones` — auditoría nivel 1: accesos (retener 12 meses)
- `log_documentos` — auditoría nivel 2: eventos de negocio (retener permanente)
- `log_cambios_criticos` — auditoría nivel 3: cambios en campos sensibles (retener 36 meses)
- `notificaciones` — alertas in-app y email

---

## IDENTIDAD VISUAL Y DISEÑO

### Logo
- Logo principal: **Altamira Light & Sound** (fondo negro, texto y círculo dorado/amarillo)
- El logo cambia dinámicamente según la empresa activa:
  - Altamira Matriz → logo "Light & Sound"
  - Altamira Import → logo "Import"
- Slogan Altamira: "Ahora las luces se ven Diferente"
- Slogan Import: "Solo un DJ sabe lo que otro DJ necesita"

### Paleta de colores
Inspirada en los colores corporativos (negro y dorado/amarillo) pero adaptada
a una interfaz empresarial profesional. NO hacer el sistema todo negro y amarillo
brillante — usar esos colores como acentos sobre bases neutras elegantes.

```
-- Light Mode --
Fondo principal:     #F8FAFC  (gris muy claro, profesional)
Sidebar:             #0F172A  (azul oscuro casi negro — elegante)
Sidebar texto:       #94A3B8
Sidebar activo:      #F59E0B  (dorado Altamira — color de marca)
Sidebar activo bg:   rgba(245,158,11,0.15)
Primario (botones):  #F59E0B  (dorado)
Primario hover:      #D97706
Primario texto:      #000000
Secundario:          #1E293B
Borde:               #E2E8F0
Texto principal:     #0F172A
Texto secundario:    #64748B
Éxito:               #10B981
Peligro:             #EF4444
Warning:             #F59E0B
Info:                #3B82F6

-- Dark Mode --
Fondo principal:     #0F172A
Fondo card:          #1E293B
Sidebar:             #020617  (negro puro)
Sidebar activo:      #F59E0B  (dorado — igual en ambos modos)
Primario:            #F59E0B
Texto principal:     #F1F5F9
Texto secundario:    #94A3B8
Borde:               #334155
```

### Tipografía
- Font principal: Inter (Google Fonts)
- Tamaños: 12px datos tabla, 14px texto general, 16px títulos sección, 24px títulos página

### Estilo general
- Inspiración: sistemas enterprise como SAP Fiori, Oracle NetSuite, Odoo Enterprise
- Cards con border-radius: 8px, sombras suaves
- Sidebar colapsable (120px expandido / 56px colapsado solo íconos)
- Tablas con densidad compacta — mostrar más datos, menos espacio desperdiciado
- Transiciones suaves: 200ms ease para hover, tema, sidebar

---

## FASE 1 — LO QUE SE DEBE CONSTRUIR

### 1. Setup del proyecto

```bash
# Crear proyecto Laravel 12 con Inertia + React + TypeScript
# Laravel 12 requiere PHP 8.2+ y ya incluye Vite 6 por defecto
composer create-project laravel/laravel:^12.0 altamira-erp
cd altamira-erp

# Backend packages compatibles con Laravel 12
composer require inertiajs/inertia-laravel:^2.0
composer require spatie/laravel-permission:^6.0
composer require barryvdh/laravel-dompdf:^3.0
composer require predis/predis:^2.0
composer require tightenco/ziggy:^2.0

# Frontend — React 19 + Inertia v2 + Tailwind v4
npm install react@19 react-dom@19 @inertiajs/react@^2.0
npm install -D typescript @types/react@19 @types/react-dom@19
npm install -D @vitejs/plugin-react
npm install -D tailwindcss@^4.0 @tailwindcss/vite
npm install @tanstack/react-table react-hook-form @hookform/resolvers zod
npm install recharts lucide-react zustand
npm install class-variance-authority clsx tailwind-merge
# shadcn/ui compatible con Tailwind v4
npx shadcn@latest init
```

### Cambios clave de Laravel 12 vs 11 — aplicar en todo el proyecto:

**Middleware:** Laravel 12 mantiene `bootstrap/app.php` (igual que L11).
No existe `app/Http/Kernel.php`. Registrar middleware así:
```php
// bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\HandleInertiaRequests::class,
        \App\Http\Middleware\VerificarUsuarioActivo::class,
        \Tightenco\Ziggy\Ziggy::class,
    ]);
})
```

**Rutas:** Laravel 12 carga rutas desde `bootstrap/app.php`:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

**Tailwind v4:** NO usa `tailwind.config.js`. La configuración va en CSS:
```css
/* resources/css/app.css */
@import "tailwindcss";
@plugin "@tailwindcss/forms";

/* Tema Altamira con CSS variables nativas de Tailwind v4 */
@theme {
  --color-primary: #F59E0B;
  --color-primary-hover: #D97706;
  --color-sidebar: #0F172A;
  --color-sidebar-dark: #020617;
  --color-bg-main: #F8FAFC;
  --color-bg-card: #FFFFFF;
  --font-family-sans: 'Inter', sans-serif;
}
```

**Vite 6 + Tailwind v4:** en `vite.config.ts` usar el plugin oficial:
```typescript
import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
  plugins: [
    laravel({ input: ['resources/css/app.css', 'resources/js/app.tsx'], refresh: true }),
    react(),
    tailwindcss(),
  ],
  resolve: { alias: { '@': '/resources/js' } },
})
```

**Inertia v2 con React 19:** el entry point usa la nueva API:
```typescript
// resources/js/app.tsx
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true })
    return pages[`./Pages/${name}.tsx`]
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})
```

**shadcn/ui con Tailwind v4:** usar `components.json` con style `new-york`
y asegurarse que todos los componentes importan desde `@/components/ui`.
En Tailwind v4 las utilidades de shadcn se adaptan automáticamente.

**Spatie Permission v6 con Laravel 12:** publicar config y migraciones:
```bash
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
# Agregar HasRoles trait al modelo Usuario
```

**Model casting Laravel 12:** usar el nuevo syntax de casting:
```php
// En modelos — L12 prefiere casts como método:
protected function casts(): array {
    return [
        'created_at' => 'datetime',
        'estado' => 'boolean',
    ];
}
```

Configurar:
- `.env` con conexión PostgreSQL (`altamira_nuevo` — base de datos de desarrollo)
- `QUEUE_CONNECTION=redis` en `.env`
- Alias `@` para `resources/js` en `vite.config.ts` (ver arriba)
- Ziggy para rutas tipadas en React

### 2. Migraciones (en este orden exacto por FKs)

Crear migraciones Laravel para:
1. `empresas`
2. `centros_costo`
3. `perfiles` (además de las tablas de Spatie Permission)
4. `usuarios` (extender el User model de Laravel)
5. `modulos`
6. `permisos`
7. `tipos_aprobacion`
8. `limites_descuento`
9. `aprobaciones_especiales`
10. `log_sesiones`
11. `log_documentos`
12. `log_cambios_criticos`
13. `configuraciones`
14. `secuenciales`
15. `presupuestos_metas`
16. `notificaciones`

### 3. Seeders iniciales

Crear seeders para datos base:
- **EmpresaSeeder:** Altamira Matriz + Altamira Import
- **CentroCostoSeeder:** Altamira Matriz, Altamira Import, Altamira Fix
- **PerfilSeeder:** super_admin, admin, contador, vendedor, bodeguero, tecnico
- **ModuloSeeder:** todos los módulos del sistema con íconos Lucide
- **PermisoSeeder:** matriz completa de permisos por perfil (según spec):
  ```
  super_admin → Total en todo
  admin       → Total en todo excepto cierre contable (lectura)
  contador    → Total contabilidad/compras/bancos, Lectura ventas/inventario
  vendedor    → Operativo ventas, Lectura inventario/CxC, solo sus reportes
  bodeguero   → Operativo inventario, Lectura ventas
  tecnico     → Operativo taller únicamente
  ```
- **TiposAprobacionSeeder:** los 17 tipos definidos en el esquema
- **LimiteDescuentoSeeder:**
  ```
  super_admin: 100%, puede_aprobar=true, aprobacion_max=100%
  admin:       30%,  puede_aprobar=true, aprobacion_max=50%
  contador:    0%,   puede_aprobar=false
  vendedor:    5%,   puede_aprobar=false
  bodeguero:   0%,   puede_aprobar=false
  tecnico:     0%,   puede_aprobar=false
  ```
- **UsuarioSeeder:** crear super_admin de prueba

### 4. Modelos y relaciones

Crear modelos con relaciones completas:
- `Empresa` hasMany CentroCosto, hasMany Usuario, hasMany Configuracion
- `CentroCosto` belongsTo Empresa
- `Usuario` (extender Authenticatable) belongsTo Empresa, belongsTo Perfil,
  belongsTo CentroCosto — usar Spatie HasRoles trait
- `Perfil` hasMany Usuario, hasMany Permiso, hasMany LimiteDescuento
- `Modulo` hasMany Permiso
- `Permiso` belongsTo Perfil, belongsTo Modulo
- `Notificacion` belongsTo Usuario
- Observers: `UsuarioObserver` para log_cambios_criticos en campos sensibles

### 5. Autenticación segura

**LoginController:**
- Rate limiting: max 5 intentos por IP en 1 minuto (usar `RateLimiter` de Laravel)
- Verificar estado del usuario (activo/inactivo) antes de autenticar
- Al login exitoso: registrar en `log_sesiones` (tipo='login_ok', IP del servidor)
- Al login fallido: registrar en `log_sesiones` (tipo='login_fail')
- Al logout: registrar en `log_sesiones` (tipo='logout'), invalidar sesión completa
- Password hasheado con bcrypt (nunca MD5 ni SHA1)
- CSRF en todos los formularios vía Inertia

**Middleware `VerificarUsuarioActivo`:**
- En cada request verificar que `usuario.estado = true`
- Si está inactivo: logout inmediato + mensaje "Tu cuenta fue desactivada"

**Selección de empresa post-login:**
- Si el usuario tiene acceso a más de una empresa → mostrar pantalla de selección
- La empresa seleccionada se guarda en la sesión y en Zustand (frontend)
- Puede cambiar de empresa desde el topbar sin cerrar sesión (si tiene permiso)

### 6. Layout principal (AppLayout)

Componente `resources/js/Layouts/AppLayout.tsx` con:

**Sidebar izquierdo:**
```
- Logo Altamira (dinámico según empresa activa)
- Nombre del sistema: "ERP Altamira"
- Navegación agrupada con íconos Lucide:
  🏠 Dashboard
  🧾 Ventas (Facturas, Proformas, Prefacturas, CxC, Notas Crédito)
  🛒 Compras (Compras, Proveedores, Importaciones, CxP)
  📦 Inventario (Productos, Kárdex, Bodegas, Traslados, Activos Fijos)
  📊 Contabilidad (Asientos, Plan Cuentas, Períodos)
  🏦 Bancos (Movimientos, Cajas, Datafast, Conciliación)
  👥 RRHH (Nómina, Colaboradores, Asistencia, Préstamos)
  🔧 Taller (Órdenes de Trabajo, Equipos, Diagnósticos)
  📈 Reportes
  ⚙️ Configuración (Usuarios, Permisos, Empresa, Parámetros)
- Cada sección colapsable con animación suave
- Ítem activo: fondo dorado translúcido + texto dorado + barra lateral dorada
- Botón colapsar sidebar (solo íconos en modo compacto)
- En móvil: drawer con overlay oscuro
```

**Topbar:**
```
- Breadcrumb de navegación actual
- Selector empresa activa (dropdown con logo mini de cada empresa)
- Badge notificaciones (campana + contador rojo)
  → Dropdown con últimas 5 notificaciones no leídas
  → Link "Ver todas"
- Avatar usuario con dropdown:
  → Nombre completo + rol
  → "Mi Perfil"
  → "Cerrar Sesión"
- Toggle dark/light mode (sol/luna con animación)
```

**Implementación del tema:**
```typescript
// stores/themeStore.ts — Zustand
const useThemeStore = create((set) => ({
  theme: localStorage.getItem('altamira-theme') || 'light',
  toggleTheme: () => set((state) => {
    const newTheme = state.theme === 'light' ? 'dark' : 'light'
    localStorage.setItem('altamira-theme', newTheme)
    document.documentElement.classList.toggle('dark', newTheme === 'dark')
    return { theme: newTheme }
  })
}))
```

**CSS variables en `app.css`:**
```css
:root {
  --sidebar-bg: #0F172A;
  --sidebar-text: #94A3B8;
  --sidebar-active: #F59E0B;
  --sidebar-active-bg: rgba(245,158,11,0.12);
  --primary: #F59E0B;
  --primary-hover: #D97706;
  --bg-main: #F8FAFC;
  --bg-card: #FFFFFF;
  --text-main: #0F172A;
  --text-muted: #64748B;
  --border: #E2E8F0;
}
.dark {
  --bg-main: #0F172A;
  --bg-card: #1E293B;
  --text-main: #F1F5F9;
  --text-muted: #94A3B8;
  --border: #334155;
  --sidebar-bg: #020617;
}
```

**Responsivo:**
- < 768px: sidebar oculto, topbar compacta, botón hamburguesa
- 768-1024px: sidebar colapsado (solo íconos) por defecto
- > 1024px: sidebar expandido por defecto

### 7. AuthLayout (Login)

Componente `resources/js/Layouts/AuthLayout.tsx`:
- Pantalla dividida: izquierda decorativa (60%), derecha formulario (40%)
- Lado izquierdo: fondo degradado negro a azul oscuro, logo Altamira centrado grande,
  eslogan, texto "Sistema de Gestión Empresarial", partículas o patrón sutil
- Lado derecho: fondo blanco (light) / #1E293B (dark), formulario centrado
- Formulario:
  - Título "Iniciar Sesión"
  - Campo email con ícono
  - Campo password con toggle mostrar/ocultar
  - Botón "Ingresar" (dorado con texto negro, full width)
  - Mensaje de error inline si falla (no alert del browser)
  - Indicador de intentos restantes si hay rate limiting
- En móvil: solo el formulario, sin panel decorativo

### 8. Dashboard

Página `resources/js/Pages/Dashboard/Index.tsx`:

**Header de página:**
```
- Título: "Dashboard"
- Subtítulo: "Buenos días, {nombre}" con fecha actual
- Selector período: Hoy / Esta semana / Este mes / Este año
- Badge empresa activa con color distintivo
```

**Grid de widgets (responsive: 1 col móvil, 2 tablet, 3-4 desktop):**

Widget 1 — Ventas del día:
```
- Número grande: $XX,XXX.XX en color dorado
- Comparación vs ayer: +12% ↑ (verde) o -5% ↓ (rojo)
- Mini gráfico de línea de las últimas 8 horas
- Fuente: SUM(facturas.total) WHERE fecha=hoy AND empresa_id=activa
```

Widget 2 — Meta del mes:
```
- Gauge circular (Recharts RadialBarChart)
- Centro: porcentaje logrado en dorado
- Abajo: $vendido / $meta
- Colores: <50% rojo, 50-80% amarillo, >80% verde
- Fuente: SUM(facturas) / presupuestos_metas del mes
```

Widget 3 — Ventas mensuales (gráfico de barras):
```
- BarChart Recharts con 2 series: año actual vs año anterior
- Color año actual: dorado (#F59E0B)
- Color año anterior: gris (#94A3B8)
- Eje X: Ene-Dic, Eje Y: en miles ($)
- Tooltip con formato moneda
- Filtrado por empresa activa
```

Widget 4 — Órdenes de Trabajo activas:
```
- Tabla compacta: # OT | Equipo | Técnico | Estado | Días
- Estados con badge de color:
  'pendiente' → gris
  'en_proceso' → azul
  'listo' → verde dorado
- Max 5 filas + link "Ver todas"
- Solo si usuario tiene acceso al módulo Taller
```

Widget 5 — Cuentas por pagar próximas:
```
- Lista de las 5 CxP más urgentes ordenadas por fecha_vencimiento ASC
- Cada fila: proveedor | monto | días para vencer
- Color: >7 días normal, 1-7 días warning naranja, vencida rojo
- Solo si usuario tiene acceso al módulo CxP
```

Widget 6 — Flujo de caja (Mi efectivo):
```
- Lista de bancos y cajas con saldo actual
- Total general en la parte inferior (bold, dorado)
- Ícono banco o caja según tipo
- Solo si usuario tiene acceso al módulo Bancos
```

**Skeleton loaders:**
- Todos los widgets muestran skeleton animado mientras cargan
- Los datos se obtienen vía Inertia (server-side) o fetch separado

### 9. Módulo Configuración — Usuarios

Páginas en `resources/js/Pages/Configuracion/Usuarios/`:

**Index.tsx — Lista de usuarios:**
```
- Tabla TanStack con: avatar | nombre | email | perfil | empresa | estado | acciones
- Búsqueda en tiempo real (client-side para <200 registros)
- Filtros: por perfil, por empresa, por estado
- Columna estado: toggle switch para activar/desactivar (con confirmación modal)
- Acciones: Editar | Ver historial accesos
- Botón "Nuevo Usuario" (dorado, top right)
- Paginación server-side
```

**Form.tsx — Crear/Editar usuario:**
```
Sección 1 — Datos personales:
  - Nombre completo | Email | Teléfono
Sección 2 — Acceso al sistema:
  - Username (único) | Password (con confirmación) | Perfil/Rol
  - Empresa(s) a las que tiene acceso (multi-select)
  - Centro de costo por defecto
Sección 3 — Código de aprobación:
  - Campo PIN 4-6 dígitos (solo si perfil es admin o super_admin)
  - Tooltip explicando para qué sirve
  - Confirmación del PIN
Sección 4 — Estado:
  - Toggle activo/inactivo
```

**Show.tsx — Historial de accesos:**
```
- Timeline de últimos 30 accesos desde log_sesiones
- Columnas: fecha | hora | tipo | IP | resultado
- Tipos con ícono y color: login_ok (verde), login_fail (rojo), logout (gris)
```

### 10. Módulo Configuración — Permisos

Página `resources/js/Pages/Configuracion/Permisos/Index.tsx`:
```
- Selector de perfil (tabs: super_admin, admin, contador, vendedor, bodeguero, tecnico)
- Tabla matriz:
  Filas: módulos del sistema
  Columnas: Ver | Crear | Editar | Eliminar | Anular
  Celdas: checkbox (guardado automático con debounce 500ms)
- Sección separada: Límites de descuento
  - Input numérico: % máximo sin aprobación
  - Toggle: puede aprobar descuentos de otros
  - Input: % máximo que puede aprobar
- Botón "Restaurar defaults del perfil"
```

### 11. Módulo Configuración — Empresa

Página `resources/js/Pages/Configuracion/Empresa/Index.tsx`:
```
Sección 1 — Datos generales:
  - Logo (upload con preview)
  - Razón social | Nombre comercial | RUC
  - Dirección matriz | Dirección establecimiento
  - Email notificaciones | Teléfono

Sección 2 — Datos SRI:
  - Ambiente: [Pruebas] [Producción] — toggle prominente con warning en producción
  - Código establecimiento | Código punto de emisión
  - ¿Obligado a llevar contabilidad? | ¿Contribuyente especial?
  - Agente de retención (número resolución)
  - Upload firma electrónica (.p12) + contraseña de firma

Sección 3 — Centros de costo:
  - Lista con: nombre | código | tipo | estado
  - Crear/editar desde modal

Sección 4 — Secuenciales:
  - Tabla: tipo documento | establecimiento | punto emisión | próximo número
  - Edición inline del número
```

---

## ESTRUCTURA DE CARPETAS

```
app/
  Http/
    Controllers/
      Auth/
        LoginController.php
      Dashboard/
        DashboardController.php
      Configuracion/
        UsuarioController.php
        PermisoController.php
        EmpresaController.php
    Middleware/
      VerificarUsuarioActivo.php
      SetEmpresaActiva.php          ← inyecta empresa en cada request
    Requests/
      Auth/LoginRequest.php
      Configuracion/UsuarioRequest.php
    Resources/
      UsuarioResource.php
      EmpresaResource.php
      NotificacionResource.php
  Models/
    Empresa.php
    CentroCosto.php
    Perfil.php
    Usuario.php                     ← extiende Authenticatable
    Modulo.php
    Permiso.php
    TipoAprobacion.php
    AprobacionEspecial.php
    LimiteDescuento.php
    Configuracion.php
    Secuencial.php
    PresupuestoMeta.php
    Notificacion.php
    LogSesion.php
    LogDocumento.php
    LogCambioCritico.php
  Observers/
    UsuarioObserver.php
  Services/
    AuditoriaService.php            ← registra en los 3 niveles de log
    NotificacionService.php
    EmpresaContextService.php       ← maneja empresa activa en sesión
  Traits/
    RegistraAuditoria.php           ← trait reutilizable en controllers

resources/
  js/
    app.tsx                         ← entry point Inertia
    Components/
      ui/                           ← shadcn components
      shared/
        Sidebar.tsx
        Topbar.tsx
        NotificacionesDropdown.tsx
        EmpresaSelector.tsx
        ThemeToggle.tsx
        SkeletonCard.tsx
        DataTable.tsx               ← wrapper TanStack reutilizable
        ConfirmModal.tsx
        AprobacionModal.tsx         ← modal para código de aprobación
        PageHeader.tsx
        StatusBadge.tsx
    Pages/
      Auth/
        Login.tsx
      Dashboard/
        Index.tsx
        widgets/
          VentasDia.tsx
          MetaMes.tsx
          VentasMensuales.tsx
          OrdenesActivas.tsx
          CxpProximas.tsx
          FlujoCaja.tsx
      Configuracion/
        Usuarios/
          Index.tsx
          Form.tsx
          Show.tsx
        Permisos/
          Index.tsx
        Empresa/
          Index.tsx
    Layouts/
      AppLayout.tsx
      AuthLayout.tsx
    Stores/
      themeStore.ts
      empresaStore.ts               ← empresa activa, centros de costo
      authStore.ts                  ← usuario, permisos cacheados
      notificacionStore.ts
    Types/
      empresa.ts
      usuario.ts
      dashboard.ts
      notificacion.ts
    Hooks/
      usePermiso.ts                 ← hook: usePermiso('ventas', 'crear')
      useEmpresa.ts
      useNotificaciones.ts
```

---

## ESTÁNDARES OBLIGATORIOS

### Seguridad
- IDs nunca en URLs — usar UUIDs o rutas con model binding
- Validación en FormRequest, nunca en el Controller
- Policy de Laravel para autorización de cada recurso
- Headers de seguridad en middleware: CSP, X-Frame-Options, X-Content-Type-Options
- Firma electrónica almacenada con `encrypt()` de Laravel

### Auditoría (usar AuditoriaService)
```php
// En cada controller action importante:
$this->auditoriaService->documento(
    accion: 'crear',
    modulo: 'usuarios',
    tabla: 'usuarios',
    registro_id: $usuario->id,
    descripcion: "Usuario {$usuario->username} creado con perfil {$usuario->perfil->nombre}"
);
```

### Permisos (usar usePermiso hook en React)
```typescript
// En componentes React:
const { puede } = usePermiso('ventas')
// Renderizado condicional:
{puede('crear') && <Button>Nueva Factura</Button>}
```

### UX empresarial obligatorio
- Toast notifications para éxito/error (shadcn Toaster)
- Modal de confirmación antes de: eliminar, anular, desactivar usuario
- Loading state en cada botón de submit (spinner + deshabilitar)
- Mensajes de error descriptivos en español
- Tablas vacías con ilustración + mensaje + call-to-action
- Todos los montos en formato: $1,234.56 (punto decimal, coma miles)
- Todas las fechas en formato: DD/MM/YYYY

### TypeScript
- Interfaces tipadas para TODOS los props
- No usar `any` — usar `unknown` si es necesario
- Resources de Laravel generan tipos predecibles

---

## DATOS SEMILLA INICIALES PARA PRUEBAS

```
Super Admin:
  email: admin@altamira.com
  password: Altamira2026*
  codigo_aprobacion: 1234
  empresa: Altamira Matriz
  perfil: super_admin

Vendedor de prueba:
  email: vendedor@altamira.com
  password: Vendedor2026*
  empresa: Altamira Matriz
  perfil: vendedor
```

---

## ENTREGABLE ESPERADO DE LA FASE 1

Al finalizar debe poderse:
1. `php artisan migrate --seed` sin errores
2. `npm run dev` + `php artisan serve` sin errores
3. Acceder a `/login` — ver pantalla profesional con logo Altamira
4. Login exitoso → selección de empresa → dashboard
5. Dashboard carga con skeleton y luego widgets reales (aunque con datos en 0)
6. Sidebar funcional: colapsar/expandir, navegación, responsive
7. Toggle dark/light funciona y persiste al recargar
8. Crear, editar, activar/desactivar usuarios
9. Configurar permisos por perfil (matriz de checkboxes)
10. Cambiar de empresa desde topbar sin cerrar sesión
11. Campana de notificaciones visible (aunque vacía)
12. Pasar sin errores en Chrome móvil (375px), tablet (768px) y desktop (1440px)

**NO construir en esta fase:** facturación SRI, inventario, contabilidad,
bancos, compras, importaciones, RRHH, taller. Eso es Fase 2.

---

## NOTAS IMPORTANTES

1. **Laravel 12** usa `bootstrap/app.php` — NO existe Kernel.php. Todo middleware
   se registra en `->withMiddleware()` dentro de ese archivo.
2. **Inertia.js v2** con React 19 — usar `createRoot` (no `render` legacy).
   El SSR es opcional, no habilitarlo en esta fase.
3. **Tailwind v4** NO usa `tailwind.config.js`. La config va en `app.css`
   con directivas `@theme {}`. No hay `purge` ni `content` array.
4. **shadcn/ui** con Tailwind v4: usar `npx shadcn@latest init` (sin el guión).
   Seleccionar style `new-york`, base color `zinc`.
5. **Spatie Permission v6**: usar `perfil` como sinónimo de `role`.
   El guard debe ser `web`. Publicar migraciones antes de las propias.
6. **React 19** trae cambios en hooks — no usar `useEffect` para data fetching,
   usar Inertia props directamente. `use()` hook disponible para Promises.
7. **Vite 6**: el plugin de Tailwind v4 es `@tailwindcss/vite`, no el PostCSS.
   No instalar `postcss` ni `autoprefixer` — Tailwind v4 no los necesita.
8. **Model casts** en Laravel 12: definir como método `casts(): array {}`,
   no como propiedad `$casts = []` (deprecated).
9. El `codigo_aprobacion` se hashea con `Hash::make()` igual que el password.
10. La empresa activa se persiste en `session('empresa_activa_id')` server-side
    y en Zustand client-side para acceso inmediato en componentes.
11. TanStack Table v8: server-side para tablas grandes, client-side solo para
    tablas de configuración (<200 registros).
12. Todos los queries deben filtrar por empresa activa usando un Global Scope:
    `EmpresaScope` que agrega automáticamente `WHERE empresa_id = $empresaActiva`.
13. **PHP 8.2+ requerido** para Laravel 12. Verificar con `php -v` antes de instalar.
    Usar typed properties y enums nativos de PHP 8.2 donde aplique.
```
