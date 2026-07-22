<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bodega extends Model
{
    protected $table = 'bodegas';
    public $timestamps = false;

    const TIPOS = ['general', 'importacion', 'taller', 'reserva', 'cuarentena'];

    protected $fillable = [
        'empresa_id', 'centro_costo_id', 'nombre', 'tipo', 'es_virtual', 'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean', 'es_virtual' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function scopeActivas($query)
    {
        return $query->where('estado', true);
    }

    public function scopeDeEmpresa($query, int $empresaId)
    {
        return $query->where('empresa_id', $empresaId);
    }
}
