<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuiaRemisionDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'guia_remision_detalles';

    protected $fillable = [
        'guia_id',
        'producto_id',
        'descripcion',
        'cantidad',
        'numero_serie',
    ];

    public function guia(): BelongsTo
    {
        return $this->belongsTo(GuiaRemision::class, 'guia_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
