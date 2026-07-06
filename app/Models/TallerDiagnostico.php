<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TallerDiagnostico extends Model
{
    public $timestamps = false;

    protected $table = 'taller_diagnosticos';

    protected $fillable = [
        'orden_id',
        'tecnico_id',
        'fecha',
        'hora',
        'diagnostico',
        'tiempo_estimado',
        'tipo_tiempo',
        'cliente_aprueba',
        'fecha_aprobacion',
        'observacion_aprobacion',
        'usuario_aprobacion_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cliente_aprueba'  => 'boolean',
            'fecha_aprobacion' => 'datetime',
        ];
    }

    public function orden(): BelongsTo
    {
        return $this->belongsTo(TallerOrdenTrabajo::class, 'orden_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function usuarioAprobacion(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_aprobacion_id');
    }
}
