<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrasladoDetalle extends Model
{
    protected $table = 'traslado_detalles';
    public $timestamps = false;

    protected $fillable = [
        'traslado_id', 'producto_id', 'numero_serie',
        'cantidad_enviada', 'cantidad_recibida',
    ];

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(TrasladoBodega::class, 'traslado_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
