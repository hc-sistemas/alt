<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecepcionDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'recepcion_detalles';

    protected $fillable = [
        'recepcion_id', 'compra_detalle_id', 'producto_id',
        'cantidad_esperada', 'cantidad_recibida', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_esperada' => 'decimal:4',
            'cantidad_recibida' => 'decimal:4',
        ];
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionBodega::class, 'recepcion_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function compraDetalle(): BelongsTo
    {
        return $this->belongsTo(CompraDetalle::class, 'compra_detalle_id');
    }
}
