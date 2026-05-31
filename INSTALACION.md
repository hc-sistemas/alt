# Guía de Instalación — ERP Altamira

Sistema de Gestión Empresarial para Altamira Light & Sound.  
Stack: Laravel 12 · React 19 · Inertia.js v2 · TypeScript · PostgreSQL

---

## Requisitos del sistema

| Herramienta | Versión mínima | Notas |
|---|---|---|
| PHP | 8.2+ | Con extensiones: pdo_pgsql, openssl, mbstring, xml, curl, tokenizer |
| Composer | 2.x | Gestor de dependencias PHP |
| Node.js | 18+ | Incluye npm |
| PostgreSQL | 12+ | Base de datos principal |
| Git | cualquier | Para clonar el repositorio |

---

## 1. Instalar PostgreSQL

### Windows

1. Descargar el instalador desde: https://www.postgresql.org/download/windows/
   - Seleccionar versión **16.x** (recomendada) o 12+
2. Ejecutar el instalador:
   - Puerto: **5432** (por defecto)
   - Usuario superadmin: **postgres**
   - **Anotar bien la contraseña** — la necesitarás después
   - Dejar marcado "Stack Builder" si deseas instalar herramientas adicionales
3. Verificar instalación abriendo CMD:
   ```cmd
   psql --version
   ```

### Crear la base de datos

Abrir **pgAdmin** (se instala con PostgreSQL) o usar la terminal:

```bash
psql -U postgres
```

```sql
CREATE DATABASE altamira;
\q
```

### Restaurar el backup de la BD (si tienes el archivo .sql o .dump)

```bash
psql -U postgres -d altamira -f backup_altamira.sql
```

O con pg_restore si el backup es binario:
```bash
pg_restore -U postgres -d altamira backup_altamira.dump
```

> **Importante:** El sistema usa la BD `altamira` que contiene datos del sistema legacy.  
> Sin el backup, las migraciones crearán solo las tablas nuevas del ERP.

---

## 2. Instalar PHP 8.2+

### Opción A — Laragon (recomendado para Windows)

Laragon incluye PHP, Apache y Composer en un solo instalador:

1. Descargar desde: https://laragon.org/download/
   - Elegir **Laragon Full**
2. Instalar y abrir Laragon
3. En el menú de Laragon: **PHP → Cambiar versión → php-8.2.x o 8.4.x**
4. Clic en **Reload**

### Opción B — PHP standalone

1. Descargar desde: https://windows.php.net/download/
   - Elegir **VS16 x64 Thread Safe**
2. Descomprimir en `C:\php`
3. Agregar `C:\php` al PATH del sistema
4. Copiar `php.ini-production` → `php.ini`
5. En `php.ini`, habilitar estas extensiones (quitar el `;` al inicio):
   ```ini
   extension=pdo_pgsql
   extension=pgsql
   extension=openssl
   extension=mbstring
   extension=xml
   extension=curl
   extension=fileinfo
   extension=zip
   ```

Verificar:
```cmd
php -v
```

---

## 3. Instalar Composer

1. Descargar desde: https://getcomposer.org/Composer-Setup.exe
2. Ejecutar el instalador (detecta PHP automáticamente)
3. Verificar:
   ```cmd
   composer --version
   ```

---

## 4. Instalar Node.js

1. Descargar desde: https://nodejs.org/ — versión **LTS**
2. Instalar con opciones por defecto
3. Verificar:
   ```cmd
   node -v
   npm -v
   ```

---

## 5. Instalar el proyecto

### Clonar el repositorio

```bash
git clone https://github.com/hc-sistemas/alt.git
cd alt
```

### Instalar dependencias PHP

```bash
composer install
```

### Instalar dependencias frontend

```bash
npm install
```

---

## 6. Configurar el entorno

```bash
cp .env.example .env
php artisan key:generate
```

Abrir el archivo `.env` con cualquier editor y configurar:

