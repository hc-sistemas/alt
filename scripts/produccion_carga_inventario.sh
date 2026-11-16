#!/usr/bin/env bash
# Habilitación del inventario en PRODUCCIÓN (carga limpia al 31/10/2026) — ERP Altamira.
# Guía completa: docs/DESPLIEGUE_PRODUCCION.md §10. Ejecutar en el servidor, dentro de la carpeta del proyecto,
# DESPUÉS de haber desplegado el código (git pull, composer install, npm ci && npm run build).
#
# Cada paso que cambia datos pide confirmación. Solo toca la base «altamira» (el Postgres del VPS es compartido:
# nunca «lideres» ni «sae_alt»). Uso:  bash scripts/produccion_carga_inventario.sh /ruta/Inventario_Altamira_ERP_Carga.xlsx
set -euo pipefail

EXCEL="${1:-}"
[ -f "$EXCEL" ] || { echo "Uso: $0 /ruta/Inventario_Altamira_ERP_Carga.xlsx  (el archivo se pasa por scp, no está en git)"; exit 1; }
PHP="${PHP_BIN:-php}"
DB="altamira"
RESPALDO_DIR="${RESPALDO_DIR:-$HOME/respaldos-altamira}"
mkdir -p "$RESPALDO_DIR"

confirmar() { read -r -p "$1 [escriba SI para continuar] " r; [ "$r" = "SI" ] || { echo "Cancelado."; exit 1; }; }

echo "== 0. Comprobaciones"
$PHP artisan --version
grep -q "^DB_DATABASE=${DB}\s*$" .env || { echo "El .env no apunta a la base '${DB}'. Se detiene."; exit 1; }

echo "== 1. Respaldo de la base ${DB}"
confirmar "Se generará el respaldo en ${RESPALDO_DIR}."
ARCHIVO="${RESPALDO_DIR}/altamira_antes_carga_inventario_$(date +%Y%m%d_%H%M%S).backup"
pg_dump -U "${PGUSER:-postgres}" -h "${PGHOST:-127.0.0.1}" -Fc -f "$ARCHIVO" "$DB"
ls -lh "$ARCHIVO"

echo "== 2. Migraciones (incluye 2026_11_16_100000_inventario_plan_v51_subcategorias_nuevas)"
$PHP artisan altamira:preflight-despliegue || { echo "El preflight reportó bloqueos: se detiene."; exit 1; }
confirmar "¿Ejecutar php artisan migrate --force?"
$PHP artisan migrate --force
$PHP artisan migrate:status | grep -i pending && { echo "Quedan migraciones pendientes."; exit 1; } || true

echo "== 3. Datos de prueba (la carga se niega a correr si hay documentos con productos)"
$PHP artisan altamira:limpiar-datos-prueba       # simula
echo "Si arriba aparecen datos de PRUEBA (facturas, compras, asientos…) bórrelos; si son datos REALES, deténgase."
read -r -p "¿Borrar los datos de prueba mostrados? [SI / NO] " r
if [ "$r" = "SI" ]; then $PHP artisan altamira:limpiar-datos-prueba --ejecutar; fi

echo "== 4. IVA vigente 15 % de los productos migrados (simula)"
$PHP artisan altamira:actualizar-iva-productos
read -r -p "¿Aplicar el IVA 15 %? [SI / NO] " r
if [ "$r" = "SI" ]; then $PHP artisan altamira:actualizar-iva-productos --aplicar; fi

echo "== 5. Carga limpia del inventario — SIMULACIÓN"
$PHP artisan inventario:carga-limpia "$EXCEL"
echo "Esperado: 13 bajas, 45 renumerados, 17 nuevos, 12.032 unidades y \$175.015,00. Si no coincide, se detiene."
confirmar "¿Aplicar la carga limpia? (respaldo JSON automático en storage/app/private/carga-limpia/)"
$PHP artisan inventario:carga-limpia "$EXCEL" --aplicar

echo "== 6. Verificación"
$PHP artisan inventario:carga-limpia "$EXCEL" | tail -3   # debe decir que ya se aplicó
echo "Revisar: Contabilidad → Asientos (AS-… «Apertura de inventario al 31/10/2026»), Inventario → Consolidado (12.032 u / \$175.015,00)"
echo "y facturar un producto y un servicio de prueba (el asiento debe usar la cuenta de su subcategoría)."
echo "Listo. Respaldo previo: $ARCHIVO"
