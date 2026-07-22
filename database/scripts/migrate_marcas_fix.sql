-- =============================================================
-- migrate_marcas_fix.sql
-- Reemplaza las 7 marcas incorrectas por marcas reales extraídas
-- de erp_mp.mp_n (altamira_legacy) y actualiza marca_id en productos.
--
-- Idempotente: ON CONFLICT + condicionales garantizan re-ejecución segura.
-- Ejecutar: psql -U postgres -d altamira -f migrate_marcas_fix.sql
-- =============================================================

BEGIN;

-- ─────────────────────────────────────────────────────────────
-- Función de sesión: normaliza variantes sucias al nombre canónico
-- ─────────────────────────────────────────────────────────────
CREATE OR REPLACE FUNCTION pg_temp.normalizar_marca(raw_name TEXT)
RETURNS TEXT
LANGUAGE sql
IMMUTABLE
AS $func$
    SELECT CASE TRIM(UPPER(raw_name))
        WHEN 'EALIGH'        THEN 'EALIGHT'
        WHEN 'EAALIGHT'      THEN 'EALIGHT'
        WHEN 'EALIGHTS'      THEN 'EALIGHT'
        WHEN 'EALGHT'        THEN 'EALIGHT'
        WHEN 'EA LIGHT'      THEN 'EALIGHT'
        WHEN 'EASOND'        THEN 'EASOUND'
        WHEN 'EASOUD'        THEN 'EASOUND'
        WHEN 'EA SOUND'      THEN 'EASOUND'
        WHEN 'EA@COUSTIC'    THEN 'EASOUND'
        WHEN 'EA'            THEN 'EASOUND'
        WHEN 'BESSER SOUND'  THEN 'BESSERSOUND'
        WHEN 'BESSER'        THEN 'BESSERSOUND'
        WHEN 'BESSESOUND'    THEN 'BESSERSOUND'
        WHEN 'PIONNER'       THEN 'PIONEER'
        WHEN 'PIONEER DJ'    THEN 'PIONEER'
        WHEN 'PIONNER DJ'    THEN 'PIONEER'
        WHEN 'DENO DJ'       THEN 'DENON'
        WHEN 'DENON DJ'      THEN 'DENON'
        WHEN 'DEON'          THEN 'DENON'
        WHEN 'PRO DG'        THEN 'PRODG'
        WHEN 'PSG'           THEN 'PSG AUDIO'
        WHEN 'NUMARK NS7'    THEN 'NUMARK'
        WHEN 'FUER'          THEN 'FEUR'
        WHEN 'HERCULES'      THEN 'HERCULES DJ'
        WHEN 'FAITALPRO'     THEN 'FAITAL PRO'
        WHEN 'BETA3'         THEN 'BETA 3'
        WHEN 'ABC CABLS'     THEN 'ABCCABLS'
        WHEN 'SHUREE'        THEN 'SHURE'
        WHEN 'LANE'          THEN 'RANE'
        WHEN 'WHAFERDALE'    THEN 'WHARFEDALE'
        ELSE TRIM(UPPER(raw_name))
    END;
$func$;


-- ─────────────────────────────────────────────────────────────
-- Paso 1: Insertar marcas normalizadas desde legacy (vía dblink)
-- ON CONFLICT DO NOTHING garantiza idempotencia.
-- ─────────────────────────────────────────────────────────────
INSERT INTO marcas (nombre)
SELECT DISTINCT pg_temp.normalizar_marca(leg.mp_n)
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    $sql$
        SELECT DISTINCT mp_n
        FROM erp_mp
        WHERE ids = 26
          AND mp_n IS NOT NULL
          AND TRIM(mp_n) <> ''
    $sql$
) AS leg(mp_n TEXT)
WHERE TRIM(UPPER(leg.mp_n)) NOT IN (
    'NA',
    'EDITAR PRODUCTO',
    'TERMOMETRO MEDICO',
    'SERVICIO TÉCNICO',
    '12INCH',
    '802.1IN',
    'NUMARK/DENON',
    'AYR.000044'
)
ON CONFLICT (nombre) DO NOTHING;


