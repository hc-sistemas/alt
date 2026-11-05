-- ============================================================================
-- Borrado de datos de prueba (módulo A, tarea 4) — LISTAS A y B
-- ============================================================================
-- GENERADO PARA REVISIÓN — NO EJECUTADO.
--
-- Corre dentro de una transacción que termina en ROLLBACK: aunque se ejecute
-- por error no guarda nada. Para aplicarlo de verdad, cambie la última línea
-- por COMMIT. Antes de aplicarlo en cualquier BD: respaldar (pg_dump).
--
-- Solo para la BD `altamira`. NO tocar `lideres` ni `sae_alt` (Postgres compartido).
--
-- Qué hace, en este orden:
--   0. Guarda: aborta si la BD no tiene exactamente los datos de prueba esperados.
--   1. (Lista A) borra la factura de prueba y sus hijos.
--   2. (Lista A) borra los traslados de prueba y sus detalles.
--   3. (Lista B) revierte el efecto de los movimientos de prueba en inventario_saldos.
--   4. (Lista A) borra los 16 movimientos de inventario de prueba.
--   5. (Lista A) borra la importación de prueba y la notificación de prueba.
--   6. Verificación final.
--
-- NO incluye (lista C, a decidir por el usuario): secuencial de facturas,
-- proveedores/transportistas, logs de auditoría, ejercicio contable,
-- usuarios de prueba, clientes del 22/07, saldos sin movimiento asociado.
-- ============================================================================

BEGIN;

-- ---------------------------------------------------------------------------
-- 0. Guarda: solo continúa si los datos son exactamente los esperados
-- ---------------------------------------------------------------------------
DO $$
DECLARE
    n integer;
BEGIN
    IF (SELECT array_agg(id ORDER BY id) FROM facturas) IS DISTINCT FROM ARRAY[1]::bigint[] THEN
        RAISE EXCEPTION 'Se esperaba una sola factura (id 1).';
    END IF;
    IF (SELECT array_agg(id ORDER BY id) FROM traslados_bodega) IS DISTINCT FROM ARRAY[3,4]::bigint[] THEN
        RAISE EXCEPTION 'Se esperaban solo los traslados 3 y 4.';
    END IF;
    IF (SELECT array_agg(id ORDER BY id) FROM inventario_movimientos)
        IS DISTINCT FROM ARRAY[5,6,7,8,9,10,11,12,13,14,15,16,17,24,25,26]::bigint[] THEN
        RAISE EXCEPTION 'Los movimientos de inventario no son exactamente los 16 de prueba (ids 5-17, 24-26).';
    END IF;
    IF (SELECT array_agg(id ORDER BY id) FROM importaciones) IS DISTINCT FROM ARRAY[2]::bigint[]
       OR (SELECT nombre FROM importaciones WHERE id = 2) <> 'IMP TEST' THEN
        RAISE EXCEPTION 'Se esperaba solo la importación 2 «IMP TEST».';
    END IF;
    IF (SELECT count(*) FROM notificaciones) <> 1
       OR (SELECT titulo FROM notificaciones WHERE id = 1) NOT LIKE 'Documento sin asiento%' THEN
        RAISE EXCEPTION 'Se esperaba solo la notificación 1 «Documento sin asiento contable».';
    END IF;

    -- Nada más debe depender de lo que se va a borrar.
    SELECT (SELECT count(*) FROM notas_credito WHERE factura_id = 1)
         + (SELECT count(*) FROM taller_ordenes_trabajo WHERE factura_id = 1)
         + (SELECT count(*) FROM compras WHERE importacion_id = 2)
         + (SELECT count(*) FROM anticipos_proveedores WHERE importacion_id = 2)
      INTO n;
    IF n > 0 THEN
        RAISE EXCEPTION 'Hay % registros que dependen de la factura 1 o de la importación 2; revisar antes de borrar.', n;
    END IF;
END $$;

-- Estado ANTES (para comparar)
SELECT 'antes' AS momento,
       (SELECT count(*) FROM facturas)                AS facturas,
       (SELECT count(*) FROM traslados_bodega)        AS traslados,
       (SELECT count(*) FROM inventario_movimientos)  AS movimientos,
       (SELECT count(*) FROM importaciones)           AS importaciones,
       (SELECT count(*) FROM notificaciones)          AS notificaciones,
       (SELECT count(*) FROM inventario_saldos)       AS saldos,
       (SELECT round(sum(stock_actual), 4) FROM inventario_saldos)       AS stock_total,
       (SELECT round(sum(cantidad_reservada), 4) FROM inventario_saldos) AS reservado_total;

-- ---------------------------------------------------------------------------
-- 1. Lista A — factura de prueba (hijos primero)
-- ---------------------------------------------------------------------------
DELETE FROM factura_pagos    WHERE factura_id = 1;
DELETE FROM factura_detalles WHERE factura_id = 1;
DELETE FROM facturas         WHERE id = 1;

