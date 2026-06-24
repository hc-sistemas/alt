<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends Model
{
    public $timestamps = false;
    protected $table = 'permisos';

    protected $fillable = [
        'perfil_id', 'modulo_id',
        'ver', 'crear', 'editar', 'eliminar', 'anular',
    ];

    protected function casts(): array
    {
        return [
            'ver'      => 'boolean',
            'crear'    => 'boolean',
            'editar'   => 'boolean',
            'eliminar' => 'boolean',
            'anular'   => 'boolean',
        ];
    }

    public function perfil(): BelongsTo
    {
        return $this->belongsTo(Perfil::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }
}