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
        'fecha_cheque',
        'num_voucher',
        'estado',
        'banco_caja_id',
        'referencia',
        'procesador_id',
        'tipo_tarjeta',
        'lote',
        'autorizacion',
    ];

    /** dias_credito solo importa en crédito: nunca se guarda nulo (error SQLSTATE[23502] del V3). */
    protected static function booted(): void
    {
        static::saving(function (self $pago) {
            $pago->dias_credito = (int) ($pago->dias_credito ?? 0);
        });
    }

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
