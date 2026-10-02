# DEPLOY.md — ERP Altamira en producción (VPS)

Guía completa y actualizada (2026-09-27) del flujo de deploy a `sistema.altamiralightsounds.com`. Léela antes de tocar el servidor.

---

## Datos del entorno

| Dato | Valor |
|---|---|
| VPS | AlmaLinux, panel CWP (CentOS WebPanel) |
| IP | 148.163.124.118 (también resuelve como `vm620.gmc360.net`) |
| Cuenta / usuario del sistema | `altamira` — **shell `nologin`**, todo se opera con `su -s /bin/bash - altamira -c "..."` |
| Acceso SSH | como `root`, vía llave pública ya autorizada en `~/.ssh/authorized_keys` de root |
| Dominio | sistema.altamiralightsounds.com |
| Document root (fijo, no cambiar) | `/home/altamira/sistema.altamiralightsounds.com` (es un **symlink**, no una carpeta real) |
| PHP (CLI, para composer/artisan/build) | `/opt/alt/php83/usr/bin/php` — **el `php` por defecto del sistema es 8.1, no sirve** |
| Node | v20.19.5 (sí está instalado en el VPS) |
| Composer | `/usr/local/bin/composer` |
| Base de datos | PostgreSQL, base `altamira` (usuario de la app: `altamira_user`) |
| Repo | https://github.com/hc-sistemas/alt (**público** — pendiente evaluar privado) |
| Rama de producción | `produccion` |

**Importante — Postgres es compartido:** esa misma instancia de PostgreSQL tiene otras bases de datos de producción de otras aplicaciones: `lideres` y `sae_alt`. **Solo `altamira` se toca.** Nunca correr `DROP`, `pg_dump`/`pg_restore`, cambios de configuración global (`pg_hba.conf`, reinicio del servicio) ni nada que no especifique explícitamente `-d altamira`, sin confirmar antes.

---

## Cómo funciona (deploy atómico con releases/)

```
/home/altamira/
  releases/
    00-legacy-manual/        <- respaldo del deploy manual original (no tocar, es la red de seguridad más vieja)
    20260921203756/          <- cada deploy crea una carpeta con timestamp
    20260921210632/
  shared/
    .env                     <- credenciales reales, nunca se pisan
    storage/                 <- uploads, logs, cache — persisten entre deploys
  sistema.altamiralightsounds.com -> releases/20260921210632   (symlink)
  deploy.sh                  <- el script real que se ejecuta (vive en $HOME, no dentro de un release)
```

El dominio (`DocumentRoot` de Apache) siempre apunta al mismo path, pero ese path es un symlink que se redirige al release más reciente. Cambiar el symlink es instantáneo (`ln -sfn`), así que no hay downtime ni archivos a medio subir. `.env` y `storage/` viven en `shared/` y se enlazan dentro de cada release nuevo — nunca se pierden credenciales, uploads ni logs al actualizar.

---

## Actualizar producción (lo único que necesitas correr normalmente)

**Antes, en tu PC:**
1. Confirma que tu trabajo está probado localmente.
2. `git push` a `main` (o a la rama que corresponda) y luego asegúrate de que `produccion` tenga ese mismo código —
   ver sección "Cómo actualizar la rama `produccion`" más abajo. `deploy.sh` **siempre** clona `produccion`, nunca `main`.

**En el servidor, por SSH como `root`:**
```bash
bash /home/altamira/deploy.sh
```

Eso es todo. El script (8 pasos, ver contenido íntegro más abajo):
1. Verifica que se puede operar como `altamira`.
2. Clona `produccion` en `releases/<timestamp>` (sin tocar el release que sirve tráfico).
3. Enlaza `.env` y `storage/` compartidos.
4. `composer install --no-dev --optimize-autoloader`.
5. **Instala dependencias de Node y compila Vite** — corre como `root` (no como `altamira`, ver por qué en la sección de problemas resueltos), y **borra `node_modules` al terminar** (en producción solo hace falta `public/build/`).
6. `php artisan migrate --force` (idempotente).
7. Cachea config/rutas/vistas, `storage:link`.
8. Switch atómico del symlink del dominio, y limpia releases viejos dejando los últimos 5.

Si algo falla en cualquier paso antes del switch (paso 8 en esta versión), el sitio sigue sirviendo el release anterior sin interrupción. El propio script imprime al final el comando de rollback exacto:
```bash
ln -sfn $(ls -1dt /home/altamira/releases/*/ | grep -v <el-nuevo> | grep -v 00-legacy-manual | head -1 | sed 's:/$::') /home/altamira/sistema.altamiralightsounds.com
```

