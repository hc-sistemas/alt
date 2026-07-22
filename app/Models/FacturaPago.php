<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FacturaPago extends Model
{
    public $timestamps = false;

    protected $table = 'factura_pagos';

    protected $fillable = [
        'factura_id',
        'forma_pago',
        'valor',
        'dias_credito',
        'fecha_vencimiento',
        'banco',
        'num_cheque',
        'num_voucher',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_vencimiento' => 'date',
        ];
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }
}
