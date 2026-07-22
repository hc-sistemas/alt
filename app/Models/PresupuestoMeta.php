<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PresupuestoMeta extends Model
{
    protected $table = 'presupuestos_metas';

    protected $fillable = [
        'empresa_id', 'centro_costo_id', 'mes', 'anio', 'meta_ventas', 'meta_cobros',
    ];

    protected function casts(): array
    {
        return [
            'meta_ventas' => 'decimal:2',
            'meta_cobros' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }
}
