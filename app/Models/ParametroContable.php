<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParametroContable extends Model
{
    public $timestamps = false;
    protected $table   = 'parametros_contables';

    protected $fillable = [
        'empresa_id', 'codigo', 'cuenta_id', 'descripcion',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'cuenta_id');
    }

    public static function getCuenta(string $codigo, int $empresaId): ?PlanCuenta
    {
        $param = static::where('empresa_id', $empresaId)
                       ->where('codigo', $codigo)
                       ->first();
        return $param?->cuenta;
    }

    public static function getCuentaId(string $codigo, int $empresaId): ?int
    {
        return static::where('empresa_id', $empresaId)
                     ->where('codigo', $codigo)
                     ->value('cuenta_id');
    }
}
