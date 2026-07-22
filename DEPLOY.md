# DEPLOY.md — ERP Altamira en producción (VPS)

Guía completa del flujo de deploy a `sistema.altamiralightsounds.com`. Léela antes de tocar el servidor.

---

## Datos del entorno

| Dato | Valor |
|---|---|
| VPS | AlmaLinux, panel CWP (CentOS WebPanel) |
| IP | 148.163.124.118 |
| Cuenta / usuario del sistema | `altamira` (uid 1005) |
| Dominio | sistema.altamiralightsounds.com |
| Document root (fijo, no cambiar) | `/home/altamira/sistema.altamiralightsounds.com` |
| PHP (CLI, para composer/artisan) | `/opt/alt/php83/usr/bin/php` — **el `php` por defecto del sistema es 8.1, no sirve** |
| Composer | `/usr/local/bin/composer` |
| Base de datos | PostgreSQL 12.22, base `altamira` |
| Repo | https://github.com/hc-sistemas/alt (público) |
| Rama de producción | `produccion` |

**Importante:** este VPS tiene 6 cuentas más corriendo aplicaciones en producción. Todo lo de este documento aplica **exclusivamente** a la cuenta `altamira`. Nunca tocar otras cuentas/dominios.

---

## Por qué esta estructura

La app antes se subía manualmente (sin git) directo a `/home/altamira/sistema.altamiralightsounds.com`. Eso hacía cada actualización manual y arriesgada (sin rollback, sin forma de saber qué versión está corriendo).

Ahora se usa el patrón estándar de deploy atómico:

```
/home/altamira/
  releases/
    00-legacy-manual/        <- respaldo del deploy manual original (no tocar)
    20260722153000/          <- cada deploy crea una carpeta con timestamp
    20260725101500/
  shared/
    .env                     <- credenciales reales, nunca se pisan
    storage/                 <- uploads, logs, cache — persisten entre deploys
  sistema.altamiralightsounds.com -> releases/20260725101500   (symlink)
```

El dominio (`DocumentRoot` de Apache) siempre apunta al mismo path, pero ese path es un **symlink** que se redirige al release más reciente. Cambiar el symlink es instantáneo (`ln -sfn`), así que no hay downtime ni archivos a medio subir.

`.env` y `storage/` viven en `shared/` y se enlazan (symlink) dentro de cada release nuevo — así nunca se pierden credenciales, uploads de usuarios, ni logs al actualizar.

---

## Setup inicial (correr UNA SOLA VEZ)

Ya se preparó `deploy/setup-inicial.sh` en el repo. Convierte el deploy manual actual a la estructura de arriba, sin perder nada:

1. Hace backup completo (archivos + base de datos) antes de tocar cualquier cosa.
2. Preserva `.env` y `storage/` actuales en `shared/`.
3. Mueve el deploy manual actual a `releases/00-legacy-manual` (queda ahí como red de seguridad).
4. Clona la rama `produccion` como primer release real.
5. Instala dependencias, corre migraciones, cachea.
6. Activa el symlink.

```bash
cd /home/altamira
git clone --branch produccion --depth 1 https://github.com/hc-sistemas/alt.git /tmp/alt-deploy-scripts
cp /tmp/alt-deploy-scripts/deploy/*.sh /home/altamira/
rm -rf /tmp/alt-deploy-scripts
bash /home/altamira/setup-inicial.sh
```

Si algo se ve mal después de correrlo, rollback inmediato al deploy manual anterior:

```bash
rm /home/altamira/sistema.altamiralightsounds.com
mv /home/altamira/releases/00-legacy-manual /home/altamira/sistema.altamiralightsounds.com
```

### Problema ya resuelto: ownership de tablas en PostgreSQL

Al correr `setup-inicial.sh` la primera vez, las migraciones fallaron con `SQLSTATE[42501]: Insufficient privilege` al alterar tablas existentes. Causa: las 91 tablas de la base `altamira` eran dueñas del rol `postgres`, no de `altamira_user` (el usuario que usa la app en `.env`). Los `CREATE TABLE` funcionan igual (el que crea se vuelve dueño), pero cualquier `ALTER TABLE` sobre una tabla ya existente falla si el usuario de la app no es su dueño.