---

## Cómo actualizar la rama `produccion`

`produccion` **no** se fusiona normalmente con `git merge` — tiene un historial no relacionado con `main` (se creó por separado). El patrón que usamos:

```bash
git fetch origin
git checkout -b produccion origin/produccion   # o: git checkout produccion && git reset --hard origin/produccion
git checkout main -- .                         # trae TODO el contenido de main
git reset --hard main                          # asegura que produccion == main a nivel de árbol
git checkout origin/produccion -- deploy/deploy.sh deploy/setup-inicial.sh DEPLOY.md .htaccess
git commit -m "deploy: alinear produccion con main"
git push --force-with-lease origin produccion
```

Esto deja `produccion` = contenido de `main` + los 4 archivos propios del deploy (`deploy.sh`, `setup-inicial.sh`, `DEPLOY.md`, `.htaccess` de raíz) que `main` no tiene por diseño (`.gitignore` no aplica a deploy porque esos archivos viven en `deploy/` y en la raíz, fuera de lo que Laravel ignora — pero `public/build` sí lo ignora `main`, y por eso el paso 5 del script compila en el servidor en vez de depender de que el build venga en el commit).

**Antes de hacer `--force-with-lease`:** revisa si `produccion` tiene commits propios que `main` no tenga (además de los 4 archivos de deploy) — puede pasar si alguien tocó algo directo ahí. Compáralos con:
```bash
git log origin/main..origin/produccion --oneline
```

---

## Problemas ya resueltos (no los repitas / no te sorprendan)

### 1. `EMFILE: too many open files` al compilar Vite como usuario `altamira`
El usuario `altamira` tiene un límite de descriptores de archivo (`ulimit -n`) bajo, impuesto por PAM (`/etc/security/limits.conf`), que **no puede subir por sí mismo** (confirmado: `ulimit -n 65536` como `altamira` da "Operation not permitted"). `npm ci`/`vite build` abren muchos archivos a la vez para un árbol de ~200 paquetes y truenan con ese límite.

**Solución aplicada:** el paso 5 de `deploy.sh` corre `npm ci && npm run build` como **root** (`ulimit -n 65536` primero, sin restricción para root), y al terminar hace `chown -R altamira:altamira` sobre `public/build` y borra `node_modules` (no hace falta en runtime, y ahorra espacio en disco).

Si alguna vez hay que compilar a mano dentro de un release ya existente:
```bash
ulimit -n 65536
cd /home/altamira/releases/<timestamp>
rm -rf node_modules public/build
npm ci && npm run build
chown -R altamira:altamira public/build
rm -rf node_modules
```

### 2. Ownership de tablas en PostgreSQL (`Insufficient privilege` en migraciones)
Cuando se restaura un dump con `sudo -u postgres psql -f archivo.sql` o `pg_restore`, **todas las tablas quedan siendo propiedad del rol `postgres`**, no de `altamira_user` (el que usa `.env`). Los `CREATE TABLE` nuevos los crea quien corre la migración (`altamira_user` vía artisan) y quedan bien, pero cualquier `ALTER TABLE` sobre una tabla restaurada falla.

`REASSIGN OWNED BY postgres TO altamira_user` **no funciona** — Postgres lo rechaza ("no se puede reasignar la propiedad de objetos de rol postgres porque son requeridos por el sistema"). Hay que hacerlo tabla por tabla:

```bash
sudo -u postgres psql -d altamira -c "
DO \$\$
DECLARE r RECORD;
BEGIN
  FOR r IN SELECT tablename FROM pg_tables WHERE schemaname='public' LOOP
    EXECUTE format('ALTER TABLE public.%I OWNER TO altamira_user', r.tablename);
  END LOOP;
  FOR r IN SELECT sequencename FROM pg_sequences WHERE schemaname='public' LOOP
    EXECUTE format('ALTER SEQUENCE public.%I OWNER TO altamira_user', r.sequencename);
  END LOOP;
END \$\$;
"
```
Verificar después: `SELECT DISTINCT tableowner FROM pg_tables WHERE schemaname='public';` debe devolver solo `altamira_user`.

**Obligatorio después de cualquier restore de dump.**

