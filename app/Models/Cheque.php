<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Cheque extends Model
{
    use \App\Models\Concerns\PerteneceAEmpresaActiva;
    public $timestamps  = false;
    protected $table    = 'cheques';
    protected $fillable = [
        'empresa_id', 'banco_caja_id', 'movimiento_id', 'numero', 'banco',
        'cuenta', 'monto', 'fecha_emision', 'fecha_cobro',
        'beneficiario', 'estado', 'observacion', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
            'fecha_cobro'   => 'date',
            'monto'         => 'decimal:4',
            'created_at'    => 'datetime',
        ];
    }

    /**
     * Un cheque pasa a «cobrado» solo cuando su movimiento se concilia con el estado de cuenta (02-06 §2.5), y vuelve a
     * «emitido» si esa conciliación se anula. La fecha de cobro prevista se conserva; si no había, queda la de hoy.
     *
     * @param iterable<int> $movimientoIds
     */
    public static function sincronizarConciliacion(iterable $movimientoIds, bool $conciliado): void
    {
        $ids = collect($movimientoIds)->filter()->values()->all();
        if (!$ids) {
            return;
        }

        if ($conciliado) {
            static::whereIn('movimiento_id', $ids)->where('estado', 'emitido')
                ->update(['estado' => 'cobrado', 'fecha_cobro' => \DB::raw('COALESCE(fecha_cobro, CURRENT_DATE)')]);
        } else {
            static::whereIn('movimiento_id', $ids)->where('estado', 'cobrado')->update(['estado' => 'emitido']);
        }
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function bancoCaja(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class, 'banco_caja_id');
    }

    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoBancario::class, 'movimiento_id');
    }

    public function estaEmitido(): bool   { return $this->estado === 'emitido'; }
    public function estaCobrado(): bool   { return $this->estado === 'cobrado'; }
    public function estaAnulado(): bool   { return $this->estado === 'anulado'; }
    public function estaProtestado(): bool { return $this->estado === 'protestado'; }
}
