#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Migración de inventario: escribe SQLs como UTF-8 sin BOM y los ejecuta.

Tablas destino (schema real altamira):
  inventario_movimientos: bodega_origen_id / bodega_destino_id, tipo_movimiento,
                          documento_numero, cantidad, observacion
  inventario_saldos:      producto_id, bodega_id, cantidad, costo_promedio
"""

import subprocess
import sys
import os

# ──────────────────────────────────────────────────────────────────────────────
# SQL 1 — migrate_kardex.sql
# ──────────────────────────────────────────────────────────────────────────────
KARDEX_SQL = """\
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
"""

# ──────────────────────────────────────────────────────────────────────────────
# SQL 2 — migrate_saldos.sql
# ──────────────────────────────────────────────────────────────────────────────
SALDOS_SQL = """\
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
"""

# ──────────────────────────────────────────────────────────────────────────────
# Helpers
# ──────────────────────────────────────────────────────────────────────────────

def write_utf8(path: str, content: str) -> None:
    """Escribe el archivo en UTF-8 sin BOM."""
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write(content)
    print(f"[ESCRITO] {path}")


def run_psql(sql_file: str, db: str = 'altamira', user: str = 'postgres') -> None:
    """Ejecuta un archivo SQL con psql; aborta en caso de error."""
    print(f"\n{'='*60}")
    print(f"[EJECUTANDO] {os.path.basename(sql_file)}")
    print(f"{'='*60}")
    result = subprocess.run(
        ['psql', '-U', user, '-d', db,
         '-v', 'ON_ERROR_STOP=1',
         '-f', sql_file],
        text=True,
        encoding='utf-8',
    )
    if result.returncode != 0:
        print(f"\n[ERROR] psql terminó con código {result.returncode}.")
        sys.exit(result.returncode)
    print(f"[OK] {os.path.basename(sql_file)} completado.")


def verify(db: str = 'altamira', user: str = 'postgres') -> None:
    """Muestra conteos finales."""
    print(f"\n{'='*60}")
    print("[VERIFICACIÓN FINAL]")
    print(f"{'='*60}")
    for table in ['inventario_movimientos', 'inventario_saldos']:
        subprocess.run(
            ['psql', '-U', user, '-d', db,
             '-c', f"SELECT COUNT(*) AS total_{table} FROM {table};"],
            text=True, encoding='utf-8',
        )


# ──────────────────────────────────────────────────────────────────────────────
# Main
# ──────────────────────────────────────────────────────────────────────────────

if __name__ == '__main__':
    base = os.path.dirname(os.path.abspath(__file__))

    kardex_path = os.path.join(base, 'migrate_kardex.sql')
    saldos_path = os.path.join(base, 'migrate_saldos.sql')

    # Paso 1: escribir archivos SQL en UTF-8 sin BOM
    write_utf8(kardex_path, KARDEX_SQL)
    write_utf8(saldos_path, SALDOS_SQL)
    print("\n[INFO] Archivos SQL escritos correctamente.")

    # Paso 2: kardex — puede tardar 5-10 minutos (~52 k registros)
    print("\n[INFO] Iniciando migración de movimientos (kardex)...")
    print("[INFO] Esto puede tardar entre 5 y 10 minutos. Por favor espera.")
    run_psql(kardex_path)

    # Paso 3: saldos
    print("\n[INFO] Iniciando migración de saldos...")
    run_psql(saldos_path)

    # Paso 4: verificar
    verify()
    print("\n[LISTO] Migración de inventario completada.")
