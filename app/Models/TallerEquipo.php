<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerEquipo extends Model
{
    protected $table = 'taller_equipos';

    protected $fillable = [
        'tipo_id',
        'marca',
        'modelo',
        'numero_serie',
        'color',
        'medida',
        'adicional',
        'observaciones',
        'estado',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TallerTipoEquipo::class, 'tipo_id');
    }

    public function ingresos(): HasMany
    {
        return $this->hasMany(TallerIngreso::class, 'equipo_id');
    }
}
