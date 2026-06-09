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
        'precio', 'descuento_max', 'vigencia_desde', 'vigencia_hasta',
    ];

    protected function casts(): array
    {
        return ['precio' => 'float', 'descuento_max' => 'float'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
