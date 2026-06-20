<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class BancoCaja extends Model
{
    protected $table    = 'bancos_cajas';
    protected $fillable = [
        'empresa_id', 'cuenta_id', 'tipo', 'nombre',
        'num_cuenta', 'tipo_cuenta', 'saldo_inicial', 'saldo_actual', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'saldo_inicial' => 'decimal:4',
            'saldo_actual'  => 'decimal:4',
            'estado'        => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoBancario::class, 'banco_caja_id');
    }

    public function cierres(): HasMany
    {
        return $this->hasMany(CierreCaja::class, 'banco_caja_id');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('estado', true);
    }

    public function scopeBancos(Builder $q): Builder
    {
        return $q->where('tipo', 'banco');
    }

    public function scopeCajas(Builder $q): Builder
    {
        return $q->whereIn('tipo', ['caja', 'caja_chica']);
    }

    public function esBanco(): bool { return $this->tipo === 'banco'; }
    public function esCaja(): bool  { return in_array($this->tipo, ['caja', 'caja_chica']); }

    public function getTipoLabelAttribute(): string
    {
        return match($this->tipo) {
            'banco'      => 'Banco',
            'caja'       => 'Caja',
            'caja_chica' => 'Caja Chica',
            'tarjeta'    => 'Tarjeta',
            default      => $this->tipo,
        };
    }

    public function getTipoColorAttribute(): string
    {
        return match($this->tipo) {
            'banco'      => 'blue',
            'caja'       => 'green',
            'caja_chica' => 'yellow',
            'tarjeta'    => 'purple',
            default      => 'gray',
        };
    }

    public function actualizarSaldo(float $monto, string $tipo): void
    {
        if ($tipo === 'ingreso') {
            $this->increment('saldo_actual', $monto);
        } else {
            $this->decrement('saldo_actual', $monto);
        }
    }
}
