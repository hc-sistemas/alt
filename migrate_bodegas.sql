-- migrate_bodegas.sql
-- Insertar bodegas del sistema legacy y bodegas especiales del sistema nuevo

INSERT INTO bodegas (empresa_id, nombre, tipo, es_virtual, estado)
VALUES
    (1, 'BODEGA PRINCIPAL UIO', 'general', FALSE, TRUE),
    (1, 'BODEGA MUESTRA', 'general', FALSE, TRUE),
    (2, 'BODEGA IMPORT', 'importacion', FALSE, TRUE),
    (1, 'BODEGA RESERVA', 'reserva', TRUE, TRUE),
    (1, 'CUARENTENA/GARANTIAS', 'cuarentena', TRUE, TRUE),
    (1, 'BODEGA TALLER', 'taller', FALSE, TRUE)
ON CONFLICT DO NOTHING;
