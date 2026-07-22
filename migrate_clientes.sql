INSERT INTO clientes (
    empresa_id, tipo_identificacion, identificacion,
    razon_social, nombre_comercial, email, telefono,
    direccion, ciudad, provincia, pais,
    tiene_credito, dias_credito, cupo_maximo,
    agente_retencion, estado, created_at, updated_at
)
SELECT
    e.id,
    CASE
        WHEN LENGTH(TRIM(COALESCE(c.cli_ced_ruc,'')))=13 THEN '04'
        WHEN LENGTH(TRIM(COALESCE(c.cli_ced_ruc,'')))=10 THEN '05'
        ELSE '07'
    END,
    LEFT(COALESCE(NULLIF(TRIM(c.cli_ced_ruc),''),'9999999999999'), 20),
    COALESCE(
        NULLIF(TRIM(c.cli_raz_social),''),
        NULLIF(TRIM(COALESCE(c.cli_apellidos,'')||' '||COALESCE(c.cli_nombres,'')),''),
        'SIN NOMBRE'
    ),
    NULLIF(TRIM(c.cli_nom_comercial),''),
    NULLIF(TRIM(c.cli_email),''),
    NULLIF(LEFT(TRIM(c.cli_telefono), 20),''),
    NULLIF(TRIM(
        COALESCE(c.cli_calle_prin,'')||
        CASE WHEN c.cli_numeracion IS NOT NULL THEN ' '||c.cli_numeracion ELSE '' END||
        CASE WHEN c.cli_calle_sec IS NOT NULL THEN ' y '||c.cli_calle_sec ELSE '' END
    ),''),
    NULLIF(TRIM(c.cli_canton),''),
    NULLIF(TRIM(c.cli_provincia),''),
    COALESCE(NULLIF(TRIM(c.cli_pais),''),'ECUADOR'),
    CASE WHEN c.cli_credito > 0 THEN TRUE ELSE FALSE END,
    CASE WHEN c.cli_credito > 0 THEN c.cli_credito ELSE 0 END,
    COALESCE(c.cli_cup_maximo,0),
    CASE WHEN TRIM(c.cli_retencion)='SI' THEN TRUE ELSE FALSE END,
    CASE WHEN c.cli_estado='1' THEN TRUE ELSE FALSE END,
    NOW(), NOW()
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    'SELECT cli_ced_ruc, cli_raz_social, cli_nom_comercial,
            cli_apellidos, cli_nombres, cli_email, cli_telefono,
            cli_calle_prin, cli_numeracion, cli_calle_sec,
            cli_canton, cli_provincia, cli_pais, cli_credito,
            cli_cup_maximo, cli_retencion, cli_estado
     FROM erp_i_cliente
     WHERE cli_ced_ruc IS NOT NULL AND TRIM(cli_ced_ruc) != ''''
    '
) AS c(
    cli_ced_ruc varchar, cli_raz_social varchar, cli_nom_comercial varchar,
    cli_apellidos varchar, cli_nombres varchar, cli_email varchar,
    cli_telefono varchar, cli_calle_prin varchar, cli_numeracion varchar,
    cli_calle_sec varchar, cli_canton varchar, cli_provincia varchar,
    cli_pais varchar, cli_credito bigint, cli_cup_maximo numeric,
    cli_retencion varchar, cli_estado varchar
)
CROSS JOIN (SELECT id FROM empresas) e
ON CONFLICT (empresa_id, identificacion) DO NOTHING;
