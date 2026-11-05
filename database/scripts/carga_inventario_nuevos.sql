-- Carga de los productos nuevos del Excel «INVENTARIO ALTAMIRA JUNTO FINAL.xlsx» (módulo A, tarea 4).
-- GENERADO PARA REVISIÓN — NO EJECUTADO. Solo INSERTA productos que no existen; no modifica ningún producto existente.
-- Corre dentro de una transacción que termina en ROLLBACK: para aplicar de verdad, cambie la última línea por COMMIT.
-- No carga stock (inventario_saldos): el Excel trae cantidades, pero la carga de existencias por bodega no está definida.
-- Descuento máximo en listas_precio queda en 0 (el Excel no trae descuento). marca_id y categoria_id se infieren de los productos ya cargados.
-- EXCLUIDO fila Excel 1652: el campo CODIGO trae «LAS 2 CAJAS VACIAS» (parece un texto, no un código). Corregir en el Excel y agregar a mano.
BEGIN;

-- fila Excel 1648: REP.000309 | marca 'EASOUND' -> marca_id 152 | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 9, 'REP.000309', 'DIAFRAGMA DRIVER XD15P', 'ORIGINAL', 'UNIDAD', 'producto', 0, 79, 69, 79, 69, 0, 15, 'DIAFRAGMA XD15P', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000309');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 79, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000309'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 69, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000309'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 79, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000309'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 69, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000309'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1649: CBL.000111 | marca 'BESSERSOUND' -> marca_id 152 | prefijo CBL -> categoria_id 4 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 4, 'CBL.000111', 'CABLE XLR F / XLR M 12M PREMIUM', 'CONECTORES Y CABLE PREMIUM 0% OXIGENO', 'UNIDAD', 'producto', 0, 25.43, 19.57, 25.43, 19.57, 0, 15, 'C13606', 'BESSERSOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'CBL.000111');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 25.43, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CBL.000111'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 19.57, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CBL.000111'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 25.43, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CBL.000111'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 19.57, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CBL.000111'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1650: SON.000314 | marca 'DEZIBEL' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000314', 'DM-9 MICROFONO', 'DINAMIC CARDEOIDE', 'UNIDAD', 'producto', 0, 59, 40, 59, 40, 0, 15, 'DM-9', 'DEZIBEL', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000314');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 59, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 40, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 59, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 40, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1651: SON.000315 | marca 'DEZIBEL' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000315', 'DM-226 MICROFONO', 'CARDEIODE INSTRUMENTAL', 'UNIDAD', 'producto', 0, 55, 38, 55, 38, 0, 15, 'DM-226', 'DEZIBEL', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000315');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 55, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 38, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 55, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 38, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1653: IML.000172 | marca 'EALIGHT' -> marca_id 152 | prefijo IML -> categoria_id 2 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 2, 'IML.000172', 'SLIM PAR 20X10 4IN1', 'PLASTIC DMX', 'UNIDAD', 'producto', 0, 45, 35, 45, 35, 0, 15, 'SLIMPAR 20W', 'EALIGHT', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000172');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 45, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000172'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 35, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000172'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 45, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000172'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 35, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000172'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1654: DJS.000236 | marca 'EASOUND' -> marca_id 152 | prefijo DJS -> categoria_id 1 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 1, 'DJS.000236', 'DISCO MOVIL AMPLI ILUMINACION', 'EVENTO ALQUILER', 'UNIDAD', 'producto', 0, 340, 150, 340, 150, 0, 15, 'EVENTO CIRCULO MILITAR', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000236');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 340, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000236'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 150, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000236'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 340, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000236'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 150, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000236'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1655: IML.000173 | marca 'EALIGHT' -> marca_id 152 | prefijo IML -> categoria_id 2 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 2, 'IML.000173', 'HELICOPTERO', '4 LED 10W LASER R150MW VERDE 100MW FLASH 5W', 'UNIDAD', 'producto', 0, 180, 120, 180, 120, 0, 15, 'KDB-264', 'EALIGHT', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000173');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 180, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000173'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 120, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000173'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 180, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000173'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 120, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000173'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1656: IML.000174 | marca 'EALIGHT' -> marca_id 152 | prefijo IML -> categoria_id 2 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 2, 'IML.000174', 'ZOOM WASH', '36LEDS X 10W 4IN1 ZOOM DIGITAL', 'UNIDAD', 'producto', 0, 240, 180, 240, 180, 0, 15, 'KDB-51', 'EALIGHT', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000174');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 240, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000174'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 180, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000174'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 240, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000174'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 180, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000174'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1657: REP.000311 | marca 'FEUR' -> marca_id 152 | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 9, 'REP.000311', 'REPUESTO PARLANTE 12INCH LINE ARRAY', '3INCH BOBINA 1000W RMS', 'UNIDAD', 'producto', 0, 75, 69, 75, 69, 0, 15, 'REPUESTO WOOFER 12INCH FEUR', 'FEUR', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000311');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 75, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000311'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 69, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000311'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 75, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000311'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 69, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000311'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1658: REP.000312 | marca 'ELECTROVOICE' -> marca_id NULL (sin equivalente) | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, NULL, 9, 'REP.000312', 'REP DRIVER EV ELX112 , 115 , 215 ,112P 115P', 'ORIGINAL', 'UNIDAD', 'producto', 0, 75, 65, 75, 65, 0, 15, 'FU-036', 'ELECTROVOICE', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000312');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 75, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000312'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 65, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000312'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 75, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000312'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 65, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000312'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1659: REP.000313 | marca 'FEUR' -> marca_id 152 | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 9, 'REP.000313', 'REPUESTO PARLANTE 15INCH FU-PRO1590', 'WOOFER ORIGINAL', 'UNIDAD', 'producto', 0, 85, 70.46, 85, 70.46, 0, 15, 'WOOFER PRO1590', 'FEUR', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000313');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 85, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000313'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 70.46, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000313'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 85, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000313'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 70.46, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000313'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1660: REP.000314 | marca 'ELECTROVOICE' -> marca_id NULL (sin equivalente) | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, NULL, 9, 'REP.000314', 'REP DIAFRAGMA EV ELX112 , 115 ,215,112P,115P', 'ORIGINAL', 'UNIDAD', 'producto', 0, 45, 30, 45, 30, 0, 15, 'FU-VCEV', 'ELECTROVOICE', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000314');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 45, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 30, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 45, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 30, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000314'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1661: SON.000316 | marca 'FEUR' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000316', 'FU-VR12', '12INCH MADERA 1800W 133DB MAX SPL', 'UNIDAD', 'producto', 0, 650, 510, 650, 510, 0, 15, 'FU-VR15 VR PRO SERIES', 'FEUR', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000316');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 650, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 510, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 650, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 510, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1662: REP.000315 | marca 'FEUR' -> marca_id 152 | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 9, 'REP.000315', 'REPUESTO DSP FU-PRO1590', 'ORIGINAL', 'UNIDAD', 'producto', 0, 89, 79, 89, 79, 0, 15, 'DSP FU-PRO1590', 'FEUR', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000315');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 89, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 79, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 89, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 79, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000315'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1663: REP.000316 | marca 'FEUR' -> marca_id 152 | prefijo REP -> categoria_id 9 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 9, 'REP.000316', 'REPUESTO AMPLIFICADOR FU-PRO1590', 'ORIGINAL', 'UNIDAD', 'producto', 0, 99, 85, 99, 85, 0, 15, 'AMPLIFICADOR FU-PRO1590', 'FEUR', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000316');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 99, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 85, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 99, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 85, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'REP.000316'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1664: IML.000175 | marca 'ROHDE' -> marca_id NULL (sin equivalente) | prefijo IML -> categoria_id 2 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, NULL, 2, 'IML.000175', 'BEAM 295W 10R 2IN1', 'OSRAM ARCO IRIS', 'UNIDAD', 'producto', 0, 978.27, 850, 978.27, 850, 0, 15, 'ROHDE BEAM 295W', 'ROHDE', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000175');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 978.27, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000175'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 850, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000175'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 978.27, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000175'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 850, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'IML.000175'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1665: DJS.000237 | marca 'DENO DJ' -> marca_id 152 | prefijo DJS -> categoria_id 1 | IVA 0% | REVISAR PRECIOS (valor sospechoso en el Excel)
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 1, 'DJS.000237', 'DENON PRIME 4 PLUS + ESTUCHE', 'CONTROLADOR 4CH AUTONOMO', 'UNIDAD', 'producto', 0, 1, 1, 1, 1, 0, 0, 'DENON PRIME 4 +', 'DENO DJ', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000237');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000237'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000237'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000237'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000237'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1666: DJS.000238 | marca 'EASOUND' -> marca_id 152 | prefijo DJS -> categoria_id 1 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 1, 'DJS.000238', 'HORA EXTRA DISCO MOVIL AMPLI ILUMINACION', 'ADICIONAL', 'UNIDAD', 'producto', 0, 20, 20, 20, 20, 0, 15, 'HORA EXTRA', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000238');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 20, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000238'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 20, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000238'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 20, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000238'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 20, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000238'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1667: ACC.000586 | marca 'EALIGHT' -> marca_id 152 | prefijo ACC -> categoria_id 3 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 3, 'ACC.000586', 'LIQUIDO BURBUJAS HIGH', 'ORIGINAL', 'UNIDAD', 'producto', 0, 16, 13, 16, 13, 0, 15, 'WILY ORIGINAL', 'EALIGHT', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'ACC.000586');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 16, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'ACC.000586'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 13, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'ACC.000586'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 16, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'ACC.000586'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 13, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'ACC.000586'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1668: SON.000317 | marca 'EASOUND' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000317', 'ALQUILER TARIMA', 'ORIGINAL', 'UNIDAD', 'producto', 0, 900, 700, 900, 700, 0, 15, 'ALQUILER TARIMA PARA EVENTO', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000317');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 900, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000317'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 700, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000317'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 900, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000317'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 700, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000317'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1669: DJS.000239 | marca 'EASOUND' -> marca_id 152 | prefijo DJS -> categoria_id 1 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 1, 'DJS.000239', 'ALQUILER SONIDO EVENTO', 'EVENTO ALQUILER', 'UNIDAD', 'producto', 0, 295.65, 275, 295.65, 275, 0, 15, 'ALQUILER SONIDO', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000239');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 295.65, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000239'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 275, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000239'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 295.65, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000239'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 275, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000239'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1670: SON.000318 | marca 'EASOUND' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000318', 'UHF-3000 MIC DUAL', 'FQ 650 - 690 MHZ', 'UNIDAD', 'producto', 0, 173.85, 150, 173.85, 150, 0, 15, 'UHF-3000', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000318');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 173.85, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000318'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 150, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000318'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 173.85, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000318'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 150, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000318'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1671: CMP.000026 | marca 'APPLE' -> marca_id 152 | prefijo CMP -> categoria_id 8 | IVA 0% | REVISAR PRECIOS (valor sospechoso en el Excel)
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 8, 'CMP.000026', 'MACBOOK PRO M3', '8GB RAM 1000GB SSD HDMI', 'UNIDAD', 'producto', 0, 1, 1, 1, 1, 0, 0, 'MACBOOK PRO DJ', 'APPLE', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'CMP.000026');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CMP.000026'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CMP.000026'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CMP.000026'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'CMP.000026'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1672: DJS.000240 | marca 'DENON DJ' -> marca_id 152 | prefijo DJS -> categoria_id 1 | IVA 15% | IVA del Excel no es válido (50%), se usa 15 | REVISAR PRECIOS (valor sospechoso en el Excel)
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 1, 'DJS.000240', 'PRIME 4 G2', 'CONTROLADOR AUTONOMO 4CH SERATO VIRTUAL', 'UNIDAD', 'producto', 0, 3, 2, 3, 2, 0, 15, 'DENON DJ PRIME 4 G2', 'DENON DJ', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000240');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 3, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000240'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 2, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000240'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 3, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000240'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 2, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'DJS.000240'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1673: SON.000319 | marca 'PRODG' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15% | IVA del Excel no es válido (-100%), se usa 15 | REVISAR PRECIOS (valor sospechoso en el Excel)
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000319', 'AVIATOR S115A', 'LAVOCE ITALIANA 15” 800 W RMS 132DB SPL', 'UNIDAD', 'producto', 0, 1, 895, 1, 895, 0, 15, 'AVIATOR S115A ESPAñA', 'PRODG', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000319');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000319'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 895, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000319'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 1, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000319'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 895, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000319'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- fila Excel 1674: SON.000320 | marca 'EASOUND' -> marca_id 152 | prefijo SON -> categoria_id 5 | IVA 15%
INSERT INTO productos (empresa_id, marca_id, categoria_id, codigo, nombre, descripcion, unidad, tipo, costo, pvp, pvd, pvp_original, pvd_original, descuento_maximo, porcentaje_iva, ref_importacion, marca_fabricante, estado, created_at, updated_at)
SELECT 1, 152, 5, 'SON.000320', 'FORTE 110A', '1500W RMS 134DB SPL NEO DRIVER 2INCH', 'UNIDAD', 'producto', 0, 750, 600, 750, 600, 0, 15, 'FORTE 110A NOV 2026', 'EASOUND', true, now(), now()
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000320');
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVP', 750, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000320'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 1, id, 'PVD', 600, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000320'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVP', 750, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000320'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;
INSERT INTO listas_precio (empresa_id, producto_id, tipo, precio, descuento_max)
SELECT 2, id, 'PVD', 600, 0 FROM productos WHERE empresa_id = 1 AND codigo = 'SON.000320'
ON CONFLICT (empresa_id, producto_id, tipo) DO NOTHING;

-- Verificación: debe dar 26 filas (y 104 filas nuevas en listas_precio).
SELECT count(*) AS productos_nuevos FROM productos WHERE codigo IN ('REP.000309','CBL.000111','SON.000314','SON.000315','IML.000172','DJS.000236','IML.000173','IML.000174','REP.000311','REP.000312','REP.000313','REP.000314','SON.000316','REP.000315','REP.000316','IML.000175','DJS.000237','DJS.000238','ACC.000586','SON.000317','DJS.000239','SON.000318','CMP.000026','DJS.000240','SON.000319','SON.000320');

ROLLBACK; -- cambiar por COMMIT; tras revisar
