<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProformaDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'proforma_detalles';

    protected $fillable = [
        'proforma_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento_pct',
        'subtotal',
        'porcentaje_iva',
        'total',
    ];

    public function proforma(): BelongsTo
    {
        return $this->belongsTo(Proforma::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
