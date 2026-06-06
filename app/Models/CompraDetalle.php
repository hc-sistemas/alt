<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'compra_detalles';

    protected $fillable = [
        'compra_id', 'producto_id', 'cuenta_id', 'descripcion',
        'cantidad', 'precio_unitario', 'descuento',
        'subtotal', 'porcentaje_iva', 'valor_iva', 'total',
        'es_activo_fijo', 'activo_fijo_id',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'decimal:4',
            'precio_unitario' => 'decimal:4',
            'descuento'       => 'decimal:4',
            'subtotal'        => 'decimal:4',
            'porcentaje_iva'  => 'decimal:2',
            'valor_iva'       => 'decimal:4',
            'total'           => 'decimal:4',
            'es_activo_fijo'  => 'boolean',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }
}
