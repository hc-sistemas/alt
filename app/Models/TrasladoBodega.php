<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrasladoBodega extends Model
{
    protected $table = 'traslados_bodega';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    const ESTADOS = ['pendiente', 'aceptado', 'rechazado'];

    protected $fillable = [
        'empresa_id', 'bodega_origen_id', 'bodega_destino_id',
        'numero', 'fecha', 'estado',
        'enviado_por', 'recibido_por', 'fecha_recepcion', 'observacion',
    ];

    public function detalles(): HasMany
    {
        return $this->hasMany(TrasladoDetalle::class, 'traslado_id');
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'enviado_por');
    }

    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'recibido_por');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function isPendiente(): bool
    {
        return $this->estado === 'pendiente';
    }
}
