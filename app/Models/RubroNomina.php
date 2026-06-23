<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubroNomina extends Model
{
    public $timestamps = false;
    protected $table   = 'rubros_nomina';

    protected $fillable = [
        'codigo', 'descripcion', 'grupo', 'tipo_valor', 'valor',
        'operacion', 'cuenta_contable', 'afecta_iess', 'afecta_renta', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'valor'        => 'decimal:4',
            'afecta_iess'  => 'boolean',
            'afecta_renta' => 'boolean',
            'estado'       => 'boolean',
        ];
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }
}
