<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrestamoEmpleado extends Model
{
    public $timestamps = false;
    protected $table   = 'prestamos_empleados';

    protected $fillable = [
        'colaborador_id', 'tipo', 'monto_total', 'saldo', 'cuota',
        'fecha', 'descripcion', 'estado', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'monto_total' => 'decimal:2',
            'saldo'       => 'decimal:2',
            'cuota'       => 'decimal:2',
            'fecha'       => 'date',
            'created_at'  => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo')->where('saldo', '>', 0);
    }

    // Suma de cuotas mensuales activas para un colaborador y período
    public static function cuotasMes(int $colaboradorId, string $periodoTipo = 'mensual'): float
    {
        $total = static::where('colaborador_id', $colaboradorId)
            ->activos()
            ->where('tipo', 'prestamo')
            ->sum('cuota');

        return $periodoTipo === 'quincenal'
            ? round((float)$total / 2, 2)
            : round((float)$total, 2);
    }

    // Suma de anticipos activos (se descuenta el saldo completo de una sola vez)
    public static function anticiposPendientes(int $colaboradorId): float
    {
        return (float) static::where('colaborador_id', $colaboradorId)
            ->where('estado', 'activo')
            ->where('tipo', 'anticipo')
            ->sum('saldo');
    }
}
