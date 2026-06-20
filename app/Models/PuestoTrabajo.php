<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PuestoTrabajo extends Model
{
    protected $table = 'puestos_trabajo';
    public $timestamps = false;

    protected $fillable = ['empresa_id', 'nombre', 'cargo', 'departamento', 'estado'];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'puesto_id');
    }
}
