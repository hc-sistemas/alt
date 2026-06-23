<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecepcionEscaneo extends Model
{
    public $timestamps = false;

    protected $table = 'recepcion_escaneos';

    protected $fillable = [
        'recepcion_id', 'recepcion_detalle_id', 'producto_id',
        'codigo_escaneado', 'correlativo', 'usuario_id', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'correlativo' => 'integer',
            'created_at'  => 'datetime',
        ];
    }

    public function recepcion(): BelongsTo
    {
        return $this->belongsTo(RecepcionBodega::class, 'recepcion_id');
    }

    public function detalle(): BelongsTo
    {
        return $this->belongsTo(RecepcionDetalle::class, 'recepcion_detalle_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
