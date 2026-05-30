<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogSesion extends Model
{
    public $timestamps = false;
    protected $table = 'log_sesiones';

    // Schema real: id, usuario_id, username, tipo, ip, user_agent, fecha
    protected $fillable = [
        'usuario_id', 'username', 'tipo', 'ip', 'ip_address', 'user_agent', 'fecha', 'email', 'empresa_id',
    ];

    protected $casts = [
        'fecha' => 'datetime',
    ];

    // Alias: created_at -> fecha
    public function getCreatedAtAttribute(): mixed
    {
        return $this->attributes['fecha'] ?? $this->attributes['created_at'] ?? null;
    }

    public function getIpAddressAttribute(): ?string
    {
        return $this->attributes['ip'] ?? $this->attributes['ip_address'] ?? null;
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
