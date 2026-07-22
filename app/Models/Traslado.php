<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Traslado extends Model
{
    protected $table = 'traslados';

    const ESTADOS = ['pendiente', 'confirmado', 'anulado'];

    protected $fillable = [
        'empresa_id',
        'bodega_origen_id',
        'bodega_destino_id',
        'estado',
        'usuario_origen_id',
        'usuario_destino_id',
        'fecha_traslado',
        'fecha_confirmacion',
        'notas_origen',
        'notas_destino',
    ];

    protected function casts(): array
    {
        return [
            'fecha_traslado'    => 'datetime',
            'fecha_confirmacion' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_origen_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_destino_id');
    }

    public function usuarioOrigen(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_origen_id');
    }

    public function usuarioDestino(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_destino_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TrasladoItem::class);
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
