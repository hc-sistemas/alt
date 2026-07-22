<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaPagar extends Model
{
    protected $table = 'cuentas_pagar';

    protected $fillable = [
        'empresa_id', 'proveedor_id', 'compra_id',
        'monto', 'saldo', 'fecha_emision', 'fecha_vencimiento',
        'aprobada', 'estado', 'asiento_pago_id',
    ];

    protected function casts(): array
    {
        return [
            'monto'             => 'decimal:4',
            'saldo'             => 'decimal:4',
            'fecha_emision'     => 'date',
            'fecha_vencimiento' => 'date',
            'aprobada'          => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function asientoPago(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_pago_id');
    }

    public function scopePendientes(Builder $q): Builder
    {
        return $q->whereIn('estado', ['pendiente', 'parcial']);
    }

    public function scopeVencidas(Builder $q): Builder
    {
        return $q->where('fecha_vencimiento', '<', now()->toDateString())
                 ->where('estado', '!=', 'pagada');
    }

    public function scopePorVencer(Builder $q, int $dias = 15): Builder
    {
        return $q->whereBetween('fecha_vencimiento', [
            now()->toDateString(),
            now()->addDays($dias)->toDateString(),
        ])->where('estado', '!=', 'pagada');
    }

    public function getDiasVencimientoAttribute(): int
    {
        return now()->diffInDays($this->fecha_vencimiento, false);
    }

    public function getUrgenciaAttribute(): string
    {
        $dias = $this->dias_vencimiento;
        if ($dias < 0)   return 'vencida';
        if ($dias <= 5)  return 'critica';
        if ($dias <= 15) return 'proxima';
        return 'normal';
    }

    public function getColorUrgenciaAttribute(): string
    {
        return match ($this->urgencia) {
            'vencida' => 'red',
            'critica' => 'orange',
            'proxima' => 'yellow',
            default   => 'green',
        };
    }
}
