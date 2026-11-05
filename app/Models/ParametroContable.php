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

    /**
     * Configuración compartida: la fila general (empresa_id NULL) vale para
     * todas las empresas y una fila con empresa_id es una excepción solo de
     * esa empresa. La excepción tiene prioridad sobre la general.
     */
    public static function getCuenta(string $codigo, int $empresaId): ?PlanCuenta
    {
        return static::resolver($codigo, $empresaId)?->cuenta;
    }

    public static function getCuentaId(string $codigo, int $empresaId): ?int
    {
        return static::resolver($codigo, $empresaId)?->cuenta_id;
    }

    private static function resolver(string $codigo, int $empresaId): ?self
    {
        return static::where('codigo', $codigo)
            ->where(function ($q) use ($empresaId) {
                $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
            })
            ->orderByRaw('empresa_id IS NULL')
            ->first();
    }
}
