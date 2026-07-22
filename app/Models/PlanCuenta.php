<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanCuenta extends Model
{
    public $timestamps = false;

    protected $table = 'plan_cuentas';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'tipo',
        'padre_id', 'nivel', 'permite_asientos',
        'estado', 'total_asientos',
    ];

    protected function casts(): array
    {
        return [
            'permite_asientos' => 'boolean',
            'estado'           => 'boolean',
            'nivel'            => 'integer',
            'total_asientos'   => 'integer',
        ];
    }

    // ─── Relaciones ──────────────────────────────────────────────────────────

    public function padre(): BelongsTo
    {
        return $this->belongsTo(PlanCuenta::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(PlanCuenta::class, 'padre_id')->orderBy('codigo');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopePermiteAsientos(Builder $query): Builder
    {
        return $query->where('permite_asientos', true)->where('estado', true);
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('estado', true);
    }

    public function scopeRaices(Builder $query): Builder
    {
        return $query->whereNull('padre_id')->orderBy('codigo');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function puedeEliminarse(): bool
    {
        return $this->total_asientos === 0
            && !$this->hijos()->exists();
    }

    public function motivoNoPuedeEliminarse(): ?string
    {
        if ($this->hijos()->exists()) {
            $total = $this->hijos()->count();
            return "No se puede eliminar \"{$this->nombre}\": tiene {$total} cuenta(s) hija(s). Elimina primero las cuentas hijas.";
        }
        if ($this->total_asientos > 0) {
            return "No se puede eliminar \"{$this->nombre}\": tiene {$this->total_asientos} asiento(s) registrado(s).";
        }
        return null;
    }
}
