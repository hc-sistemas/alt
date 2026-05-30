<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimiteDescuento extends Model
{
    public $timestamps = false;
    protected $table = 'limites_descuento';

    // Schema real: empresa_id, perfil_id, descuento_maximo_pct, puede_aprobar, descuento_aprobacion_max_pct
    protected $fillable = [
        'empresa_id', 'perfil_id',
        'descuento_maximo_pct', 'puede_aprobar', 'descuento_aprobacion_max_pct',
        // Alias por si la migración agrega las nuevas columnas
        'porcentaje_maximo', 'porcentaje_aprobacion_max',
    ];

    protected function casts(): array
    {
        return [
            'puede_aprobar' => 'boolean',
            'descuento_maximo_pct' => 'decimal:2',
            'descuento_aprobacion_max_pct' => 'decimal:2',
        ];
    }

    public function getPorcentajeMaximoAttribute(): float
    {
        return (float) ($this->attributes['descuento_maximo_pct'] ?? $this->attributes['porcentaje_maximo'] ?? 0);
    }

    public function getPorcentajeAprobacionMaxAttribute(): float
    {
        return (float) ($this->attributes['descuento_aprobacion_max_pct'] ?? $this->attributes['porcentaje_aprobacion_max'] ?? 0);
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }
}
