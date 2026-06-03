<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    const TIPOS = [
        'entrada',
        'salida',
        'traslado_entrada',
        'traslado_salida',
        'ajuste_positivo',
        'ajuste_negativo',
        'reserva',
        'liberacion',
    ];

    protected $fillable = [
        'producto_id',
        'bodega_id',
        'tipo',
        'doc_tipo',
        'doc_id',
        'cantidad',
        'costo_unitario',
        'costo_total',
        'stock_anterior',
        'stock_nuevo',
        'usuario_id',
        'empresa_id',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:4',
            'costo_unitario' => 'decimal:4',
            'costo_total'    => 'decimal:4',
            'stock_anterior' => 'decimal:4',
            'stock_nuevo'    => 'decimal:4',
            'created_at'     => 'datetime',
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

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
