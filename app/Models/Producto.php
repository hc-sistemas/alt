<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    protected $table = 'productos';

    protected $fillable = [
        'empresa_id', 'marca_id', 'categoria_id',
        'codigo', 'codigo_externo', 'nombre', 'descripcion', 'unidad', 'tipo',
        'requiere_serie', 'costo', 'pvp', 'pvd', 'descuento_maximo',
        'porcentaje_iva', 'tiene_ice', 'porcentaje_ice',
        'stock_minimo', 'stock_maximo',
        'cuenta_inventario', 'cuenta_costo_ventas', 'cuenta_ventas',
        'ref_importacion', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado'         => 'boolean',
            'requiere_serie' => 'boolean',
            'tiene_ice'      => 'boolean',
            'costo'          => 'float',
            'pvp'            => 'float',
            'pvd'            => 'float',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_id');
    }

    public function series(): HasMany
    {
        return $this->hasMany(ProductoSerie::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function scopePorEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeRequiereSerie($query)
    {
        return $query->where('requiere_serie', true);
    }
}
