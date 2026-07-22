<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventarioMovimiento extends Model
{
    protected $table = 'inventario_movimientos';
    public $timestamps = false;
    public const CREATED_AT = 'created_at';

    // Columnas reales de inventario_movimientos (schema legacy):
    //   id, producto_id, bodega_id, tipo, doc_tipo, doc_id,
    //   cantidad, costo_unitario, costo_total,
    //   stock_anterior, stock_nuevo, usuario_id, empresa_id, notas, created_at

    public const TIPOS = ['entrada', 'salida', 'traslado', 'ajuste', 'reserva', 'reserva_liberada'];

    protected $fillable = [
        'empresa_id', 'producto_id', 'bodega_id',
        'tipo', 'doc_tipo', 'doc_id',
        'cantidad', 'costo_unitario', 'costo_total',
        'stock_anterior', 'stock_nuevo',
        'usuario_id', 'notas', 'created_at',
    ];

    // Aliases para que el frontend y código existente sigan funcionando
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    protected $appends = [
        'tipo_movimiento', 'documento_tipo', 'documento_id',
        'fecha', 'hora', 'observacion',
    ];

    public function getTipoMovimientoAttribute(): ?string  { return isset($this->attributes['tipo']) ? (string) $this->attributes['tipo'] : null; }
    public function getDocumentoTipoAttribute(): ?string  { return $this->attributes['doc_tipo'] ?? null; }
    public function getDocumentoIdAttribute(): ?int       { return isset($this->attributes['doc_id']) ? (int) $this->attributes['doc_id'] : null; }
    public function getFechaAttribute(): ?string          { return $this->created_at?->toDateString(); }
    public function getHoraAttribute(): ?string           { return $this->created_at?->toTimeString(); }
    public function getObservacionAttribute(): ?string    { return $this->attributes['notas'] ?? null; }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function bodega(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    // Alias de relaciones para compatibilidad con código existente
    public function bodegaOrigen(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function bodegaDestino(): BelongsTo
    {
        return $this->belongsTo(Bodega::class, 'bodega_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
