<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogCambioCritico extends Model
{
    public $timestamps = false;
    protected $table = 'log_cambios_criticos';

    protected $fillable = [
        'usuario_id', 'empresa_id', 'tabla', 'registro_id',
        'campo', 'valor_anterior', 'valor_nuevo', 'ip_address',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
