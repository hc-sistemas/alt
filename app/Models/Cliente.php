<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'empresa_id',
        'ruc_cedula',
        'nombre',
        'direccion',
        'telefono',
        'email',
        'ciudad',
        'pais',
        'tiene_credito',
        'dias_credito',
        'cupo_credito',
        'es_agente_retencion',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tiene_credito' => 'boolean',
            'es_agente_retencion' => 'boolean',
            'estado' => 'boolean',
            'cupo_credito' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }

    public function scopePorEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
