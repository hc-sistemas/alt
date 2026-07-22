<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoSerie extends Model
{
    protected $table = 'producto_series';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    const ESTADOS = ['disponible', 'vendido', 'reservado', 'garantia'];

    protected $fillable = [
        'producto_id', 'bodega_id', 'numero_serie', 'estado',
        'factura_compra_id', 'factura_venta_id',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    public function scopeDisponibles($query)
    {
        return $query->where('estado', 'disponible');
    }
}
