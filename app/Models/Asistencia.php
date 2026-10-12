<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencias';
    public $timestamps = false;

    /** Duración permitida del almuerzo, en minutos. Solo informativa: no descuenta ni bloquea. */
    public const ALMUERZO_MINUTOS = 60;

    protected $fillable = [
        'colaborador_id', 'fecha',
        'hora_entrada', 'hora_salida',
        'minutos_atraso', 'horas_extra', 'tipo_extra',
        'ip_entrada', 'ip_salida', 'observacion',
        'almuerzo_inicio', 'almuerzo_fin', 'almuerzo_minutos',
    ];

    protected function casts(): array
    {
        return [
            // 'fecha' se deja sin cast: PostgreSQL DATE devuelve "YYYY-MM-DD" directamente
            'hora_entrada'   => 'datetime',
            'hora_salida'    => 'datetime',
            'almuerzo_inicio' => 'datetime',
            'almuerzo_fin'    => 'datetime',
            'almuerzo_minutos' => 'integer',
            'minutos_atraso' => 'integer',
            'horas_extra'    => 'float',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function horasExtras(): BelongsTo
    {
        return $this->belongsTo(HorasExtrasAprobacion::class, 'id', 'asistencia_id');
    }
}
