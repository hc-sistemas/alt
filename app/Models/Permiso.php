<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends Model
{
    public $timestamps = false;
    protected $table = 'permisos';

    // El schema real usa puede_ver, puede_crear, etc.
    protected $fillable = [
        'perfil_id', 'modulo_id',
        'puede_ver', 'puede_crear', 'puede_editar', 'puede_eliminar', 'puede_anular',
    ];

    protected function casts(): array
    {
        return [
            'puede_ver' => 'boolean',
            'puede_crear' => 'boolean',
            'puede_editar' => 'boolean',
            'puede_eliminar' => 'boolean',
            'puede_anular' => 'boolean',
        ];
    }

    // Aliases para compatibilidad con el frontend que usa ver/crear/editar/eliminar/anular
    public function getVerAttribute(): bool { return $this->puede_ver ?? false; }
    public function getCrearAttribute(): bool { return $this->puede_crear ?? false; }
    public function getEditarAttribute(): bool { return $this->puede_editar ?? false; }
    public function getEliminarAttribute(): bool { return $this->puede_eliminar ?? false; }
    public function getAnularAttribute(): bool { return $this->puede_anular ?? false; }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }
}
