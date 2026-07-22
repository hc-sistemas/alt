INSERT INTO categorias_producto (nombre, estado)
SELECT DISTINCT t.tps_nombre, TRUE
FROM dblink(
    'dbname=altamira_legacy user=postgres password=512',
    'SELECT tps_id, tps_nombre, tps_siglas
     FROM erp_tipos
     WHERE tps_relacion = ''2'''
) AS t(tps_id int, tps_nombre varchar, tps_siglas varchar)
WHERE NOT EXISTS (
    SELECT 1 FROM categorias_producto WHERE nombre = t.tps_nombre
);
