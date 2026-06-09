<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivoFijo extends Model
{
    protected $table = 'activos_fijos';

    public const ESTADOS = ['activo', 'dado_de_baja', 'vendido'];

    protected $fillable = [
        'empresa_id', 'cuenta_id', 'nombre', 'descripcion', 'codigo',
        'fecha_adquisicion', 'costo_adquisicion', 'vida_util_anios',
        'valor_residual', 'depreciacion_acumulada', 'valor_en_libros', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_adquisicion'      => 'date',
            'costo_adquisicion'      => 'decimal:2',
            'valor_residual'         => 'decimal:2',
            'depreciacion_acumulada' => 'decimal:2',
            'valor_en_libros'        => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function depreciaciones(): HasMany
    {
        return $this->hasMany(ActivoDepreciacion::class, 'activo_id');
    }

    public function depreciacionMensual(): float
    {
        if ($this->vida_util_anios <= 0) {
            return 0.0;
        }

        return round(
            ((float) $this->costo_adquisicion - (float) $this->valor_residual) / ($this->vida_util_anios * 12),
            2
        );
    }
}
