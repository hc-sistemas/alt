# Guía de trabajo en equipo — ERP Altamira

Repositorio: https://github.com/hc-sistemas/alt

---

## Primera vez — configurar el equipo

### 1. Clonar el repositorio

```bash
git clone https://github.com/hc-sistemas/alt.git
cd alt
```

### 2. Instalar dependencias

```bash
composer install
npm install
```

### 3. Configurar entorno

```bash
cp .env.example .env
php artisan key:generate
```

Editar `.env` con tus datos locales:
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=altamira
DB_USERNAME=postgres
DB_PASSWORD=tu_password_local
SESSION_DRIVER=file
```

### 4. Ejecutar migraciones

```bash
php artisan migrate
php artisan db:seed
```

### 5. Compilar y levantar

```bash
npm run build
php artisan serve
```

> Ver `INSTALACION.md` para instrucciones detalladas de PostgreSQL y PHP.

---

## Flujo de trabajo diario

### Al comenzar a trabajar cada día

```bash
# 1. Asegúrate de estar en main
git checkout main

# 2. Traer los últimos cambios del equipo
git pull origin main

# 3. Crear tu rama para el módulo que vas a trabajar
git checkout -b modulo/nombre-del-modulo
```

**Ejemplos de nombres de rama:**
```
modulo/ventas-facturas
modulo/inventario-productos
modulo/rrhh-colaboradores
fix/login-error-sesion
```

### Durante el desarrollo

```bash
# Ver qué archivos modificaste
git status

# Guardar tus cambios (hacer commit)
git add .
git commit -m "feat: descripción de lo que hiciste"
```

### Al terminar el día o un avance

```bash
# Subir tu rama a GitHub
git push origin modulo/nombre-del-modulo
```

Luego en GitHub → **"Compare & pull request"** → pedir revisión al líder antes de mezclar a `main`.

---

## Reglas del repositorio

| Rama | Uso |
|---|---|
| `main` | Código estable. **No hacer push directo aquí.** |
| `modulo/xxx` | Una rama por módulo o funcionalidad |
| `fix/xxx` | Para corrección de errores |

### Antes de hacer merge a main, verificar:
- [ ] `php artisan migrate` corre sin errores
- [ ] `npm run build` compila sin errores
- [ ] El módulo funciona en el navegador
- [ ] No hay conflictos con otras ramas

---

## División de módulos sugerida

### Programador 1
- Módulo Ventas (facturas, proformas, CxC, notas de crédito)
- Módulo Compras (órdenes, proveedores, CxP)

### Programador 2
- Módulo Inventario (productos, kárdex, bodegas)
- Módulo Taller (órdenes de trabajo, equipos)

---

## Comandos Git más usados

```bash
# Ver en qué rama estás
git branch

# Cambiar de rama
git checkout nombre-rama

# Traer cambios de main a tu rama (para no quedarte atrás)
git merge main

# Ver historial de commits
git log --oneline

# Deshacer cambios en un archivo (cuidado)
git checkout -- nombre-archivo.php

# Ver diferencias antes de commitear
git diff
```

---

## Si hay conflictos

Cuando dos personas modificaron el mismo archivo:

```bash
# 1. Traer los cambios de main
git pull origin main

# 2. Git marcará los conflictos así en el archivo:
# <<<<<<< HEAD
# tu código
# =======
# código de otro
# >>>>>>> main

# 3. Editar el archivo, elegir qué código queda
# 4. Guardar y commitear
git add .
git commit -m "fix: resolver conflicto en NombreArchivo"
```

---

## Estructura de archivos — dónde trabaja cada módulo

```
app/Http/Controllers/     ← Controllers PHP de cada módulo
app/Models/               ← Modelos Eloquent
database/migrations/      ← Migraciones nuevas
resources/js/Pages/       ← Páginas React (frontend)
resources/js/Components/  ← Componentes reutilizables
routes/web.php            ← Agregar rutas nuevas aquí
```

### Convención para archivos nuevos

**Controller:** `app/Http/Controllers/Ventas/FacturaController.php`  
**Modelo:** `app/Models/Factura.php`  
**Migración:** `php artisan make:migration create_facturas_table`  
**Página React:** `resources/js/Pages/Ventas/Facturas/Index.tsx`  
**Ruta:** agregar en `routes/web.php` dentro del grupo `auth`

---

## Mensajes de commit — formato

```
tipo: descripción corta en español

feat:     nueva funcionalidad
fix:      corrección de error
style:    cambios de diseño/CSS
refactor: reorganización de código
docs:     documentación
```

**Ejemplos:**
```
feat: crear listado de facturas con filtros
feat: agregar formulario de nueva factura
fix: corregir cálculo de IVA en totales
style: ajustar diseño de tabla de productos
```

---

## Contacto y repositorio

- **Repo:** https://github.com/hc-sistemas/alt
- **Rama principal:** `main`
- **Credenciales de prueba:** ver `INSTALACION.md`
