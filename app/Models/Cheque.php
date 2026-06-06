<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    public $timestamps  = false;
    protected $table    = 'cheques';
    protected $fillable = [
        'empresa_id', 'banco_caja_id', 'movimiento_id', 'numero', 'banco',
        'cuenta', 'monto', 'fecha_emision', 'fecha_cobro',
        'beneficiario', 'estado', 'observacion', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_cobro'   => 'date',
            'monto'         => 'decimal:4',
            'created_at'    => 'datetime',
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

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoBancario::class, 'movimiento_id');
    }

    public function estaEmitido(): bool   { return $this->estado === 'emitido'; }
    public function estaCobrado(): bool   { return $this->estado === 'cobrado'; }
    public function estaAnulado(): bool   { return $this->estado === 'anulado'; }
    public function estaProtestado(): bool { return $this->estado === 'protestado'; }
}
