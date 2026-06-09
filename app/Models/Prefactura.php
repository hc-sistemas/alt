<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prefactura extends Model
{
    protected $table = 'prefacturas';

    protected $fillable = [
        'empresa_id',
        'centro_costo_id',
        'cliente_id',
        'usuario_id',
        'numero',
        'fecha_emision',
        'total',
        'total_abonado',
        'saldo_pendiente',
        'asiento_id',
        'factura_id',
        'observaciones',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(PrefacturaDetalle::class, 'prefactura_id');
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(PrefacturaAbono::class, 'prefactura_id');
    }
}
