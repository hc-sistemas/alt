<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrasladoItem extends Model
{
    protected $table = 'traslado_items';

    protected $fillable = [
        'traslado_id',
        'producto_id',
        'cantidad_enviada',
        'cantidad_recibida',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_enviada'  => 'decimal:4',
            'cantidad_recibida' => 'decimal:4',
        ];
    }

    public function traslado(): BelongsTo
    {
        return $this->belongsTo(Traslado::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
