<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
        if ($this->total_asientos > 0 || DB::table('asiento_detalles')->where('cuenta_id', $this->id)->exists()) {
            return "No se puede eliminar \"{$this->nombre}\": tiene asientos contables registrados.";
        }

        // Referencias que la base rechazaría con un error 500 (o que dejarían datos huérfanos).
        $parametros = DB::table('parametros_contables')->where('cuenta_id', $this->id)->pluck('codigo');
        if ($parametros->isNotEmpty()) {
            return "No se puede eliminar \"{$this->nombre}\": está asignada en Parámetros Contables ({$parametros->implode(', ')}). "
                . 'Asigna otra cuenta a esos parámetros y vuelve a intentarlo.';
        }

        $usos = [
            ['bancos_cajas', 'cuenta_id', 'bancos y cajas'],
            ['compra_detalles', 'cuenta_id', 'detalles de compras'],
            ['movimientos_bancarios', 'cuenta_contrapartida_id', 'movimientos bancarios'],
            ['activos_fijos', 'cuenta_id', 'activos fijos'],
        ];
        foreach ($usos as [$tabla, $columna, $etiqueta]) {
            if (DB::table($tabla)->where($columna, $this->id)->exists()) {
                return "No se puede eliminar \"{$this->nombre}\": está en uso en {$etiqueta}.";
            }
        }

        return null;
    }
}
