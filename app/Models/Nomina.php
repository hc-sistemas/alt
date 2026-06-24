<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Nomina extends Model
{
    public $timestamps = false;
    protected $table   = 'nominas';

    protected $fillable = [
        'empresa_id', 'periodo_tipo', 'anio', 'mes', 'quincena',
        'fecha_emision', 'estado', 'total_ingresos', 'total_egresos', 'total_neto',
        'asiento_id', 'generado_por', 'procesado_por', 'pagado_por', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'anio'           => 'integer',
            'mes'            => 'integer',
            'quincena'       => 'integer',
            'fecha_emision'  => 'date',
            'total_ingresos' => 'decimal:2',
            'total_egresos'  => 'decimal:2',
            'total_neto'     => 'decimal:2',
            'created_at'     => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(NominaDetalle::class);
    }

    public function generadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'generado_por');
    }

    public function procesadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'procesado_por');
    }

    public function pagadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'pagado_por');
    }

    private static array $MESES = [
        '', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    public function getPeriodoLabelAttribute(): string
    {
        $mes = self::$MESES[$this->mes] ?? $this->mes;
        if ($this->periodo_tipo === 'quincenal') {
            return "{$this->quincena}ª quincena {$mes} {$this->anio}";
        }
        return "{$mes} {$this->anio}";
    }
}
