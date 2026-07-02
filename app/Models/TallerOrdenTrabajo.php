<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TallerOrdenTrabajo extends Model
{
    protected $table = 'taller_ordenes_trabajo';

    protected $fillable = [
        'empresa_id',
        'ingreso_id',
        'tecnico_id',
        'numero',
        'fecha_inicio',
        'hora_inicio',
        'fecha_fin_estimada',
        'fecha_fin_real',
        'tipo_orden',
        'descripcion_trabajo',
        'costo_mano_obra',
        'costo_repuestos',
        'costo_total',
        'es_garantia',
        'factura_id',
        'asiento_id',
        'estado',
        'observaciones',
    ];

    protected function casts(): array
    {
        return [
            'es_garantia' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function ingreso(): BelongsTo
    {
        return $this->belongsTo(TallerIngreso::class, 'ingreso_id');
    }

    public function tecnico(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'tecnico_id');
    }

    public function repuestos(): HasMany
    {
        return $this->hasMany(TallerOtRepuesto::class, 'orden_id');
    }

    public function diagnosticos(): HasMany
    {
        return $this->hasMany(TallerDiagnostico::class, 'orden_id');
    }

    public function factura(): BelongsTo
    {
        return $this->belongsTo(Factura::class);
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }
}
