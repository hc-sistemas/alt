<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Liquidacion extends Model
{
    protected $table = 'liquidaciones';

    protected $fillable = [
        'colaborador_id', 'fecha_salida', 'motivo',
        'decimos_acumulados', 'vacaciones', 'fondos_reserva',
        'anticipos_descontar', 'total_liquidacion',
        'estado', 'modificado_manualmente', 'created_by', 'asiento_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_salida'       => 'date',
            'decimos_acumulados' => 'decimal:2',
            'vacaciones'         => 'decimal:2',
            'fondos_reserva'     => 'decimal:2',
            'anticipos_descontar'=> 'decimal:2',
            'total_liquidacion'      => 'decimal:2',
            'modificado_manualmente' => 'boolean',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class);
    }
}
