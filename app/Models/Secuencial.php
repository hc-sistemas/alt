<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Secuencial extends Model
{
    public $timestamps = false;
    protected $table = 'secuenciales';

    // Schema real: id, empresa_id, tipo_documento, establecimiento, punto_emision, secuencial
    protected $fillable = [
        'empresa_id', 'tipo_documento', 'establecimiento', 'punto_emision', 'secuencial', 'siguiente',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function getSiguienteAttribute(): int
    {
        return (int) ($this->attributes['secuencial'] ?? $this->attributes['siguiente'] ?? 1);
    }

    public function getProximoNumeroAttribute(): string
    {
        return str_pad($this->siguiente, 9, '0', STR_PAD_LEFT);
    }
}
