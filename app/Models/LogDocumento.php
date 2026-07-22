<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogDocumento extends Model
{
    public $timestamps = false;
    protected $table = 'log_documentos';

    // Schema real: id, usuario_id, username, accion, modulo, tabla, registro_id, descripcion, ip, empresa_id, centro_costo_id, fecha
    protected $fillable = [
        'usuario_id', 'username', 'accion', 'modulo', 'tabla',
        'registro_id', 'descripcion', 'ip', 'ip_address', 'empresa_id', 'centro_costo_id', 'fecha',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    public function getCreatedAtAttribute(): mixed
    {
        return $this->attributes['fecha'] ?? null;
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
