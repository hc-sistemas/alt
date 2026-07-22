INSERT INTO productos (
    empresa_id, categoria_id, marca_id,
    codigo, nombre, descripcion,
    unidad, tipo, requiere_serie,
    pvp, pvd, costo,
    descuento_maximo, porcentaje_iva, porcentaje_ice,
    stock_minimo, stock_maximo,
    estado, created_at, updated_at
)
SELECT
    e.id AS empresa_id,
    c.id AS categoria_id,
    m.id AS marca_id,
    p.mp_c AS codigo,
    p.mp_d AS nombre,
    p.mp_o AS descripcion,
    COALESCE(NULLIF(TRIM(p.mp_q),''), 'UNIDAD') AS unidad,
    'producto' AS tipo,
    FALSE AS requiere_serie,
    COALESCE(REPLACE(p.mp_e, ',', '.')::numeric, 0) AS pvp,
    COALESCE(REPLACE(p.mp_f, ',', '.')::numeric, 0) AS pvd,
    COALESCE(REPLACE(p.mp_p, ',', '.')::numeric, 0) AS costo,
    COALESCE(REPLACE(p.mp_g, ',', '.')::numeric, 0) AS descuento_maximo,
    COALESCE(REPLACE(p.mp_h, ',', '.')::numeric, 12) AS porcentaje_iva,
    0 AS porcentaje_ice,
    0 AS stock_minimo,
    0 AS stock_maximo,
    CASE WHEN p.mp_i = '1' THEN TRUE ELSE FALSE END AS estado,
    NOW(), NOW()
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    'SELECT mp_c, mp_d, mp_o, mp_q, mp_e, mp_f, mp_p,
            mp_g, mp_h, mp_i, mp_a, mp_n, mar_id
     FROM erp_mp
     WHERE ids = 26
     AND mp_c IS NOT NULL AND TRIM(mp_c) != '''''
) AS p(
    mp_c varchar, mp_d varchar, mp_o varchar, mp_q varchar,
    mp_e varchar, mp_f varchar, mp_p varchar,
    mp_g varchar, mp_h varchar, mp_i varchar,
    mp_a varchar, mp_n varchar, mar_id int
)
CROSS JOIN (SELECT id FROM empresas) e
-- Mapear categoria por tps_id (mp_a)
LEFT JOIN categorias_producto c
    ON c.nombre = (
        SELECT tps_nombre FROM dblink(
            'dbname=altamira_legacy user=postgres password=512',
            'SELECT tps_id, tps_nombre FROM erp_tipos'
        ) AS t2(tps_id int, tps_nombre varchar)
        WHERE t2.tps_id = p.mp_a::int
        LIMIT 1
    )
-- Mapear marca por mar_id
LEFT JOIN marcas m
    ON m.nombre = (
        SELECT mar_descripcion FROM dblink(
            'dbname=altamira_legacy user=postgres password=512',
            'SELECT mar_id, mar_descripcion FROM erp_marcas'
        ) AS mr(mar_id int, mar_descripcion varchar)
        WHERE mr.mar_id = p.mar_id
        LIMIT 1
    )
ON CONFLICT (empresa_id, codigo) DO NOTHING;
