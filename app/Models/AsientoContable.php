<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsientoContable extends Model
{
    public $timestamps = false;
    protected $table   = 'asientos_contables';

    protected $fillable = [
        'empresa_id', 'ejercicio_id', 'numero', 'fecha', 'concepto',
        'documento_tipo', 'documento_id', 'documento_ref',
        'total_debe', 'total_haber', 'es_automatico', 'estado', 'creado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha'         => 'date',
            'total_debe'    => 'decimal:4',
            'total_haber'   => 'decimal:4',
            'es_automatico' => 'boolean',
            'estado'        => 'integer',
            'created_at'    => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ejercicio(): BelongsTo
    {
        return $this->belongsTo(EjercicioContable::class, 'ejercicio_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'creado_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(AsientoDetalle::class, 'asiento_id');
    }

    public function scopeActivos(Builder $q): Builder
    {
        return $q->where('estado', 1);
    }

    public function scopeAnulados(Builder $q): Builder
    {
        return $q->where('estado', 0);
    }

    public function scopeManuales(Builder $q): Builder
    {
        return $q->where('es_automatico', false);
    }

    public function scopeAutomaticos(Builder $q): Builder
    {
        return $q->where('es_automatico', true);
    }

    public function estaActivo(): bool  { return $this->estado === 1; }
    public function estaAnulado(): bool { return $this->estado === 0; }
    public function estaManual(): bool  { return !$this->es_automatico; }

    public function estaCuadrado(): bool
    {
        return abs($this->total_debe - $this->total_haber) < 0.0001;
    }

    public function getDiferenciaAttribute(): float
    {
        return round($this->total_debe - $this->total_haber, 4);
    }

    public function getTipoLabelAttribute(): string
    {
        return $this->es_automatico ? 'Automático' : 'Manual';
    }

    public function getEstadoLabelAttribute(): string
    {
        return $this->estado === 1 ? 'Activo' : 'Anulado';
    }

    public static function generarNumero(int $empresaId, int $anio): string
    {
        $ultimo = static::where('empresa_id', $empresaId)
                        ->whereYear('fecha', $anio)
                        ->max('numero');

        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $seq    = intval(end($partes)) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('AS-%d-%04d', $anio, $seq);
    }
}
