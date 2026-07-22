<?php

namespace App\Services;

use App\Models\ListaPrecio;
use App\Models\Producto;

class DescuentoService
{
    /**
     * Único lugar que resuelve el % de descuento máximo permitido por
     * producto. Prioridad:
     *   1. listas_precio.descuento_max_promo (tipo PVP), solo si hoy está
     *      entre vigencia_desde y vigencia_hasta (inclusive) y no es null.
     *   2. listas_precio.descuento_max (tipo PVP, empresa activa).
     *   3. productos.descuento_maximo (fallback si no hay fila en listas_precio).
     *
     * @param array<int> $productoIds
     * @return array<int, float> descuento_max indexado por producto_id
     */
    public function mapaMaximosPermitidos(array $productoIds, int $empresaId): array
    {
        if (empty($productoIds)) {
            return [];
        }

        $hoy = now()->toDateString();

        $desdeListaPrecio = ListaPrecio::whereIn('producto_id', $productoIds)
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'PVP')
            ->get(['producto_id', 'descuento_max', 'descuento_max_promo', 'vigencia_desde', 'vigencia_hasta'])
            ->keyBy('producto_id');

        $desdeProducto = Producto::whereIn('id', $productoIds)
            ->pluck('descuento_maximo', 'id');

        $resultado = [];
        foreach ($productoIds as $productoId) {
            $lista = $desdeListaPrecio->get($productoId);

            if ($lista !== null
                && $lista->descuento_max_promo !== null
                && $lista->vigencia_desde !== null
                && $lista->vigencia_hasta !== null
                && $hoy >= $lista->vigencia_desde->toDateString()
                && $hoy <= $lista->vigencia_hasta->toDateString()
            ) {
                $resultado[$productoId] = (float) $lista->descuento_max_promo;
                continue;
            }

            $resultado[$productoId] = $lista !== null
                ? (float) $lista->descuento_max
                : (float) ($desdeProducto[$productoId] ?? 0);
        }

        return $resultado;
    }

    public function maximoPermitido(int $productoId, int $empresaId): float
    {
        return $this->mapaMaximosPermitidos([$productoId], $empresaId)[$productoId] ?? 0.0;
    }
}
