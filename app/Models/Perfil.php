<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Perfil extends Model
{
    public $timestamps = false;
    protected $table = 'perfiles';

    protected $fillable = ['nombre', 'descripcion', 'es_sistema', 'estado'];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class);
    }

    public function limiteDescuento(): HasMany
    {
        return $this->hasMany(LimiteDescuento::class);
    }
}
