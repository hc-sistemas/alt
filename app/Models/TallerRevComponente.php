<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerRevComponente extends Model
{
    const UPDATED_AT = null;

    protected $table = 'taller_rev_componentes';

    protected $fillable = [
        'ingreso_id',
        'nombre',
        'funciona',
        'accion',
        'descripcion',
        'costo',
    ];

    protected function casts(): array
    {
        return [
            'funciona' => 'boolean',
            'costo'    => 'float',
        ];
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(TallerIngreso::class, 'ingreso_id');
    }
}
