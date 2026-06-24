<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecepcionBodega extends Model
{
    protected $table = 'recepciones_bodega';

    protected $fillable = [
        'empresa_id', 'compra_id', 'bodega_id', 'estado',
        'recibido_por', 'fecha_recepcion', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_recepcion' => 'date',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RecepcionDetalle::class, 'recepcion_id');
    }

    public function isPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }

    public function isCompletada(): bool
    {
        return $this->estado === 'completada';
    }
}
