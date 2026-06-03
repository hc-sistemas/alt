<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id',
        'tipo',
        'ruc_cedula',
        'nombre',
        'direccion',
        'telefono',
        'email',
        'ciudad',
        'pais',
        'divisa',
        'tiene_credito',
        'dias_credito',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'tiene_credito' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        if ($this->tipo === 'nacional' && $this->ruc_cedula) {
            return "{$this->nombre} ({$this->ruc_cedula})";
        }
        return $this->nombre;
    }

    public function scopeNacionales($query)
    {
        return $query->where('tipo', 'nacional');
    }

    public function scopeInternacionales($query)
    {
        return $query->where('tipo', 'internacional');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }
}
