-- migrate_kardex.sql
-- Fuente : altamira_legacy.erp_i_mov_inv_pt
-- Destino: altamira.inventario_movimientos
-- Nota   : bodega_origen para egresos, bodega_destino para ingresos

-- Aborta si ya existe data para evitar duplicados
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM inventario_movimientos LIMIT 1) THEN
        RAISE EXCEPTION
            'inventario_movimientos ya tiene datos. '
            'TRUNCATE TABLE inventario_movimientos RESTART IDENTITY CASCADE '
            'si deseas re-migrar.';
    END IF;
END $$;

INSERT INTO inventario_movimientos (
    empresa_id,
    producto_id,
    bodega_origen_id,
    bodega_destino_id,
    tipo_movimiento,
    documento_numero,
    cantidad,
    costo_unitario,
    costo_total,
    numero_serie,
    fecha,
    observacion,
    created_at
)
SELECT
    b.empresa_id,
    p.id                                                                AS producto_id,
    CASE WHEN m.trs_operacion = 1 THEN b.id ELSE NULL END              AS bodega_origen_id,
    CASE WHEN m.trs_operacion = 0 THEN b.id ELSE NULL END              AS bodega_destino_id,
    CASE WHEN m.trs_operacion = 1 THEN 'egreso' ELSE 'ingreso' END     AS tipo_movimiento,
    LEFT(COALESCE(m.mov_documento, ''), 50)                            AS documento_numero,
    ABS(m.mov_cantidad)                                                AS cantidad,
    COALESCE(m.mov_val_unit, 0)                                        AS costo_unitario,
    COALESCE(m.mov_val_tot, 0)                                         AS costo_total,
    NULLIF(TRIM(COALESCE(m.mov_serie, '')), '')                        AS numero_serie,
    COALESCE(m.mov_fecha_trans, m.mov_fecha_registro, CURRENT_DATE)    AS fecha,
    LEFT(COALESCE(m.trs_descripcion, ''), 300)                         AS observacion,
    NOW()                                                              AS created_at
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    'SELECT m.mov_id,
            m.pro_id,
            m.bod_id,
            m.mov_fecha_trans,
            m.mov_fecha_registro,
            m.mov_cantidad,
            m.mov_val_unit,
            m.mov_val_tot,
            m.mov_documento,
            m.mov_serie,
            t.trs_operacion,
            t.trs_descripcion,
            mp.mp_c AS codigo
     FROM erp_i_mov_inv_pt m
     JOIN erp_transacciones t ON m.trs_id = t.trs_id
     JOIN erp_mp mp ON m.pro_id = mp.id
     WHERE m.mov_estado = 1
       AND m.bod_id IS NOT NULL
       AND mp.mp_c IS NOT NULL
       AND TRIM(mp.mp_c) <> ''''
') AS m(
    mov_id             integer,
    pro_id             integer,
    bod_id             integer,
    mov_fecha_trans    date,
    mov_fecha_registro date,
    mov_cantidad       numeric,
    mov_val_unit       double precision,
    mov_val_tot        double precision,
    mov_documento      varchar,
    mov_serie          varchar,
    trs_operacion      integer,
    trs_descripcion    varchar,
    codigo             varchar
)
JOIN productos p ON p.codigo = m.codigo
JOIN bodegas   b ON b.id     = m.bod_id;
