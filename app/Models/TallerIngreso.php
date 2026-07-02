<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerIngreso extends Model
{
    const UPDATED_AT = null;

    protected $table = 'taller_ingresos';

    protected $fillable = [
        'empresa_id',
        'cliente_id',
        'equipo_id',
        'usuario_id',
        'fecha',
        'hora',
        'diagnostico_inicial',
        'imagen',
        'video',
        'observaciones',
        'estado',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function equipo(): BelongsTo
    {
        return $this->belongsTo(TallerEquipo::class, 'equipo_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function ordenesTrabajo(): HasMany
    {
        return $this->hasMany(TallerOrdenTrabajo::class, 'ingreso_id');
    }
}
