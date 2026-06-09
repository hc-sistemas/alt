<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatafastLiquidacion extends Model
{
    public $timestamps  = false;
    protected $table    = 'datafast_liquidaciones';
    protected $fillable = [
        'lote_id', 'fecha_deposito', 'valor_bruto', 'comision_datafast',
        'retencion_iva', 'retencion_ir', 'valor_neto',
        'banco_destino_id', 'asiento_id', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_deposito'    => 'date',
            'valor_bruto'       => 'decimal:4',
            'comision_datafast' => 'decimal:4',
            'retencion_iva'     => 'decimal:4',
            'retencion_ir'      => 'decimal:4',
            'valor_neto'        => 'decimal:4',
            'created_at'        => 'datetime',
        ];
    }

    public function lote(): BelongsTo
    {
        return $this->belongsTo(DatafastLote::class, 'lote_id');
    }

    public function bancoDestino(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class, 'banco_destino_id');
    }
}
