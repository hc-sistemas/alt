<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'factura_detalles';

    protected $fillable = [
        'factura_id',
        'producto_id',
        'codigo_producto',
        'descripcion',
        'unidad',
        'cantidad',
        'precio_unitario',
        'descuento_pct',
        'descuento_valor',
        'subtotal',
        'porcentaje_iva',
        'valor_iva',
        'valor_ice',
        'total',
        'numero_serie',
        'costo_unitario',
    ];

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
