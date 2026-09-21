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

    /**
     * Siguiente número de la serie AS-{año}-{secuencial}.
     *
     * Antes usaba max('numero') sobre una columna de TEXTO, lo que fallaba de
     * dos maneras:
     *
     *   1. El Cierre Fiscal Anual crea asientos llamados "CIERRE-2026" y
     *      "CIERRE-ARRASTRE-2026". Como 'C' > 'A', el max() de texto devolvía
     *      "CIERRE-ARRASTRE-2026", y intval(end(explode('-'))) daba 2026 → el
     *      siguiente asiento del año se numeraba AS-2026-2027 y la serie
     *      quedaba destruida con un salto de ~2.000 números.
     *   2. Al pasar de 9999, "AS-2026-10000" es lexicográficamente MENOR que
     *      "AS-2026-9999", así que la numeración se habría repetido.
     *
     * Ahora se restringe a la propia serie (prefijo AS-{año}-) y el máximo se
     * calcula sobre el secuencial convertido a entero, en SQL.
     */
    public static function generarNumero(int $empresaId, int $anio): string
    {
        $prefijo = sprintf('AS-%d-', $anio);

        $ultimo = (int) static::where('empresa_id', $empresaId)
            ->where('numero', 'like', $prefijo . '%')
            ->selectRaw("COALESCE(MAX(NULLIF(regexp_replace(split_part(numero, '-', 3), '\\D', '', 'g'), '')::bigint), 0) AS seq")
            ->value('seq');

        return sprintf('AS-%d-%04d', $anio, $ultimo + 1);
    }
}
