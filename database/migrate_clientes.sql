-- Migración de clientes desde altamira_legacy a altamira
-- Ejecutar con: psql -U postgres -d altamira -f database/migrate_clientes.sql

-- 1. Crear tabla de staging (drop si ya existe de un intento previo)
DROP TABLE IF EXISTS _tmp_clientes_legacy;

CREATE TABLE _tmp_clientes_legacy (
    cli_ced_ruc VARCHAR(20),
    razon_social VARCHAR(200),
    nombre_comercial VARCHAR(200),
    email VARCHAR(200),
    telefono VARCHAR(100),
    celular VARCHAR(100),
    direccion VARCHAR(300),
    ciudad VARCHAR(100),
    provincia VARCHAR(100),
    pais VARCHAR(100),
    tiene_credito BOOLEAN,
    dias_credito INTEGER,
    cupo_maximo NUMERIC,
    agente_retencion BOOLEAN,
    cli_fecha DATE
);

-- 2. Cargar CSV exportado de legacy
\copy _tmp_clientes_legacy FROM 'c:/Users/Asus/Desktop/Dario/Chamba/alt/database/legacy_clientes.csv' WITH CSV HEADER

-- 3. Insertar en clientes con mapeo de tipo_identificacion
INSERT INTO clientes (
    empresa_id,
    tipo_identificacion,
    identificacion,
    razon_social,
    nombre_comercial,
    email,
    telefono,
    celular,
    direccion,
    ciudad,
    provincia,
    pais,
    tiene_credito,
    dias_credito,
    cupo_maximo,
    agente_retencion,
    es_cliente_nuevo,
    estado,
    created_at,
    updated_at
)
SELECT
    1 AS empresa_id,
    CASE
        WHEN LENGTH(TRIM(cli_ced_ruc)) = 13 THEN '04'
        WHEN LENGTH(TRIM(cli_ced_ruc)) = 10 THEN '05'
        ELSE '06'
    END AS tipo_identificacion,
    TRIM(cli_ced_ruc) AS identificacion,
    COALESCE(razon_social, 'SIN NOMBRE') AS razon_social,
    nombre_comercial,
    email,
    LEFT(telefono, 20) AS telefono,
    LEFT(celular, 20) AS celular,
    direccion,
    ciudad,
    provincia,
    COALESCE(pais, 'ECUADOR') AS pais,
    COALESCE(tiene_credito, false) AS tiene_credito,
    COALESCE(dias_credito, 0)::smallint AS dias_credito,
    COALESCE(cupo_maximo, 0) AS cupo_maximo,
    COALESCE(agente_retencion, false) AS agente_retencion,
    false AS es_cliente_nuevo,
    true AS estado,
    COALESCE(cli_fecha::timestamp, NOW()) AS created_at,
    COALESCE(cli_fecha::timestamp, NOW()) AS updated_at
FROM _tmp_clientes_legacy
ON CONFLICT (empresa_id, identificacion) DO NOTHING;

-- 4. Limpiar staging
DROP TABLE _tmp_clientes_legacy;

-- 5. Reporte
SELECT COUNT(*) AS total_clientes FROM clientes;
