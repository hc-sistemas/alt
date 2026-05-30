<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoAprobacion extends Model
{
    public $timestamps = false;
    protected $table = 'tipos_aprobacion';

    // Schema real: id, codigo, descripcion, perfiles_autorizados, requiere_codigo, activo
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'perfiles_autorizados', 'requiere_codigo', 'activo'];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'requiere_codigo' => 'boolean',
        ];
    }

    public function getClaveAttribute(): string
    {
        return $this->attributes['codigo'] ?? '';
    }

    public function getNombreAttribute(): string
    {
        return $this->attributes['nombre'] ?? $this->attributes['descripcion'] ?? '';
    }
}
