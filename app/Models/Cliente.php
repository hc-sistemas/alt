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
        'tipo_identificacion',
        'identificacion',
        'razon_social',
        'nombre_comercial',
        'email',
        'telefono',
        'celular',
        'direccion',
        'ciudad',
        'provincia',
        'pais',
        'tiene_credito',
        'dias_credito',
        'cupo_maximo',
        'agente_retencion',
        'es_cliente_nuevo',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'tiene_credito'    => 'boolean',
            'agente_retencion' => 'boolean',
            'es_cliente_nuevo' => 'boolean',
            'estado'           => 'boolean',
            'cupo_maximo'      => 'decimal:2',
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

    public function getNombreDisplayAttribute(): string
    {
        return $this->nombre_comercial ?? $this->razon_social;
    }
}
