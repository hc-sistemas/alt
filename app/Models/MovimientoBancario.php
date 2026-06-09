<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class MovimientoBancario extends Model
{
    protected $table    = 'movimientos_bancarios';
    protected $fillable = [
        'empresa_id', 'banco_caja_id', 'tipo', 'sub_tipo', 'fecha', 'monto',
        'persona_tipo', 'persona_id', 'beneficiario', 'num_documento',
        'num_cheque', 'fecha_cheque', 'descripcion', 'documento_tipo',
        'documento_id', 'cuenta_contrapartida_id', 'asiento_id',
        'conciliado', 'es_postfechado', 'anulado', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha'          => 'date',
            'fecha_cheque'   => 'date',
            'monto'          => 'decimal:4',
            'conciliado'     => 'boolean',
            'es_postfechado' => 'boolean',
            'anulado'        => 'boolean',
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

    public function cuentaContrapartida(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_contrapartida_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('anulado', false);
    }

    public function scopeIngresos(Builder $q): Builder
    {
        return $q->where('tipo', 'ingreso');
    }

    public function scopeEgresos(Builder $q): Builder
    {
        return $q->where('tipo', 'egreso');
    }

    public function scopeNoConciliados(Builder $q): Builder
    {
        return $q->where('conciliado', false)->where('anulado', false);
    }

    public function puedeAnularse(): bool
    {
        return !$this->anulado && !$this->conciliado;
    }

    public function getTipoColorAttribute(): string
    {
        return $this->tipo === 'ingreso' ? 'green' : 'red';
    }

    public function getSubTipoLabelAttribute(): string
    {
        return match($this->sub_tipo) {
            'transferencia' => 'Transferencia',
            'cheque'        => 'Cheque',
            'efectivo'      => 'Efectivo',
            'deposito'      => 'Depósito',
            default         => $this->sub_tipo ?? '—',
        };
    }
}
