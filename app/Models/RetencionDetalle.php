<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RetencionDetalle extends Model
{
    public $timestamps = false;

    protected $table = 'retencion_detalles';

    protected $fillable = [
        'retencion_id',
        'impuesto_id',
        'tipo',
        'codigo',
        'porcentaje',
        'base_imponible',
        'valor_retenido',
    ];

    public function retencion(): BelongsTo
    {
        return $this->belongsTo(Retencion::class);
    }
}
