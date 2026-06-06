<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioSaldo extends Model
{
    protected $table = 'inventario_saldos';
    public $timestamps = false;
    const UPDATED_AT = 'updated_at';

    protected $fillable = ['producto_id', 'bodega_id', 'cantidad', 'costo_promedio'];

    protected function casts(): array
    {
        return ['cantidad' => 'float', 'costo_promedio' => 'float'];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class);
    }
}
