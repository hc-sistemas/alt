<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── bodegas ──────────────────────────────────────────────────────────
        // activo  → estado  (bool)
        // agregar es_virtual (bool, default false)
        if (Schema::hasColumn('bodegas', 'activo') && !Schema::hasColumn('bodegas', 'estado')) {
            DB::statement('ALTER TABLE bodegas RENAME COLUMN activo TO estado');
        }
        if (!Schema::hasColumn('bodegas', 'es_virtual')) {
            DB::statement('ALTER TABLE bodegas ADD COLUMN es_virtual boolean NOT NULL DEFAULT false');
        }

        // ── categorias_producto ───────────────────────────────────────────────
        // activo     → estado          (bool)
        // parent_id  → categoria_padre_id  (fk)
        if (Schema::hasColumn('categorias_producto', 'activo') && !Schema::hasColumn('categorias_producto', 'estado')) {
            DB::statement('ALTER TABLE categorias_producto RENAME COLUMN activo TO estado');
        }
        if (Schema::hasColumn('categorias_producto', 'parent_id') && !Schema::hasColumn('categorias_producto', 'categoria_padre_id')) {
            DB::statement('ALTER TABLE categorias_producto RENAME COLUMN parent_id TO categoria_padre_id');
        }

        // ── marcas ────────────────────────────────────────────────────────────
        // activo → estado (bool)
        if (Schema::hasColumn('marcas', 'activo') && !Schema::hasColumn('marcas', 'estado')) {
            DB::statement('ALTER TABLE marcas RENAME COLUMN activo TO estado');
        }

        // ── productos ─────────────────────────────────────────────────────────
        // iva_porcentaje  → porcentaje_iva
        // ice_porcentaje  → porcentaje_ice
        // + tiene_ice, codigo_externo, ref_importacion
        // + cuenta_inventario, cuenta_costo_ventas, cuenta_ventas (nullable text)
        if (Schema::hasColumn('productos', 'iva_porcentaje') && !Schema::hasColumn('productos', 'porcentaje_iva')) {
            DB::statement('ALTER TABLE productos RENAME COLUMN iva_porcentaje TO porcentaje_iva');
        }
        if (Schema::hasColumn('productos', 'ice_porcentaje') && !Schema::hasColumn('productos', 'porcentaje_ice')) {
            DB::statement('ALTER TABLE productos RENAME COLUMN ice_porcentaje TO porcentaje_ice');
        }
        if (!Schema::hasColumn('productos', 'tiene_ice')) {
            DB::statement('ALTER TABLE productos ADD COLUMN tiene_ice boolean NOT NULL DEFAULT false');
        }
        if (!Schema::hasColumn('productos', 'codigo_externo')) {
            DB::statement('ALTER TABLE productos ADD COLUMN codigo_externo varchar(50) NULL');
        }
        if (!Schema::hasColumn('productos', 'ref_importacion')) {
            DB::statement('ALTER TABLE productos ADD COLUMN ref_importacion varchar(100) NULL');
        }
        // Las FK de cuentas existen como cuenta_inventario_id etc.; agregar alias texto para compatibilidad
        if (!Schema::hasColumn('productos', 'cuenta_inventario')) {
            DB::statement('ALTER TABLE productos ADD COLUMN cuenta_inventario varchar(20) NULL');
        }
        if (!Schema::hasColumn('productos', 'cuenta_costo_ventas')) {
            DB::statement('ALTER TABLE productos ADD COLUMN cuenta_costo_ventas varchar(20) NULL');
        }
        if (!Schema::hasColumn('productos', 'cuenta_ventas')) {
            DB::statement('ALTER TABLE productos ADD COLUMN cuenta_ventas varchar(20) NULL');
        }

        // ── activos_fijos ─────────────────────────────────────────────────────
        // valor_adquisicion → costo_adquisicion
        // vida_util_años    → vida_util_anios
        // valor_libro       → valor_en_libros
        // cuenta_activo_id  → cuenta_id
        if (Schema::hasColumn('activos_fijos', 'valor_adquisicion') && !Schema::hasColumn('activos_fijos', 'costo_adquisicion')) {
            DB::statement('ALTER TABLE activos_fijos RENAME COLUMN valor_adquisicion TO costo_adquisicion');
        }
        if (Schema::hasColumn('activos_fijos', 'vida_util_años') && !Schema::hasColumn('activos_fijos', 'vida_util_anios')) {
            DB::statement('ALTER TABLE activos_fijos RENAME COLUMN "vida_util_años" TO vida_util_anios');
        }
        if (Schema::hasColumn('activos_fijos', 'valor_libro') && !Schema::hasColumn('activos_fijos', 'valor_en_libros')) {
            DB::statement('ALTER TABLE activos_fijos RENAME COLUMN valor_libro TO valor_en_libros');
        }
        if (Schema::hasColumn('activos_fijos', 'cuenta_activo_id') && !Schema::hasColumn('activos_fijos', 'cuenta_id')) {
            DB::statement('ALTER TABLE activos_fijos RENAME COLUMN cuenta_activo_id TO cuenta_id');
        }
    }

    public function down(): void
    {
        // reverse — omitted for brevity; idempotent up() is what matters here
    }
};
