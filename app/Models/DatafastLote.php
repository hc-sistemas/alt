<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DatafastLote extends Model
{
    public $timestamps  = false;
    protected $table    = 'datafast_lotes';
    protected $fillable = [
        'empresa_id', 'banco_caja_id', 'numero_lote',
        'fecha', 'total_vouchers', 'asiento_id', 'estado', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha'          => 'date',
            'total_vouchers' => 'decimal:4',
            'created_at'     => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function bancoCaja(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class, 'banco_caja_id');
    }

    public function liquidacion(): HasOne
    {
        return $this->hasOne(DatafastLiquidacion::class, 'lote_id');
    }

    public function estaPendiente(): bool { return $this->estado === 'pendiente'; }
    public function estaLiquidado(): bool { return $this->estado === 'liquidado'; }
}
