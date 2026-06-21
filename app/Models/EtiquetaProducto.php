<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EtiquetaProducto extends Model
{
    protected $table = 'etiquetas_productos';

    public $timestamps = false;

    protected $fillable = [
        'empresa_id', 'compra_id', 'compra_detalle_id', 'producto_id',
        'codigo_producto', 'correlativo_desde', 'correlativo_hasta',
        'cantidad', 'generado_por', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'correlativo_desde' => 'integer',
            'correlativo_hasta' => 'integer',
            'cantidad'          => 'integer',
            'created_at'        => 'datetime',
        ];
    }

    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public static function ultimoCorrelativo(int $productoId): int
    {
        return (int) static::where('producto_id', $productoId)->max('correlativo_hasta');
    }

    public static function ultimoCorrelativoPorPrefijo(string $prefijo): int
    {
        return (int) (static::where('codigo_producto', 'LIKE', $prefijo . '-%')
            ->max('correlativo_hasta') ?? 0);
    }

    public static function extraerPrefijo(string $codigo): string
    {
        return explode('-', $codigo)[0];
    }
}
