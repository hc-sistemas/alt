<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DevolucionCompra extends Model
{
    protected $table = 'devoluciones_compra';

    protected $fillable = [
        'empresa_id', 'compra_id', 'proveedor_id',
        'num_documento', 'fecha', 'motivo', 'estado',
        'subtotal', 'iva', 'total',
        'asiento_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'    => 'date',
            'subtotal' => 'float',
            'iva'      => 'float',
            'total'    => 'float',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(DevolucionCompraDetalle::class, 'devolucion_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }
}
