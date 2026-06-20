INSERT INTO marcas (nombre, estado)
SELECT
    mar_descripcion,
    CASE WHEN mar_estado = 1 THEN TRUE ELSE FALSE END
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    'SELECT mar_id, mar_descripcion, mar_estado FROM erp_marcas'
) AS m(mar_id int, mar_descripcion varchar, mar_estado int)
ON CONFLICT (nombre) DO NOTHING;