Ya se corrigió una sola vez con:

```bash
sudo -u postgres psql -d altamira <<'EOF'
SELECT 'ALTER TABLE public.' || quote_ident(tablename) || ' OWNER TO altamira_user;' FROM pg_tables WHERE schemaname='public' AND tableowner != 'altamira_user' \gexec
SELECT 'ALTER SEQUENCE public.' || quote_ident(sequencename) || ' OWNER TO altamira_user;' FROM pg_sequences WHERE schemaname='public' AND sequenceowner != 'altamira_user' \gexec
EOF
```

No debería volver a pasar (las tablas nuevas las crea `altamira_user` y ya queda como dueño), pero si algún día se restaura un dump con `pg_dump`/`psql` como usuario `postgres`, va a repetirse — correr el mismo bloque de arriba después de restaurar.

---

## Actualizaciones futuras (esto es lo único que necesitas correr)

Cada vez que haya cambios nuevos en la rama `produccion` (después de hacer `git push` desde tu PC):

```bash
bash /home/altamira/deploy.sh
```

Eso es todo. El script clona la última versión, instala dependencias, migra, cachea, y hace el switch atómico. Guarda los últimos 5 releases por si hay que hacer rollback rápido:

```bash
ln -sfn /home/altamira/releases/<timestamp-anterior> /home/altamira/sistema.altamiralightsounds.com
```

### Antes de correr deploy.sh, en tu PC:

1. Si cambiaste algo en el frontend (React/TSX), compila los assets: `npm run build`
2. `git add -A && git commit -m "..."` y `git push` (rama `produccion`) — vía GitHub Desktop o VS Code, como ya lo hicimos.
3. El `public/build` compilado va incluido en el commit (excepción al `.gitignore` normal) para que el VPS no necesite Node instalado.

---

## Cargar datos reales a producción (sin pasar por git)

**Nunca subir dumps con datos reales de clientes al repositorio.** Estos archivos están en `.gitignore` a propósito:

- `database/altamira_dump_clientes.sql`
- `database/altamira_dump_completo.sql`
- `database/altamira_dump_dev2.sql`
- `run_migration_inventario.py` (tiene una contraseña hardcodeada — hay que revisarla y quitarla)

Para llevar estos datos al VPS, transferirlos directo por SCP/SFTP (nunca por git):

```bash
scp database/altamira_dump_clientes.sql root@148.163.124.118:/home/altamira/tmp_dumps/
```

Y cargarlos como el usuario `altamira`, apuntando a la base `altamira`:

```bash
su - altamira
psql -U postgres -d altamira -f /home/altamira/tmp_dumps/altamira_dump_clientes.sql
```

**Antes de restaurar cualquier dump sobre producción:** compara contra el backup real de producción (`pg_dump` de la BD actual) para no pisar datos reales con datos de prueba. Los datos de producción son la fuente de verdad, nunca al revés.

Borra los archivos temporales del VPS después (`rm -rf /home/altamira/tmp_dumps`) — no deben quedar dumps con datos reales sueltos en el servidor tampoco.

---

## Pendiente / notas para la reunión con los 2 desarrolladores

- La rama `produccion` sale de tu copia local (`admin-local-snapshot`), **no** incluye los cambios de las ramas `feature/dev1-personas-clientes` y `feature/dev2-contabilidad-compras` que ya están mergeados en `main`. Falta reconciliar 112 archivos con conflictos reales (ver resumen de la sesión: rutas, inventario, ventas, etc.).
- Tu copia local tiene migraciones para **eliminar las tablas de Spatie Permission** (`2026_07_18_000001_drop_spatie_permission_tables.php`) a favor de un sistema de permisos propio. `origin/main` todavía usa Spatie activamente. Confirmado contigo que es intencional — hay que alinear con los devs.
- El historial de `main`, `develop` y las 2 ramas `feature/*` en GitHub ya fue limpiado localmente (se purgaron los dumps con datos reales y la contraseña hardcodeada), pero **ese historial limpio todavía no se subió a GitHub** — requiere coordinarlo con los devs antes de hacer force-push, porque reescribe el historial que ellos ya tienen clonado.
- Considera cambiar el repo de GitHub a **privado** — actualmente es público.
