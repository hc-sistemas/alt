<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Colaborador extends Model
{
    protected $table = 'colaboradores';

    protected $fillable = [
        'empresa_id', 'puesto_id', 'horario_id',
        'cedula_ruc', 'apellidos', 'nombres', 'email',
        'telefono', 'celular', 'direccion',
        'fecha_nacimiento', 'sexo', 'estado_civil',
        'fecha_ingreso', 'fecha_salida', 'tipo_contrato',
        'cargo', 'departamento', 'comision_porcentaje',
        'sueldo_base', 'decimo_tercero', 'decimo_cuarto', 'fondos_reserva',
        'banco', 'tipo_cuenta', 'numero_cuenta',
        'usuario_id', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento'    => 'date',
            'fecha_ingreso'       => 'date',
            'fecha_salida'        => 'date',
            'sueldo_base'         => 'float',
            'comision_porcentaje' => 'float',
            'estado'              => 'boolean',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellidos} {$this->nombres}";
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoTrabajo::class, 'puesto_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function horasExtras(): HasMany
    {
        return $this->hasMany(HorasExtrasAprobacion::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }
}
