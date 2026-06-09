<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Retencion extends Model
{
    const UPDATED_AT = null;

    protected $table = 'retenciones';

    protected $fillable = [
        'empresa_id',
        'factura_id',
        'compra_id',
        'cliente_id',
        'usuario_id',
        'establecimiento',
        'punto_emision',
        'secuencial',
        'numero_completo',
        'fecha_emision',
        'identificacion',
        'razon_social',
        'num_comp_retenido',
        'total',
        'clave_acceso',
        'autorizacion',
        'estado_sri',
        'xml_doc',
        'asiento_id',
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

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(RetencionDetalle::class, 'retencion_id');
    }
}
