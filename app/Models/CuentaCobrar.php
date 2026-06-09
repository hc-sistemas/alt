<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaCobrar extends Model
{
    protected $table = 'cuentas_cobrar';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'factura_id',
        'prefactura_id',
        'monto',
        'saldo',
        'fecha_emision',
        'fecha_vencimiento',
        'forma_cobro',
        'estado',
        'asiento_cobro_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'    => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function prefactura(): BelongsTo
    {
        return $this->belongsTo(Prefactura::class);
    }
}
