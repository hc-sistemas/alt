<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HorasExtrasAprobacion extends Model
{
    protected $table = 'horas_extras_aprobacion';
    public $timestamps = false;
    public const CREATED_AT = 'created_at';

    protected $fillable = [
        'colaborador_id', 'asistencia_id', 'fecha',
        'horas_solicitadas', 'horas_aprobadas', 'tipo',
        'valor_calculado', 'estado',
        'aprobado_por', 'fecha_aprobacion', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'             => 'date',
            'fecha_aprobacion'  => 'datetime',
            'horas_solicitadas' => 'float',
            'horas_aprobadas'   => 'float',
            'valor_calculado'   => 'float',
            'created_at'        => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function asistencia(): BelongsTo
    {
        return $this->belongsTo(Asistencia::class);
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'aprobado_por');
    }
}
