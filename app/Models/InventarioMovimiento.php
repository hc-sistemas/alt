<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    const TIPOS = ['entrada', 'salida', 'traslado', 'ajuste', 'reserva'];

    protected $fillable = [
        'empresa_id', 'producto_id',
        'bodega_origen_id', 'bodega_destino_id',
        'tipo_movimiento', 'documento_tipo', 'documento_id', 'documento_numero',
        'cantidad', 'costo_unitario', 'costo_total',
        'numero_serie', 'fecha', 'hora', 'usuario_id', 'observacion',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
