<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerOtRepuesto extends Model
{
    public $timestamps = false;

    protected $table = 'taller_ot_repuestos';

    protected $fillable = [
        'orden_id',
        'producto_id',
        'numero_serie',
        'cantidad',
        'costo_unitario',
        'precio_venta',
        'estado',
    ];

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrdenTrabajo::class, 'orden_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }
}
