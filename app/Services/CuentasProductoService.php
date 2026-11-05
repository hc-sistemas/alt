<?php

namespace App\Services;

use App\Models\CategoriaProducto;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas de inventario, venta y costo de un producto según su categoría o subcategoría
 * (01 §6, PC-CAT-01 a PC-CAT-20). Lee la tabla `subcategoria_cuentas` y NO toca `productos`.
 *
 * Reglas (PC-CAT-R01 a R05):
 *  - si el producto está en una subcategoría, esa define las tres cuentas;
 *  - si su categoría no tiene subcategorías, las define la categoría;
 *  - si su categoría SÍ tiene subcategorías pero el producto sigue en la categoría, falta
 *    elegir la subcategoría: no hay cuentas todavía (no se asume «sin clasificar»).
 */
class CuentasProductoService
{
    /** @return array{codigo:string, inventario:int, venta:?int, costo:int}|null */
    public function paraCategoria(?int $categoriaId): ?array
    {
        if (!$categoriaId) {
            return null;
        }

        $fila = DB::table('subcategoria_cuentas')->where('categoria_producto_id', $categoriaId)->where('estado', true)->first();
        if (!$fila) {
            return null;
        }

        return [
            'codigo'     => $fila->codigo,
            'inventario' => (int) $fila->cuenta_inventario_id,
            'venta'      => $fila->cuenta_venta_id ? (int) $fila->cuenta_venta_id : null, // null: regalos, no se venden
            'costo'      => (int) $fila->cuenta_costo_id,
        ];
    }

    /** @return array{codigo:string, inventario:int, venta:?int, costo:int}|null */
    public function paraProducto(int $productoId): ?array
    {
        return $this->paraCategoria(DB::table('productos')->where('id', $productoId)->value('categoria_id'));
    }

    /** ¿La categoría tiene subcategorías (y por lo tanto el producto debe elegir una)? */
    public function tieneSubcategorias(int $categoriaId): bool
    {
        return CategoriaProducto::where('categoria_padre_id', $categoriaId)->where('estado', true)->exists();
    }

    /**
     * Estado de clasificación de un producto según su categoría:
     * ok | falta_subcategoria | sin_categoria | sin_cuentas.
     */
    public function estado(?int $categoriaId): string
    {
        if (!$categoriaId) {
            return 'sin_categoria';
        }
        if ($this->tieneSubcategorias($categoriaId)) {
            return 'falta_subcategoria';
        }

        return $this->paraCategoria($categoriaId) ? 'ok' : 'sin_cuentas';
    }
}
