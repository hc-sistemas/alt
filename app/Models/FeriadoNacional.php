<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeriadoNacional extends Model
{
    protected $table = 'feriados_nacionales';

    protected $fillable = [
        'fecha', 'nombre',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    // NOM-06: el timbre clasifica una hora extra como "extraordinaria" (recargo
    // 100%) en vez de "suplementaria" (50%) cuando cae sábado, domingo,
    // madrugada, o feriado nacional — este helper cubre el caso feriado.
    public static function esFeriado(string $fecha): bool
    {
        return self::where('fecha', $fecha)->exists();
    }
}
