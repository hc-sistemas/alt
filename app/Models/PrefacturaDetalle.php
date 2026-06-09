<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrefacturaDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'prefactura_detalles';

    protected $fillable = [
        'prefactura_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total',
    ];

    public function prefactura(): BelongsTo
    {
        return $this->belongsTo(Prefactura::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
