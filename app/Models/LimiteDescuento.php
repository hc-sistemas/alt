<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimiteDescuento extends Model
{
    public $timestamps = false;
    protected $table = 'limites_descuento';

    protected $fillable = [
        'perfil_id', 'porcentaje_maximo', 'puede_aprobar', 'porcentaje_aprobacion_max',
    ];

    protected function casts(): array
    {
        return [
            'puede_aprobar'             => 'boolean',
            'porcentaje_maximo'         => 'decimal:2',
            'porcentaje_aprobacion_max' => 'decimal:2',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }
}