-- ─────────────────────────────────────────────────────────────
-- Paso 2: Desvincular productos que apuntan a las 7 marcas incorrectas
-- ─────────────────────────────────────────────────────────────
UPDATE productos
SET marca_id = NULL
WHERE marca_id IN (
    SELECT id FROM marcas
    WHERE nombre IN (
        'EA SOUND',
        'FEUR',
        'PSG AUDIO',
        'PRO DG SYSTEMS',
        'DEZIBEL',
        'DJS',
        'EA LIGHT'
    )
);

-- ─────────────────────────────────────────────────────────────
-- Paso 2b: Eliminar las 7 marcas incorrectas
-- (productos ya desvinculados, no hay FK que bloquee)
-- ─────────────────────────────────────────────────────────────
DELETE FROM marcas
WHERE nombre IN (
    'EA SOUND',
    'FEUR',
    'PSG AUDIO',
    'PRO DG SYSTEMS',
    'DEZIBEL',
    'DJS',
    'EA LIGHT'
);


-- ─────────────────────────────────────────────────────────────
-- Paso 3: Actualizar marca_id en productos cruzando con legacy
--
-- DISTINCT ON (codigo) deduplicamos en caso de mp_c repetido en legacy.
-- El JOIN garantiza que solo se actualiza si la marca normalizada existe.
-- Productos con mp_n en la lista de basura quedan con marca_id = NULL.
-- ─────────────────────────────────────────────────────────────
UPDATE productos p
SET marca_id = m.id
FROM (
    SELECT DISTINCT ON (leg.codigo)
        leg.codigo,
        pg_temp.normalizar_marca(leg.mp_n) AS marca_norm
    FROM dblink(
        'dbname=altamira_legacy user=postgres password=512',
        $sql$
            SELECT mp_c::text AS codigo, mp_n
            FROM erp_mp
            WHERE ids = 26
              AND mp_c IS NOT NULL
              AND TRIM(mp_c) <> ''
              AND mp_n IS NOT NULL
              AND TRIM(mp_n) <> ''
        $sql$
    ) AS leg(codigo TEXT, mp_n TEXT)
    WHERE TRIM(UPPER(leg.mp_n)) NOT IN (
        'NA',
        'EDITAR PRODUCTO',
        'TERMOMETRO MEDICO',
        'SERVICIO TÉCNICO',
        '12INCH',
        '802.1IN',
        'NUMARK/DENON',
        'AYR.000044'
    )
    ORDER BY leg.codigo
) AS ldata
JOIN marcas m ON m.nombre = ldata.marca_norm
WHERE p.codigo = ldata.codigo;


COMMIT;


-- =============================================================
-- Verificación final (corre fuera de la transacción)
-- =============================================================

\echo ''
\echo '=== RESULTADO ==='

\echo '--- Total de marcas en la tabla ---'
SELECT COUNT(*) AS total_marcas FROM marcas;

\echo '--- Productos sin marca (meta: 0 o cercano) ---'
SELECT COUNT(*) AS productos_sin_marca FROM productos WHERE marca_id IS NULL;

\echo '--- Marcas incorrectas que NO deben existir (debe estar vacío) ---'
SELECT nombre FROM marcas
WHERE nombre IN (
    'EA SOUND', 'FEUR', 'PSG AUDIO', 'PRO DG SYSTEMS',
    'DEZIBEL', 'DJS', 'EA LIGHT'
);

\echo '--- Top 20 marcas por cantidad de productos ---'
SELECT
    m.nombre,
    COUNT(p.id) AS total_productos
FROM marcas m
LEFT JOIN productos p ON p.marca_id = m.id
GROUP BY m.nombre
ORDER BY COUNT(p.id) DESC
LIMIT 20;
