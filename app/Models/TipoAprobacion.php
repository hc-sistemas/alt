<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoAprobacion extends Model
{
    public $timestamps = false;
    protected $table = 'tipos_aprobacion';

    // Schema real: id, nombre, clave, descripcion, activo
    protected $fillable = ['clave', 'nombre', 'descripcion', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
