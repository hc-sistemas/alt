<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EjercicioContable extends Model
{
    public $timestamps = false;
    protected $table   = 'ejercicios_contables';

    protected $appends = ['nombre_mes', 'periodo_label'];

    protected $fillable = [
        'empresa_id', 'anio', 'mes', 'descripcion',
        'fecha_apertura', 'fecha_cierre', 'cerrado_por', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'anio'           => 'integer',
            'mes'            => 'integer',
            'fecha_apertura' => 'date',
            'fecha_cierre'   => 'date',
            'created_at'     => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'cerrado_por');
    }

    public function asientos(): HasMany
    {
        return $this->hasMany(AsientoContable::class, 'ejercicio_id');
    }

    public function scopeAbiertos(Builder $q): Builder
    {
        return $q->where('estado', 'abierto');
    }

    public function scopeCerrados(Builder $q): Builder
    {
        return $q->where('estado', 'cerrado');
    }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    public function estaCerrado(): bool
    {
        return $this->estado === 'cerrado';
    }

    public function permiteAsientos(): bool
    {
        return $this->estado === 'abierto';
    }

    public function getNombreMesAttribute(): string
    {
        $meses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];
        return $meses[$this->mes] ?? '';
    }

    public function getPeriodoLabelAttribute(): string
    {
        return "{$this->getNombreMesAttribute()} {$this->anio}";
    }

    public static function periodoActivo(int $empresaId): ?self
    {
        return static::where('empresa_id', $empresaId)
                     ->where('estado', 'abierto')
                     ->orderByDesc('anio')
                     ->orderByDesc('mes')
                     ->first();
    }
}
