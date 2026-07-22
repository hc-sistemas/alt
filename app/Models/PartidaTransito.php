<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartidaTransito extends Model
{
    public $timestamps  = false;
    protected $table    = 'partidas_transito';
    protected $fillable = [
        'conciliacion_id', 'tipo', 'fecha', 'descripcion',
        'monto', 'movimiento_id', 'conciliada', 'asiento_generado_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha'      => 'date',
            'monto'      => 'decimal:4',
            'conciliada' => 'boolean',
        ];
    }

    public function conciliacion(): BelongsTo
    {
        return $this->belongsTo(ConciliacionBancaria::class, 'conciliacion_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoBancario::class, 'movimiento_id');
    }
}
