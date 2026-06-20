<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Asistencia extends Model
{
    protected $table = 'asistencias';
    public $timestamps = false;

    protected $fillable = [
        'colaborador_id', 'fecha',
        'hora_entrada', 'hora_salida',
        'minutos_atraso', 'horas_extra', 'tipo_extra',
        'ip_entrada', 'ip_salida', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'        => 'date',
            'hora_entrada' => 'datetime',
            'hora_salida'  => 'datetime',
            'minutos_atraso' => 'integer',
            'horas_extra'  => 'float',
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
