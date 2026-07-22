<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroCosto extends Model
{
    protected $table = 'centros_costo';

    protected $fillable = [
        'empresa_id', 'nombre', 'codigo', 'tipo', 'es_taller', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'es_taller' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }
}
