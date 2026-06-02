<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proveedor extends Model
{
    protected $table = 'proveedores';

    protected $fillable = [
        'empresa_id', 'tipo', 'tipo_identificacion', 'identificacion',
        'razon_social', 'nombre_comercial', 'email', 'telefono',
        'direccion', 'ciudad', 'pais', 'divisa',
        'tiene_credito', 'dias_credito', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'tiene_credito' => 'boolean',
            'estado'        => 'boolean',
            'dias_credito'  => 'integer',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    public function cuentasPagar(): HasMany
    {
        return $this->hasMany(CuentaPagar::class, 'proveedor_id');
    }

    public function anticipos(): HasMany
    {
        return $this->hasMany(AnticipoProveedor::class, 'proveedor_id');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('estado', true);
    }

    public function scopeNacionales(Builder $q): Builder
    {
        return $q->where('tipo', 'nacional');
    }

    public function scopeInternacionales(Builder $q): Builder
    {
        return $q->where('tipo', 'internacional');
    }

    public function getNombreDisplayAttribute(): string
    {
        return $this->nombre_comercial ?? $this->razon_social;
    }

    public function getSaldoPendienteAttribute(): float
    {
        return (float) $this->cuentasPagar()
            ->whereIn('estado', ['pendiente', 'parcial'])
            ->sum('saldo');
    }
}
