<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    public $timestamps = false;
    const CREATED_AT = 'created_at';
    const UPDATED_AT = null;

    protected $table = 'notificaciones';

    // Schema real: id, usuario_id, titulo, mensaje, tipo, icono, url, leida, leida_at, created_at
    protected $fillable = [
        'usuario_id', 'tipo', 'titulo', 'mensaje',
        'icono', 'url', 'leida',
    ];

    protected function casts(): array
    {
        return [
            'leida'      => 'boolean',
            'leida_at'   => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
