<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Modulo extends Model
{
    protected $table = 'modulos';

    // El schema real usa 'codigo' no 'clave', y tiene 'icono', 'orden'
    protected $fillable = ['nombre', 'codigo', 'clave', 'icono', 'orden', 'padre_id', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    // El schema legacy usa 'codigo', el ERP usa 'clave' — soportar ambos
    public function getClaveAttribute(): ?string
    {
        return $this->attributes['clave'] ?? $this->attributes['codigo'] ?? null;
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'padre_id');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class);
    }
}
