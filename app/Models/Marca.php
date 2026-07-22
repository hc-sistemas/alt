<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Marca extends Model
{
    protected $table = 'marcas';
    public $timestamps = false;

    protected $fillable = ['nombre', 'logo', 'icono', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', true);
    }
}
