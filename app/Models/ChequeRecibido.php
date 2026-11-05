<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cheque recibido de un cliente (02-04 §2.7). Queda «en cartera» (PC-VEN-13) y solo se vuelve movimiento bancario cuando
 * se deposita (PC-VEN-14).
 */
class ChequeRecibido extends Model
{
    use \App\Models\Concerns\PerteneceAEmpresaActiva;

    protected $table = 'cheques_recibidos';

    protected $fillable = [
        'empresa_id', 'cliente_id', 'factura_id', 'factura_pago_id', 'cuenta_cobrar_id', 'numero', 'banco_emisor', 'monto',
        'fecha_recepcion', 'fecha_cheque', 'estado', 'fecha_deposito', 'banco_caja_id', 'movimiento_id', 'asiento_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
            'fecha_cheque'    => 'date',
            'fecha_deposito'  => 'date',
            'monto'           => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function bancoCaja(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class);
    }

    public function esPostfechado(): bool
    {
        return $this->fecha_cheque && $this->fecha_recepcion && $this->fecha_cheque->gt($this->fecha_recepcion);
    }
}
