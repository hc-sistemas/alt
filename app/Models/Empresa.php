<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

    // El schema legacy (producción) usa cod_establecimiento / cod_punto_emision; las BD creadas
    // desde las migraciones usan codigo_*. Estos accesores leen cualquiera de las dos.
    public function getCodEstablecimientoAttribute(): ?string
    {
        return $this->attributes['cod_establecimiento'] ?? $this->attributes['codigo_establecimiento'] ?? null;
    }

    public function getCodPuntoEmisionAttribute(): ?string
    {
        return $this->attributes['cod_punto_emision'] ?? $this->attributes['codigo_punto_emision'] ?? null;
    }

    public function getCodigoEstablecimientoAttribute(): ?string
    {
        return $this->cod_establecimiento;
    }

    public function getCodigoPuntoEmisionAttribute(): ?string
    {
        return $this->cod_punto_emision;
    }

    /** Guarda establecimiento y punto de emisión en las columnas que tenga esta BD. */
    public function guardarPuntoEmision(string $establecimiento, string $puntoEmision): void
    {
        $legacy = Schema::hasColumn('empresas', 'cod_establecimiento');

        DB::table('empresas')->where('id', $this->id)->update([
            $legacy ? 'cod_establecimiento' : 'codigo_establecimiento' => $establecimiento,
            $legacy ? 'cod_punto_emision' : 'codigo_punto_emision'     => $puntoEmision,
        ]);

        $this->refresh();
    }
}
