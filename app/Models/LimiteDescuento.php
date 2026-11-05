<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LimiteDescuento extends Model
{
    public $timestamps = false;
    protected $table = 'limites_descuento';

    protected $fillable = [
        'perfil_id', 'porcentaje_maximo', 'puede_aprobar', 'porcentaje_aprobacion_max', 'solo_cero_o_maximo',
    ];

    protected function casts(): array
    {
        return [
            'puede_aprobar'             => 'boolean',
            'solo_cero_o_maximo'        => 'boolean',
            'porcentaje_maximo'         => 'decimal:2',
            'porcentaje_aprobacion_max' => 'decimal:2',
        ];
    }

    /**
     * ¿El perfil solo puede dar descuento 0 o el máximo sin aprobación (DEC-035 / 02-01 §2.4: «No pueden poner
     * 1, 2, 3, etc.»)? Es una opción del perfil en Configuración → Permisos → Límites de descuento.
     */
    public static function esPerfilDescuentoFijo(?string $perfil): bool
    {
        if ($perfil === null || $perfil === '') {
            return false;
        }

        return (bool) static::whereHas('perfil', fn ($q) => $q->where('nombre', $perfil))->value('solo_cero_o_maximo');
    }

    /**
     * ¿Este % de descuento necesita aprobación especial para el perfil?
     * Supera el máximo, o es un valor intermedio (entre 0 y el máximo) en un
     * perfil de descuento fijo. Si el producto tiene un tope menor que el del
     * perfil, ese tope es el "valor máximo" válido (no se obliga a aprobar un
     * descuento que ya es el máximo del producto).
     */
    public static function exigeAprobacion(float $pct, float $maximo, ?string $perfil, ?float $topeProducto = null): bool
    {
        if ($pct > $maximo) {
            return true;
        }

        $tope = $topeProducto !== null ? min($maximo, $topeProducto) : $maximo;

        return self::esPerfilDescuentoFijo($perfil) && $pct > 0 && $pct < $tope;
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }
}
