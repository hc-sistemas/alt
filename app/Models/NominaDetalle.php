<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaDetalle extends Model
{
    public $timestamps = false;
    protected $table   = 'nomina_detalles';

    protected $fillable = [
        'nomina_id', 'colaborador_id',
        'sueldo_base', 'horas_extras_50', 'horas_extras_100',
        'comisiones', 'otros_ingresos', 'total_ingresos',
        'aporte_personal_iess', 'descuento_atrasos', 'descuento_prestamos',
        'descuento_anticipos', 'otros_egresos', 'total_egresos', 'neto_pagar',
        'tipo_pago', 'num_cuenta', 'banco',
        'estado', 'modificado_manualmente', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sueldo_base'          => 'decimal:2',
            'horas_extras_50'      => 'decimal:2',
            'horas_extras_100'     => 'decimal:2',
            'comisiones'           => 'decimal:2',
            'otros_ingresos'       => 'decimal:2',
            'total_ingresos'       => 'decimal:2',
            'aporte_personal_iess' => 'decimal:2',
            'descuento_atrasos'    => 'decimal:2',
            'descuento_prestamos'  => 'decimal:2',
            'descuento_anticipos'  => 'decimal:2',
            'otros_egresos'        => 'decimal:2',
            'total_egresos'        => 'decimal:2',
            'neto_pagar'           => 'decimal:2',
            'modificado_manualmente' => 'boolean',
            'created_at'           => 'datetime',
        ];
    }

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }
}
