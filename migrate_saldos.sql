-- migrate_saldos.sql
-- Fuente : altamira_legacy.erp_i_movpt_total
-- Destino: altamira.inventario_saldos
-- Nota   : costo_promedio se toma de los ingresos ya migrados en inventario_movimientos

INSERT INTO inventario_saldos (
    producto_id,
    bodega_id,
    cantidad,
    costo_promedio,
    updated_at
)
SELECT
    p.id                        AS producto_id,
    b.id                        AS bodega_id,
    COALESCE(s.mvt_cant, 0)    AS cantidad,
    COALESCE(
        (SELECT AVG(im.costo_unitario)
         FROM inventario_movimientos im
         WHERE im.producto_id    = p.id
           AND im.tipo_movimiento = 'ingreso'
           AND im.costo_unitario  > 0
        ), 0
    )                           AS costo_promedio,
    NOW()
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    'SELECT s.pro_id,
            s.mvt_cant,
            s.cod_punto_emision,
            mp.mp_c AS codigo
     FROM erp_i_movpt_total s
     JOIN erp_mp mp ON mp.id = s.pro_id
     WHERE s.mvt_cant > 0
       AND mp.mp_c IS NOT NULL
       AND TRIM(mp.mp_c) <> ''''
') AS s(
    pro_id            integer,
    mvt_cant          double precision,
    cod_punto_emision integer,
    codigo            varchar
)
JOIN productos p ON p.codigo = s.codigo
JOIN bodegas   b ON b.id     = s.cod_punto_emision
ON CONFLICT (producto_id, bodega_id) DO UPDATE SET
    cantidad       = EXCLUDED.cantidad,
    costo_promedio = EXCLUDED.costo_promedio,
    updated_at     = NOW();
