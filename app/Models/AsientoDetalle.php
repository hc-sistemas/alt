<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AsientoDetalle extends Model
{
    public $timestamps = false;
    protected $table   = 'asiento_detalles';

    protected $fillable = [
        'asiento_id', 'cuenta_id', 'centro_costo_id',
        'descripcion', 'debe', 'haber',
    ];

    protected function casts(): array
    {
        return [
            'debe'  => 'decimal:4',
            'haber' => 'decimal:4',
        ];
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function esDebe(): bool  { return $this->debe  > 0; }
    public function esHaber(): bool { return $this->haber > 0; }
}
