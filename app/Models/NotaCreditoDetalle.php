<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotaCreditoDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'nota_credito_detalles';

    protected $fillable = [
        'nota_credito_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'total',
        'bodega_destino_id',
        'numero_serie',
    ];

    public function notaCredito(): BelongsTo
    {
        return $this->belongsTo(NotaCredito::class, 'nota_credito_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