### 3. El schema de la base `altamira` estaba MAL (2026-09-21)
Al hacer `\dt` sobre `altamira` en el VPS se encontraron tablas con prefijos `erp_`/`eqp_` (`erp_asientos_contables`, `eqp_orden_trabajo`, etc.) — un schema **completamente distinto** al que usa este código Laravel (que espera `empresas`, `productos`, `permisos`, `usuarios`, sin prefijos — ver CLAUDE.md). No se sabe con certeza cómo quedó así; probablemente un restore viejo de otro sistema.

**Se resolvió** borrando el schema `public` de esa base (`DROP SCHEMA public CASCADE; CREATE SCHEMA public;`) y restaurando un `pg_dump --no-owner --no-privileges --clean --if-exists` completo de la base local de desarrollo (96+ tablas, con datos reales: 2 empresas, 1646 productos, etc.), seguido del fix de ownership de la sección anterior.

**Si vuelve a pasar** (por ejemplo, si alguien restaura un backup viejo por error), repetir esos mismos pasos: backup del estado actual primero, `DROP SCHEMA public CASCADE`, restaurar el dump bueno, arreglar ownership.

### 4. `deploy.sh` clonaba pero nunca compilaba el frontend
Versión original tenía 7 pasos y **nunca corría `npm install`/`npm run build`** — dependía de que los assets de `public/build` vinieran ya compilados dentro del commit (patrón viejo, de cuando no había Node en el VPS). Como el `.gitignore` de `main` excluye `/public/build`, cualquier release clonado desde una rama que herede de `main` (como se hizo el 2026-09-21) se queda sin esos archivos y el sitio responde `500` con `Vite manifest not found`.

**Ya corregido** en el script actual (paso 5, ver arriba). Si en algún momento aparece ese error otra vez, la causa es que `deploy.sh` en el servidor quedó desactualizado — comparar con el contenido de referencia más abajo.

---

## Contenido de referencia de `/home/altamira/deploy.sh` (versión actual, 8 pasos)

```bash
#!/bin/bash
set -euo pipefail

APP_USER="altamira"
BASE="/home/altamira"
DOMAIN_PATH="$BASE/sistema.altamiralightsounds.com"
RELEASES_DIR="$BASE/releases"
SHARED_DIR="$BASE/shared"
PHP_BIN="/opt/alt/php83/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"
REPO_URL="https://github.com/hc-sistemas/alt.git"
BRANCH="produccion"
KEEP_RELEASES=5
TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="$RELEASES_DIR/$TIMESTAMP"

echo "=== [0/8] Verificando que se puede operar como $APP_USER ==="
su -s /bin/bash - "$APP_USER" -c "echo OK" > /dev/null

echo "=== [1/8] Clonando rama '$BRANCH' en $NEW_RELEASE ==="
git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$NEW_RELEASE"

echo "=== [2/8] Enlazando recursos compartidos (.env, storage) ==="
rm -rf "$NEW_RELEASE/storage"
ln -s "$SHARED_DIR/storage" "$NEW_RELEASE/storage"
ln -sf "$SHARED_DIR/.env" "$NEW_RELEASE/.env"
chown -R "$APP_USER:$APP_USER" "$NEW_RELEASE"

echo "=== [3/8] Instalando dependencias con Composer (sin dev) ==="
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && $PHP_BIN $COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction"

echo "=== [4/8] Instalando dependencias de frontend y compilando (Vite) ==="
# Se corre como root, no como altamira: el usuario altamira tiene un límite
# de descriptores de archivo (ulimit -n) bajo, fijado por PAM, que no puede
# subir por sí mismo (confirmado: "Operation not permitted"). npm/vite abren
# muchos archivos a la vez para un árbol de ~200 paquetes y truenan con
# EMFILE bajo ese límite. Root sí puede subirlo para este paso puntual.
# node_modules se borra al final: en producción solo hace falta
# public/build/ (los assets ya compilados), no el árbol de dependencias.
ulimit -n 65536
(cd "$NEW_RELEASE" && npm ci && npm run build)
rm -rf "$NEW_RELEASE/node_modules"
chown -R "$APP_USER:$APP_USER" "$NEW_RELEASE/public/build"

echo "=== [5/8] Corriendo migraciones (idempotentes) ==="
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && $PHP_BIN artisan migrate --force"

echo "=== [6/8] Reconstruyendo caché ==="
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && $PHP_BIN artisan config:cache && $PHP_BIN artisan route:cache && $PHP_BIN artisan view:cache"
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && [ -L public/storage ] || $PHP_BIN artisan storage:link"

echo "=== [7/8] Switch atómico del symlink del dominio ==="
su -s /bin/bash - "$APP_USER" -c "ln -sfn '$NEW_RELEASE' '$DOMAIN_PATH'"

echo "=== [8/8] Limpiando releases viejos (dejando los últimos $KEEP_RELEASES) ==="
cd "$RELEASES_DIR"
ls -1dt */ 2>/dev/null | grep -v '^00-legacy-manual/$' | tail -n +$((KEEP_RELEASES + 1)) | xargs -r rm -rf

echo ""
echo "=== DEPLOY COMPLETO ==="
echo "Release activo: $NEW_RELEASE"
echo "Verifica: https://sistema.altamiralightsounds.com"
echo ""
echo "Rollback rápido si algo falla:"
echo "  ln -sfn \$(ls -1dt $RELEASES_DIR/*/ | grep -v $NEW_RELEASE | grep -v 00-legacy-manual | head -1 | sed 's:/$::') $DOMAIN_PATH"
```

