<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Proforma extends Model
{
    protected $table = 'proformas';

    protected $fillable = [
        'empresa_id',
        'centro_costo_id',
        'cliente_id',
        'usuario_id',
        'numero',
        'fecha_emision',
        'fecha_vencimiento',
        'subtotal',
        'descuento_total',
        'total_iva',
        'total',
        'observaciones',
        'factura_id',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'    => 'date',
            'fecha_vencimiento' => 'date',
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
        return $this->hasMany(ProformaDetalle::class, 'proforma_id');
    }
}
