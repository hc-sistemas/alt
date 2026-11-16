<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id', 'producto_id', 'cuenta_id', 'descripcion',
        'cantidad', 'peso', 'precio_unitario', 'descuento',
        'subtotal', 'porcentaje_iva', 'valor_iva', 'total',
        'es_activo_fijo', 'activo_fijo_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'decimal:4',
            'peso'            => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento'       => 'decimal:4',
            'subtotal'        => 'decimal:4',
            'porcentaje_iva'  => 'decimal:2',
            'valor_iva'       => 'decimal:4',
            'total'           => 'decimal:4',
            'es_activo_fijo'  => 'boolean',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }

    /**
     * Costo unitario que entra al kárdex: subtotal de la línea (cantidad × precio − descuento) entre la cantidad, el mismo
     * valor que contabiliza el asiento de la compra. Con el precio bruto, un descuento dejaba el inventario del kárdex por
     * encima del mayor contable.
     */
    public static function costoUnitarioNeto(self $detalle): float
    {
        $cantidad = (float) $detalle->cantidad;
        if ($cantidad <= 0) {
            return (float) $detalle->precio_unitario;
        }
        $subtotal = $detalle->subtotal !== null
            ? (float) $detalle->subtotal
            : $cantidad * (float) $detalle->precio_unitario - (float) $detalle->descuento;

        return round(max($subtotal, 0) / $cantidad, 4);
    }
}
