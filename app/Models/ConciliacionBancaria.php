<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ConciliacionBancaria extends Model
{
    public $timestamps  = false;
    protected $table    = 'conciliaciones_bancarias';
    protected $fillable = [
        'empresa_id', 'banco_caja_id', 'fecha_corte', 'saldo_banco',
        'saldo_sistema', 'diferencia', 'descripcion',
        'archivo_csv', 'estado', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_corte'   => 'date',
            'saldo_banco'   => 'decimal:4',
            'saldo_sistema' => 'decimal:4',
            'diferencia'    => 'decimal:4',
            'created_at'    => 'datetime',
        ];
    }

    public function bancoCaja(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class, 'banco_caja_id');
    }

    public function partidas(): HasMany
    {
        return $this->hasMany(PartidaTransito::class, 'conciliacion_id');
    }

    public function estaConciliada(): bool { return $this->estado === 'conciliada'; }

    public function tieneDiferencia(): bool
    {
        return abs((float) $this->diferencia) > 0.01;
    }
}
