<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use SoftDeletes;

    protected $table = 'productos';

    protected $fillable = [
        'empresa_id',
        'marca_id',
        'categoria_id',
        'bodega_default_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'unidad',
        'requiere_serie',
        'pvp',
        'pvd',
        'costo',
        'descuento_maximo',
        'iva_porcentaje',
        'ice_porcentaje',
        'stock_minimo',
        'stock_maximo',
        'cuenta_inventario_id',
        'cuenta_costo_id',
        'cuenta_ventas_id',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'requiere_serie'   => 'boolean',
            'estado'           => 'boolean',
            'pvp'              => 'decimal:4',
            'pvd'              => 'decimal:4',
            'costo'            => 'decimal:4',
            'descuento_maximo' => 'decimal:2',
            'iva_porcentaje'   => 'decimal:2',
            'ice_porcentaje'   => 'decimal:2',
            'stock_minimo'     => 'decimal:4',
            'stock_maximo'     => 'decimal:4',
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

    public function bodegaDefault(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_default_id');
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