```env
APP_NAME="ERP Altamira"
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=altamira
DB_USERNAME=postgres
DB_PASSWORD=tu_contraseña_postgres

SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

> Reemplaza `tu_contraseña_postgres` con la contraseña que pusiste al instalar PostgreSQL.

---

## 7. Ejecutar migraciones

```bash
php artisan migrate
```

Si la BD `altamira` ya tiene datos del sistema legacy, las migraciones son seguras — solo agregan tablas y columnas nuevas sin tocar las existentes.

### Cargar datos iniciales (solo si es BD vacía)

```bash
php artisan db:seed
```

Esto crea:
- Las 2 empresas (Altamira Matriz e Altamira Import)
- Los 6 perfiles de usuario
- Los 10 módulos del sistema
- La matriz de permisos
- Los 17 tipos de aprobación
- 2 usuarios de prueba

---

## 8. Compilar assets

```bash
npm run build
```

---

## 9. Ejecutar el sistema

```bash
php artisan serve
```

Abrir en el navegador: **http://127.0.0.1:8000/login**

---

## Credenciales de prueba

| Campo | Super Admin | Vendedor |
|---|---|---|
| Email | admin@altamira.com | vendedor@altamira.com |
| Password | Altamira2026* | Vendedor2026* |
| Perfil | super_admin | vendedor |
| Empresas | Ambas | Solo Matriz |

---

## Solución de problemas frecuentes

### Error: "Composer detected issues — PHP version >= 8.2.0 required"
El comando `php` apunta a una versión antigua. Usa la ruta completa:
```bash
# Laragon con PHP 8.2
C:\laragon\bin\php\php-8.2.0-Win32-vs16-x64\php.exe artisan serve

# Laragon con PHP 8.4
C:\laragon\bin\php\php-8.4.16-Win32-vs17-x64\php.exe artisan serve
```

### Error: "fe_sendauth: no password supplied"
Falta la contraseña en el `.env`. Verificar `DB_PASSWORD`.

### Error: "could not connect to server"
- PostgreSQL no está corriendo. Abrir pgAdmin o ejecutar:
  ```cmd
  net start postgresql-x64-16
  ```

### Error: "la relación X no existe"
La BD no tiene el schema. Verificar que se restauró el backup o ejecutar las migraciones.

### Vite no carga (página en blanco o sin estilos)
Ejecutar:
```bash
npm run build
```
O para desarrollo con hot reload:
```bash
npm run dev
```

---

## Desarrollo activo (con hot reload)

Abrir **dos terminales**:

**Terminal 1 — Backend:**
```bash
php artisan serve
```

**Terminal 2 — Frontend (hot reload):**
```bash
npm run dev
```

Los cambios en archivos `.tsx`, `.ts` y `.css` se reflejan automáticamente en el navegador.

---

## Estructura del proyecto

```
app/
  Http/Controllers/     ← Auth, Dashboard, Configuración
  Models/               ← 14 modelos Eloquent
  Services/             ← AuditoriaService
  Middleware/           ← Inertia, VerificarUsuarioActivo
database/
  migrations/           ← 18 migraciones
  seeders/              ← Datos iniciales
resources/
  js/
    Pages/              ← React: Login, Dashboard, Configuración
    Layouts/            ← AppLayout (sidebar+topbar), AuthLayout
    Components/         ← UI components, shared components
    Stores/             ← Zustand: tema, empresa activa
routes/
  web.php               ← Todas las rutas del sistema
```

---

## Módulos implementados (Fase 1)

- ✅ Autenticación con rate limiting
- ✅ Selección de empresa multi-empresa
- ✅ Dashboard con widgets y gráficos
- ✅ Configuración de usuarios (CRUD)
- ✅ Configuración de permisos por perfil
- ✅ Configuración de empresa y datos SRI
- ✅ Dark/light mode persistente
- ✅ Sidebar colapsable responsive

## Pendiente (Fase 2)

- ⏳ Facturación electrónica SRI
- ⏳ Inventario y kárdex
- ⏳ Contabilidad y plan de cuentas
- ⏳ Bancos y cajas
- ⏳ Compras e importaciones
- ⏳ RRHH y nómina
- ⏳ Taller (órdenes de trabajo)
