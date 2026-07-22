<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Configuracion extends Model
{
    public $timestamps = false;
    protected $table = 'configuraciones';

    // Schema real: id, empresa_id, clave, valor, descripcion
    protected $fillable = ['empresa_id', 'clave', 'valor', 'descripcion'];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
