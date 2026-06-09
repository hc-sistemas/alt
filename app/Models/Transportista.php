<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transportista extends Model
{

    protected $table = 'transportistas';

    protected $fillable = [
        'identificacion',
        'razon_social',
        'placa',
        'email',
        'telefono',
        'direccion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function guiasRemision(): HasMany
    {
        return $this->hasMany(GuiaRemision::class, 'transportista_id');
    }
}
