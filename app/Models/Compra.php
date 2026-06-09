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
        'iva_asumido', 'gasto_no_deducible', 'sustento_tributario',
        'asiento_id', 'tiene_pago', 'concepto', 'estado', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision'      => 'date',
            'fecha_registro'     => 'date',
            'fecha_vencimiento'  => 'date',
            'subtotal_0'         => 'decimal:4',
            'subtotal_iva'       => 'decimal:4',
            'total_iva'          => 'decimal:4',
            'total_ice'          => 'decimal:4',
            'total'              => 'decimal:4',
            'iva_asumido'        => 'boolean',
            'gasto_no_deducible' => 'boolean',
            'tiene_pago'         => 'boolean',
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

    public function scopeActivas(Builder $q): Builder
    {
        return $q->where('estado', 'activa');
    }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function estaActiva(): bool  { return $this->estado === 'activa'; }
    public function estaAnulada(): bool { return $this->estado === 'anulada'; }

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
