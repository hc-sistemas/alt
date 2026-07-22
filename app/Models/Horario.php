<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Horario extends Model
{
    protected $table = 'horarios';
    public $timestamps = false;

    protected $fillable = [
        'descripcion', 'hora_entrada', 'hora_salida', 'tolerancia_minutos',
        'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado', 'domingo',
    ];

    protected function casts(): array
    {
        return [
            'tolerancia_minutos' => 'integer',
            'lunes'    => 'boolean',
            'martes'   => 'boolean',
            'miercoles'=> 'boolean',
            'jueves'   => 'boolean',
            'viernes'  => 'boolean',
            'sabado'   => 'boolean',
            'domingo'  => 'boolean',
        ];
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'horario_id');
    }
}
