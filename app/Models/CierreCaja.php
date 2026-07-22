<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class CierreCaja extends Model
{
    public $timestamps  = false;
    protected $table    = 'cierres_caja';
    protected $fillable = [
        'empresa_id', 'banco_caja_id', 'centro_costo_id', 'fecha',
        'usuario_apertura_id', 'usuario_cierre_id', 'monto_inicial',
        'total_facturado', 'total_cobrado', 'total_efectivo', 'total_tarjeta',
        'total_cheque', 'total_transferencia', 'total_notas_credito',
        'diferencia', 'observaciones', 'estado', 'hora_apertura', 'hora_cierre',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha'           => 'date',
            'hora_apertura'   => 'datetime',
            'hora_cierre'     => 'datetime',
            'monto_inicial'   => 'decimal:4',
            'total_facturado' => 'decimal:4',
            'total_cobrado'   => 'decimal:4',
            'total_efectivo'  => 'decimal:4',
            'total_tarjeta'   => 'decimal:4',
            'diferencia'      => 'decimal:4',
        ];
    }

    public function bancoCaja(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class, 'banco_caja_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function usuarioApertura(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_apertura_id');
    }

    public function usuarioCierre(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_cierre_id');
    }

    public function scopeAbiertas(Builder $q): Builder
    {
        return $q->where('estado', 'abierto');
    }

    public function estaAbierta(): bool { return $this->estado === 'abierto'; }
    public function estaCerrada(): bool { return $this->estado === 'cerrado'; }

    public function tieneDiferencia(): bool
    {
        return abs((float) $this->diferencia) > 0.01;
    }
}
