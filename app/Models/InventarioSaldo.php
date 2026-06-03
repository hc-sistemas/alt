<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioSaldo extends Model
{
    protected $table = 'inventario_saldos';

    public $timestamps = false;

    // Permite que touch() actualice updated_at
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'producto_id',
        'bodega_id',
        'stock_actual',
        'stock_reservado',
        'costo_promedio',
    ];

    protected function casts(): array
    {
        return [
            'stock_actual'    => 'decimal:4',
            'stock_reservado' => 'decimal:4',
            'costo_promedio'  => 'decimal:4',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }

    public function stockDisponible(): float
    {
        return max(0.0, (float) $this->stock_actual - (float) $this->stock_reservado);
    }
}
