<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class Usuario extends Authenticatable
{
    use Notifiable, HasRoles;

    protected $table = 'usuarios';

    protected $fillable = [
        'empresa_id', 'perfil_id', 'centro_costo_id',
        'nombre', 'email', 'username', 'telefono',
        'password', 'codigo_aprobacion', 'avatar', 'estado',
    ];

    protected $hidden = ['password', 'codigo_aprobacion', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'ultimo_acceso' => 'datetime',
            'estado' => 'boolean',
            'password' => 'hashed',
            'codigo_aprobacion' => 'hashed',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }

    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class, 'empresa_usuario');
    }

    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class);
    }

    public function notificacionesNoLeidas(): HasMany
    {
        return $this->hasMany(Notificacion::class)->where('leida', false);
    }

    public function logSesiones(): HasMany
    {
        return $this->hasMany(LogSesion::class);
    }

    public function tienePermisoModulo(string $modulo, string $accion = 'ver'): bool
    {
        $permiso = $this->perfil?->permisos()
            ->whereHas('modulo', fn($q) => $q->where('clave', $modulo))
            ->first();

        return $permiso?->$accion ?? false;
    }
}
