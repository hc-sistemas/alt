<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnticipoProveedor extends Model
{
    public $timestamps = false;

    protected $table = 'anticipos_proveedores';

    protected $fillable = [
        'empresa_id', 'proveedor_id', 'importacion_id',
        'fecha', 'monto', 'saldo', 'banco_id',
        'num_transferencia', 'asiento_id', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'monto'      => 'decimal:4',
            'saldo'      => 'decimal:4',
            'fecha'      => 'date',
            'created_at' => 'datetime',
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

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->where('estado', 'pendiente');
    }

    public function estaPendiente(): bool { return $this->estado === 'pendiente'; }
    public function estaCruzado(): bool   { return $this->estado === 'cruzado'; }
}
