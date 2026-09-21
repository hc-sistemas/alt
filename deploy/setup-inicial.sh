#!/bin/bash
# ==============================================================================
# setup-inicial.sh - Migración ÚNICA de deploy manual a estructura releases/
# ==============================================================================
# CORRER UNA SOLA VEZ. Después de esto, todas las actualizaciones usan deploy.sh.
#
# Qué hace, en orden:
#   1. Backup completo (archivos + base de datos) ANTES de tocar nada.
#   2. Crea carpetas releases/ y shared/.
#   3. Copia .env y storage/ actuales a shared/ (se preservan tal cual).
#   4. Mueve la carpeta actual (deploy manual) a releases/00-legacy-manual
#      (queda intacta ahí como respaldo/rollback de emergencia).
#   5. Clona el código de la rama "produccion" como primer release real.
#   6. Instala dependencias, corre migraciones, cachea.
#   7. Switch atómico: sistema.altamiralightsounds.com pasa a ser un symlink.
#
# Uso: correr como root vía SSH:
#   bash setup-inicial.sh
# ==============================================================================

set -euo pipefail

APP_USER="altamira"
BASE="/home/altamira"
DOMAIN_PATH="$BASE/sistema.altamiralightsounds.com"
RELEASES_DIR="$BASE/releases"
SHARED_DIR="$BASE/shared"
BACKUP_DIR="$BASE/backups"
PHP_BIN="/opt/alt/php83/usr/bin/php"
COMPOSER_BIN="/usr/local/bin/composer"
REPO_URL="https://github.com/hc-sistemas/alt.git"
BRANCH="produccion"
TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="$RELEASES_DIR/$TIMESTAMP"

if [ -L "$DOMAIN_PATH" ]; then
    echo "ERROR: $DOMAIN_PATH ya es un symlink. Este script ya se corrió antes."
    echo "Para actualizar usa deploy.sh, no este script."
    exit 1
fi

echo "=== [0/7] Verificando que se puede operar como $APP_USER ==="
su -s /bin/bash - "$APP_USER" -c "echo OK" > /dev/null

echo "=== [1/7] Backup completo antes de tocar nada ==="
mkdir -p "$BACKUP_DIR"
tar -czf "$BACKUP_DIR/pre-setup-$TIMESTAMP-archivos.tar.gz" -C "$BASE" "sistema.altamiralightsounds.com"
echo "  Backup de archivos -> $BACKUP_DIR/pre-setup-$TIMESTAMP-archivos.tar.gz"

sudo -u postgres pg_dump altamira | gzip > "$BACKUP_DIR/pre-setup-$TIMESTAMP-db.sql.gz"
echo "  Backup de base de datos -> $BACKUP_DIR/pre-setup-$TIMESTAMP-db.sql.gz"

echo "=== [2/7] Creando estructura releases/ y shared/ ==="
mkdir -p "$RELEASES_DIR" "$SHARED_DIR"

echo "=== [3/7] Preservando .env y storage/ actuales en shared/ ==="
cp "$DOMAIN_PATH/.env" "$SHARED_DIR/.env"
cp -a "$DOMAIN_PATH/storage" "$SHARED_DIR/storage"

echo "=== [4/7] Moviendo el deploy manual actual a releases/00-legacy-manual (respaldo) ==="
mv "$DOMAIN_PATH" "$RELEASES_DIR/00-legacy-manual"

echo "=== [5/7] Clonando rama '$BRANCH' como primer release ==="
git clone --branch "$BRANCH" --depth 1 "$REPO_URL" "$NEW_RELEASE"
rm -rf "$NEW_RELEASE/storage"
ln -s "$SHARED_DIR/storage" "$NEW_RELEASE/storage"
ln -s "$SHARED_DIR/.env" "$NEW_RELEASE/.env"
chown -R "$APP_USER:$APP_USER" "$NEW_RELEASE" "$SHARED_DIR"

echo "=== [6/7] Instalando dependencias, migrando y cacheando ==="
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && $PHP_BIN $COMPOSER_BIN install --no-dev --optimize-autoloader --no-interaction"
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && $PHP_BIN artisan migrate --force"
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && $PHP_BIN artisan config:cache && $PHP_BIN artisan route:cache && $PHP_BIN artisan view:cache"
su -s /bin/bash - "$APP_USER" -c "cd '$NEW_RELEASE' && [ -L public/storage ] || $PHP_BIN artisan storage:link"

echo "=== [7/7] Switch atómico: activando el nuevo release ==="
su -s /bin/bash - "$APP_USER" -c "ln -sfn '$NEW_RELEASE' '$DOMAIN_PATH'"

echo ""
echo "=== SETUP INICIAL COMPLETO ==="
echo "Release activo: $NEW_RELEASE"
echo "Verifica ahora mismo: https://sistema.altamiralightsounds.com"
echo ""
echo "Si algo se ve mal, rollback INMEDIATO al deploy manual anterior:"
echo "  rm '$DOMAIN_PATH' && mv '$RELEASES_DIR/00-legacy-manual' '$DOMAIN_PATH'"
echo ""
echo "A partir de ahora, para actualizar usa: bash deploy.sh"