Esta misma copia debe vivir en `deploy/deploy.sh` dentro del repo (rama `produccion`) — si alguna vez hacen `setup-inicial.sh` desde cero en OTRO servidor, es de ahí de donde se copia.

---

## Verificación post-deploy (mínima, de rutina)

```bash
curl -sk -o /dev/null -w "HTTP %{http_code}\n" https://sistema.altamiralightsounds.com/login   # debe ser 200
readlink -f /home/altamira/sistema.altamiralightsounds.com                                       # confirma qué release quedó activo
tail -n 40 /home/altamira/shared/storage/logs/laravel.log                                        # revisar errores nuevos (por fecha/hora)
```

---

## Cargar datos reales a producción (sin pasar por git)

**Nunca subir dumps con datos reales al repositorio.** Si hace falta restaurar/actualizar datos:

1. Backup de la BD actual de producción primero, por si hay que revertir:
   ```bash
   sudo -u postgres pg_dump altamira | gzip > /home/altamira/backups/pre-restore-$(date +%Y%m%d%H%M%S).sql.gz
   ```
2. Subir el dump nuevo al VPS por SFTP (arrastrar y soltar en el panel de MobaXterm, o `scp`) a una ruta **legible por el usuario `postgres`** (nunca `/root`, que le da "Permission denied" — usar `/tmp` o `/home/altamira/`).
3. Restaurar:
   ```bash
   sudo -u postgres psql -d altamira -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public; GRANT ALL ON SCHEMA public TO altamira_user; GRANT ALL ON SCHEMA public TO postgres;"
   sudo -u postgres psql -d altamira -f /ruta/al/dump.sql
   ```
4. **Obligatorio:** correr el fix de ownership (sección "Problemas ya resueltos", punto 2).
5. Si `usuarios` se reemplazó, las contraseñas de login cambian a las del dump restaurado.
6. Borrar el dump temporal del VPS una vez confirmado que todo funciona.

---

## Pendientes conocidos (a la fecha de este documento)

- **Disco al 91%** en la partición `/home` (110G de 122G usados). Las releases de este proyecto pesan poco (~90 MB cada una desde que se borra `node_modules`), así que **no es este proyecto** el que llena el disco — es otra cosa en el servidor compartido. Sin investigar todavía a fondo (no se tocó nada de `lideres`/`sae_alt`).
- **`php artisan queue:work` no está corriendo bajo Supervisor.** Si nadie lo configura, el ZIP de roles de pago de Nómina se queda esperando en la tabla `jobs` para siempre en cuanto alguien lo use (ver sección "Colas" del `CLAUDE.md` del proyecto).
- **`php artisan schedule:run` por cron** tampoco está confirmado corriendo — de ahí salen las 4 alertas automáticas (CxP, vouchers, atrasos, cierre de nómina) y la limpieza de exportaciones de nómina.
- **No se confirmó qué PHP-FPM sirve realmente las peticiones web** del dominio (el 8.3 del `PHP_BIN` es solo para CLI/build). Pendiente de verificar contra la configuración de CWP para ese dominio específico.
- **Repo público en GitHub.** Considerar pasarlo a privado.
- **Contraseña de prueba en producción real:** `admin@altamira.com` quedó con la contraseña de prueba que se usó en desarrollo local (`Altamira2026*`), porque se restauró tal cual la tabla `usuarios`. Cambiarla antes de que el sistema tenga usuarios reales.
