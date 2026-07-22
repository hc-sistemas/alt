<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Importacion extends Model
{
    protected $table = 'importaciones';

    protected $fillable = [
        'empresa_id', 'proveedor_id', 'nombre', 'num_invoice',
        'agente_aduanero', 'pais_embarque', 'costo_fob', 'divisa',
        'fecha_partida', 'fecha_llegada', 'fecha_liquidacion',
        'total_costos_extra', 'costo_total', 'metodo_prorrateo',
        'estado', 'observaciones', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'costo_fob'          => 'decimal:4',
            'total_costos_extra' => 'decimal:4',
            'costo_total'        => 'decimal:4',
            'fecha_partida'      => 'date',
            'fecha_llegada'      => 'date',
            'fecha_liquidacion'  => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function compras(): HasMany
    {
        return $this->hasMany(Compra::class, 'importacion_id');
    }

    public function anticipos(): HasMany
    {
        return $this->hasMany(AnticipoProveedor::class, 'importacion_id');
    }

    public function scopeDeEmpresa(Builder $q, int $id): Builder
    {
        return $q->where('empresa_id', $id);
    }

    public function estaLiquidada(): bool
    {
        return $this->estado === 'liquidada';
    }

    public function getEstadoColorAttribute(): string
    {
        return match ($this->estado) {
            'en_transito' => 'blue',
            'en_aduana'   => 'yellow',
            'liquidada'   => 'green',
            default       => 'gray',
        };
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'en_transito' => 'En Tránsito',
            'en_aduana'   => 'En Aduana',
            'liquidada'   => 'Liquidada',
            default       => $this->estado,
        };
    }
}
