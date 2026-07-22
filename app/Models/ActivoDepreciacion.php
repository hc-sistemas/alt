<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivoDepreciacion extends Model
{
    protected $table = 'activos_depreciaciones';

    public $timestamps = false;

    const CREATED_AT = 'created_at';

    protected $fillable = [
        'activo_id',
        'periodo_año',
        'periodo_mes',
        'monto',
        'depreciacion_acumulada_al_periodo',
        'valor_libro_al_periodo',
    ];

    protected function casts(): array
    {
        return [
            'monto'                            => 'decimal:2',
            'depreciacion_acumulada_al_periodo' => 'decimal:2',
            'valor_libro_al_periodo'            => 'decimal:2',
        ];
    }

    public function activo(): BelongsTo
    {
        return $this->belongsTo(ActivoFijo::class, 'activo_id');
    }
}
