<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrefacturaAbono extends Model
{
    const UPDATED_AT = null;

    protected $table = 'prefactura_abonos';

    protected $fillable = [
        'prefactura_id',
        'fecha',
        'valor',
        'forma_pago',
        'banco',
        'num_comprobante',
        'asiento_id',
        'usuario_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function prefactura(): BelongsTo
    {
        return $this->belongsTo(Prefactura::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }
}
