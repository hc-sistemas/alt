<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListaPrecio extends Model
{
    protected $table = 'listas_precio';
    public $timestamps = false;

    const TIPOS = ['PVP', 'PVD'];

    protected $fillable = [
        'empresa_id', 'producto_id', 'tipo',
        'precio', 'descuento_max', 'descuento_max_promo', 'vigencia_desde', 'vigencia_hasta',
    ];

    protected function casts(): array
    {
        return [
            'precio'              => 'decimal:4',
            'descuento_max'       => 'decimal:2',
            'descuento_max_promo' => 'decimal:2',
            'vigencia_desde'      => 'date',
            'vigencia_hasta'      => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
