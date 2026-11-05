<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TarjetaProcesador extends Model
{
    use \App\Models\Concerns\PerteneceAEmpresaActiva;
    protected $table    = 'tarjeta_procesadores';
    protected $fillable = [
        'empresa_id', 'nombre', 'banco_destino_id', 'comision_credito', 'comision_debito',
        'comision_diferido', 'retencion_iva_pct', 'retencion_ir_pct', 'iva_comision_pct', 'plazo_dias', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'comision_credito'  => 'decimal:3',
            'comision_debito'   => 'decimal:3',
            'comision_diferido' => 'decimal:3',
            'retencion_iva_pct' => 'decimal:3',
            'retencion_ir_pct'  => 'decimal:3',
            'estado'            => 'boolean',
        ];
    }

    public function bancoDestino(): BelongsTo
    {
        return $this->belongsTo(BancoCaja::class, 'banco_destino_id');
    }
}
