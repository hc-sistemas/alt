#!/bin/bash
# ==============================================================================
# deploy.sh - Deploy repetible y sin downtime para sistema.altamiralightsounds.com
# ==============================================================================
# Uso: correr como root vía SSH:
#   bash /home/altamira/deploy.sh
#
# Qué hace:
#   1. Clona la última versión de la rama "produccion" en una carpeta nueva
#      (releases/<timestamp>), sin tocar el release que está sirviendo tráfico.
#   2. Enlaza .env y storage/ compartidos (no se pisan datos ni credenciales).
#   3. Instala dependencias con Composer (PHP 8.3).
#   4. Instala dependencias de Node y compila Vite (como root, por el límite
#      de archivos abiertos del usuario altamira — ver comentario abajo).
#   5. Corre migraciones (son idempotentes, seguras de repetir).
#   6. Reconstruye caché de config/rutas/vistas.
#   7. Switch atómico: cambia el symlink del dominio al nuevo release.
#   8. Borra releases viejos, dejando los últimos 5 (por espacio en disco).
#
# Si algo fallara a mitad de camino, el sitio sigue sirviendo el release
# anterior sin interrupción (el symlink no se mueve hasta el paso 7).
# ==============================================================================

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
