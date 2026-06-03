<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoSerie extends Model
{
    protected $table = 'producto_series';

    const ESTADOS = ['disponible', 'vendido', 'reservado', 'defectuoso'];

    protected $fillable = [
        'producto_id',
        'bodega_id',
        'numero_serie',
        'estado',
        'doc_entrada_tipo',
        'doc_entrada_id',
        'doc_salida_tipo',
        'doc_salida_id',
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
