<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaProducto extends Model
{
    protected $table = 'categorias_producto';
    public $timestamps = false;

    protected $fillable = ['nombre', 'categoria_padre_id', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(CategoriaProducto::class, 'categoria_padre_id');
    }

    public function scopeRaices($query)
    {
        return $query->whereNull('categoria_padre_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', true);
    }
}
