<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Compra extends Model
{
    protected $table = 'compras';

    protected $fillable = [
        'empresa_id', 'centro_costo_id', 'proveedor_id', 'importacion_id',
        'bodega_id', 'tipo_documento', 'num_documento', 'num_autorizacion',
        'fecha_emision', 'fecha_registro', 'fecha_vencimiento', 'dias_credito',
        'subtotal_0', 'subtotal_iva', 'total_iva', 'total_ice', 'total',
        'retencion_ir', 'retencion_iva',
        'iva_asumido', 'gasto_no_deducible', 'sustento_tributario',
        'asiento_id', 'tiene_pago', 'concepto', 'estado', 'created_by',
        'metodo_envio', 'divisa', 'tipo_cambio',
        'num_orden_compra', 'num_contrato', 'vigencia_desde', 'vigencia_hasta',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'      => 'date',
            'fecha_registro'     => 'date',
            'fecha_vencimiento'  => 'date',
            'subtotal_0'         => 'float',
            'subtotal_iva'       => 'float',
            'total_iva'          => 'float',
            'total_ice'          => 'decimal:4',
            'total'              => 'float',
            'retencion_ir'       => 'float',
            'retencion_iva'      => 'float',
            'dias_credito'       => 'integer',
            'iva_asumido'        => 'boolean',
            'gasto_no_deducible' => 'boolean',
            'tiene_pago'         => 'boolean',
            'tipo_cambio'        => 'decimal:4',
            'vigencia_desde'     => 'date',
            'vigencia_hasta'     => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(Importacion::class, 'importacion_id');
    }

    public function asiento(): BelongsTo
    {
        return $this->belongsTo(AsientoContable::class, 'asiento_id');
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'created_by');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(CompraDetalle::class, 'compra_id');
    }

    public function cuentaPagar(): HasOne
    {
        return $this->hasOne(CuentaPagar::class, 'compra_id');
    }

    public function recepcionBodega(): HasOne
    {
        return $this->hasOne(RecepcionBodega::class, 'compra_id');
    }

    public function etiquetasProductos(): HasMany
    {
        return $this->hasMany(EtiquetaProducto::class, 'compra_id');
    }

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('estado', 'activa');
    }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function estaPendiente(): bool { return $this->estado === 'pendiente'; }
    public function estaActiva(): bool    { return $this->estado === 'activa'; }
    public function estaAnulada(): bool   { return $this->estado === 'anulada'; }

    public function puedeAnularse(): bool
    {
        return !$this->estaAnulada() && !$this->tiene_pago;
    }

    public function puedeEditarse(): bool
    {
        return $this->estaActiva() && !$this->tiene_pago;
    }

    public function motivoNoPuedeEditarse(): ?string
    {
        if ($this->estaAnulada()) return 'La compra está anulada.';
        if ($this->tiene_pago)
            return 'La compra tiene un pago registrado. Anula el pago primero para editar.';
        return null;
    }
}
