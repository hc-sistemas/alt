<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Empresa extends Model
{
    use SoftDeletes;

    protected $table = 'empresas';

    // Columnas reales del schema altamira (nombres de legacy)
    protected $fillable = [
        'razon_social', 'nombre_comercial', 'ruc',
        'direccion_matriz', 'direccion_establecimiento',
        'email_notificaciones', 'telefono', 'logo', 'slogan',
        'ambiente_sri', 'cod_establecimiento', 'cod_punto_emision',
        'obligado_contabilidad', 'contribuyente_especial',
        'agente_retencion', 'firma_electronica_path', 'firma_electronica_pass',
        'estado',
    ];

    protected $hidden = ['firma_electronica_path', 'firma_electronica_pass'];

    protected function casts(): array
    {
        return [
            'obligado_contabilidad' => 'boolean',
            'estado' => 'boolean',
            'ambiente_sri' => 'integer',
        ];
    }

    public function centrosCosto(): HasMany
    {
        return $this->hasMany(CentroCosto::class);
    }

    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'empresa_usuario');
    }

    public function configuraciones(): HasMany
    {
        return $this->hasMany(Configuracion::class);
    }

    public function secuenciales(): HasMany
    {
        return $this->hasMany(Secuencial::class);
    }

    public function getAmbienteSriLabelAttribute(): string
    {
        return $this->ambiente_sri == 2 ? 'Producción' : 'Pruebas';
    }

    // Aliases para compatibilidad con el formulario del ERP
    public function getCodigoEstablecimientoAttribute(): ?string
    {
        return $this->attributes['cod_establecimiento'] ?? null;
    }

    public function getCodigoPuntoEmisionAttribute(): ?string
    {
        return $this->attributes['cod_punto_emision'] ?? null;
    }
}