-- ---------------------------------------------------------------------------
-- 2. Lista A — traslados de prueba (detalles primero)
-- ---------------------------------------------------------------------------
DELETE FROM traslado_detalles WHERE traslado_id IN (3, 4);
DELETE FROM traslados_bodega  WHERE id IN (3, 4);

-- ---------------------------------------------------------------------------
-- 3. Lista B — revertir el efecto de los movimientos en inventario_saldos.
--    Se calcula desde los propios movimientos (que aún existen):
--      salida  -> el stock vuelve a subir      (+cantidad)
--      entrada -> el stock vuelve a bajar      (-cantidad)
--      reserva -> se libera la reserva         (-cantidad en cantidad_reservada)
-- ---------------------------------------------------------------------------
CREATE TEMP TABLE _delta_saldos ON COMMIT DROP AS
SELECT producto_id, bodega_id,
       sum(CASE tipo WHEN 'salida'  THEN  cantidad
                     WHEN 'entrada' THEN -cantidad ELSE 0 END) AS d_stock,
       sum(CASE tipo WHEN 'reserva' THEN -cantidad ELSE 0 END) AS d_reserva
FROM inventario_movimientos
WHERE id IN (5,6,7,8,9,10,11,12,13,14,15,16,17,24,25,26)
GROUP BY producto_id, bodega_id;

-- Detalle de lo que se va a ajustar (antes del ajuste)
SELECT p.codigo, s.bodega_id, s.stock_actual AS stock_hoy, s.cantidad_reservada AS reservado_hoy,
       d.d_stock, d.d_reserva,
       s.stock_actual + d.d_stock AS stock_quedara,
       s.cantidad_reservada + d.d_reserva AS reservado_quedara
FROM _delta_saldos d
JOIN inventario_saldos s ON s.producto_id = d.producto_id AND s.bodega_id = d.bodega_id
JOIN productos p ON p.id = d.producto_id
ORDER BY p.codigo, s.bodega_id;

UPDATE inventario_saldos s
SET stock_actual       = s.stock_actual + d.d_stock,
    cantidad_reservada = s.cantidad_reservada + d.d_reserva,
    updated_at         = now()
FROM _delta_saldos d
WHERE s.producto_id = d.producto_id AND s.bodega_id = d.bodega_id;

-- Los ajustes no deben dejar stock ni reserva negativos
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM inventario_saldos s
               JOIN _delta_saldos d ON d.producto_id = s.producto_id AND d.bodega_id = s.bodega_id
               WHERE s.stock_actual < 0 OR s.cantidad_reservada < 0) THEN
        RAISE EXCEPTION 'El ajuste dejaría stock o reserva negativos; revisar.';
    END IF;
END $$;

-- Filas que quedaron en cero y que solo existían por un movimiento de prueba
-- (la primera entrada del par producto/bodega partió de stock 0)
DELETE FROM inventario_saldos s
USING _delta_saldos d
WHERE s.producto_id = d.producto_id AND s.bodega_id = d.bodega_id
  AND s.stock_actual = 0 AND s.cantidad_reservada = 0
  AND EXISTS (
      SELECT 1 FROM inventario_movimientos m
      WHERE m.producto_id = s.producto_id AND m.bodega_id = s.bodega_id
        AND m.id IN (5,6,7,8,9,10,11,12,13,14,15,16,17,24,25,26)
        AND m.tipo = 'entrada' AND m.stock_anterior = 0
  );

-- ---------------------------------------------------------------------------
-- 4. Lista A — movimientos de inventario de prueba
-- ---------------------------------------------------------------------------
DELETE FROM inventario_movimientos WHERE id IN (5,6,7,8,9,10,11,12,13,14,15,16,17,24,25,26);

-- ---------------------------------------------------------------------------
-- 5. Lista A — importación y notificación de prueba
-- ---------------------------------------------------------------------------
DELETE FROM importaciones  WHERE id = 2 AND nombre = 'IMP TEST';
DELETE FROM notificaciones WHERE id = 1;

-- ---------------------------------------------------------------------------
-- 6. Verificación final
-- ---------------------------------------------------------------------------
SELECT 'despues' AS momento,
       (SELECT count(*) FROM facturas)                AS facturas,
       (SELECT count(*) FROM traslados_bodega)        AS traslados,
       (SELECT count(*) FROM inventario_movimientos)  AS movimientos,
       (SELECT count(*) FROM importaciones)           AS importaciones,
       (SELECT count(*) FROM notificaciones)          AS notificaciones,
       (SELECT count(*) FROM inventario_saldos)       AS saldos,
       (SELECT round(sum(stock_actual), 4) FROM inventario_saldos)       AS stock_total,
       (SELECT round(sum(cantidad_reservada), 4) FROM inventario_saldos) AS reservado_total;

-- Esperado: facturas, traslados, movimientos, importaciones y notificaciones en 0;
-- reservado_total en 0 y stock_total igual al de «antes» menos lo que entró por
-- los ajustes y traslados de prueba (los traslados solo mueven entre bodegas).

ROLLBACK;  -- cambiar por COMMIT; tras revisar
