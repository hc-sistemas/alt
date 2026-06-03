<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ActivoFijo extends Model
{
    use SoftDeletes;

    protected $table = 'activos_fijos';

    public const CATEGORIAS = [
        'terreno', 'edificio', 'vehiculo', 'equipo_computo',
        'maquinaria', 'muebles', 'instalaciones', 'otro',
    ];

    public const ESTADOS = ['activo', 'dado_de_baja', 'vendido'];

    protected $fillable = [
        'empresa_id',
        'codigo',
        'nombre',
        'descripcion',
        'categoria',
        'ubicacion',
        'fecha_adquisicion',
        'valor_adquisicion',
        'valor_residual',
        'vida_util_años',
        'metodo_depreciacion',
        'depreciacion_acumulada',
        'valor_libro',
        'estado',
        'cuenta_activo_id',
        'cuenta_depreciacion_id',
        'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_adquisicion'      => 'date',
            'valor_adquisicion'      => 'decimal:2',
            'valor_residual'         => 'decimal:2',
            'depreciacion_acumulada' => 'decimal:2',
            'valor_libro'            => 'decimal:2',
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
        if ($this->vida_util_años <= 0) {
            return 0.0;
        }

        return round(
            ((float) $this->valor_adquisicion - (float) $this->valor_residual) / ($this->vida_util_años * 12),
            2
        );
    }
}
