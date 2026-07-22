<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerTipoEquipo extends Model
{
    protected $table = 'taller_tipos_equipo';

    protected $fillable = [
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function equipos(): HasMany
    {
        return $this->hasMany(TallerEquipo::class, 'tipo_id');
    }
}
