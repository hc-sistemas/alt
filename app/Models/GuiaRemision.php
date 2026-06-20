<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuiaRemision extends Model
{
    const UPDATED_AT = null;

    protected $table = 'guias_remision';

    protected $fillable = [
        'empresa_id',
        'factura_id',
        'transportista_id',
        'establecimiento',
        'punto_emision',
        'secuencial',
        'numero_completo',
        'fecha_emision',
        'fecha_inicio_transporte',
        'fecha_fin_transporte',
        'origen',
        'destino',
        'ruta',
        'motivo',
        'clave_acceso',
        'autorizacion',
        'estado_sri',
        'xml_doc',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'           => 'date',
            'fecha_inicio_transporte' => 'date',
            'fecha_fin_transporte'    => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(GuiaRemisionDetalle::class, 'guia_id');
    }
}
