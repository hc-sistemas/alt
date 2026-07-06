<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaCobrarCobro extends Model
{
    public $timestamps = false;

    protected $table = 'cuentas_cobrar_cobros';

    protected $fillable = [
        'cuenta_cobrar_id', 'usuario_id', 'fecha',
        'valor', 'forma_pago', 'observacion', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha'      => 'date',
            'valor'      => 'decimal:4',
            'created_at' => 'datetime',
        ];
    }

    public function cuentaCobrar(): BelongsTo
    {
        return $this->belongsTo(CuentaCobrar::class, 'cuenta_cobrar_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
