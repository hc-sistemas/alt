<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Factura extends Model
{
    protected $table = 'facturas';

    protected $fillable = [
        'empresa_id',
        'centro_costo_id',
        'cliente_id',
        'usuario_id',
        'establecimiento',
        'punto_emision',
        'secuencial',
        'numero_completo',
        'fecha_emision',
        'hora_emision',
        'clave_acceso',
        'autorizacion',
        'fecha_hora_aut',
        'estado_sri',
        'observacion_sri',
        'xml_doc',
        'tipo_identificacion',
        'identificacion',
        'razon_social',
        'email_cliente',
        'telefono_cliente',
        'direccion_cliente',
        'subtotal_0',
        'subtotal_15',
        'subtotal_exento',
        'descuento_total',
        'total_ice',
        'total_iva',
        'total',
        'asiento_id',
        'guia_remision',
        'observaciones',
        'email_enviado',
        'tipo',
        'estado',
        'tiene_descuento_especial',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'           => 'date',
            'email_enviado'           => 'boolean',
            'tiene_descuento_especial' => 'boolean',
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

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(FacturaDetalle::class, 'factura_id');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(FacturaPago::class, 'factura_id');
    }
}
