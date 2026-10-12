<?php

namespace App\Services;

use App\Models\ListaPrecio;

class DescuentoService
{
    /**
     * Único lugar que resuelve el % de descuento máximo permitido por
     * producto. La fuente es SOLO la lista de precios de la empresa activa
     * (`listas_precio`); `productos.descuento_maximo` ya no se consulta.
     * Prioridad:
     *   1. listas_precio.descuento_max_promo (solo tipo PVP), si hoy está
     *      entre vigencia_desde y vigencia_hasta (inclusive) y no es null.
     *   2. listas_precio.descuento_max del $tipo pedido (PVP o PVD).
     *   3. 0 si el producto no tiene fila en la lista de precios.
     *
     * @param array<int> $productoIds
     * @param string $tipo 'PVP' (por defecto) o 'PVD'
     * @return array<int, float> descuento_max indexado por producto_id
     */
    public function mapaMaximosPermitidos(array $productoIds, int $empresaId, string $tipo = 'PVP'): array
    {
        if (empty($productoIds)) {
            return [];
        }

        $hoy = now()->toDateString();

        $desdeListaPrecio = ListaPrecio::whereIn('producto_id', $productoIds)
            ->where('empresa_id', $empresaId)
            ->where('tipo', $tipo)
            ->get(['producto_id', 'descuento_max', 'descuento_max_promo', 'vigencia_desde', 'vigencia_hasta'])
            ->keyBy('producto_id');

        $resultado = [];
        foreach ($productoIds as $productoId) {
            $lista = $desdeListaPrecio->get($productoId);

            if ($lista !== null
                && $tipo === 'PVP'
                && $lista->descuento_max_promo !== null
                && $lista->vigencia_desde !== null
                && $lista->vigencia_hasta !== null
                && $hoy >= $lista->vigencia_desde->toDateString()
                && $hoy <= $lista->vigencia_hasta->toDateString()
            ) {
                $resultado[$productoId] = (float) $lista->descuento_max_promo;
                continue;
            }

            $resultado[$productoId] = $lista !== null ? (float) $lista->descuento_max : 0.0;
        }

        return $resultado;
    }

    public function maximoPermitido(int $productoId, int $empresaId, string $tipo = 'PVP'): float
    {
        return $this->mapaMaximosPermitidos([$productoId], $empresaId, $tipo)[$productoId] ?? 0.0;
    }
}
