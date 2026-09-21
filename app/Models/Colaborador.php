<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Colaborador extends Model
{
    protected $table = 'colaboradores';

    protected $fillable = [
        'empresa_id', 'puesto_id', 'horario_id',
        'cedula_ruc', 'apellidos', 'nombres', 'email',
        'telefono', 'celular', 'direccion',
        'fecha_nacimiento', 'sexo', 'estado_civil',
        'fecha_ingreso', 'fecha_salida', 'tipo_contrato',
        'cargo', 'departamento', 'departamento_id', 'comision_porcentaje',
        'sueldo_base', 'decimo_tercero', 'decimo_cuarto', 'fondos_reserva',
        'banco', 'tipo_cuenta', 'numero_cuenta',
        'usuario_id', 'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento'    => 'date',
            'fecha_ingreso'       => 'date',
            'fecha_salida'        => 'date',
            'sueldo_base'         => 'float',
            'comision_porcentaje' => 'float',
            'estado'              => 'boolean',
        ];
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellidos} {$this->nombres}";
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function puesto(): BelongsTo
    {
        return $this->belongsTo(PuestoTrabajo::class, 'puesto_id');
    }

    public function departamentoRel(): BelongsTo
    {
        return $this->belongsTo(Departamento::class, 'departamento_id');
    }

    public function horario(): BelongsTo
    {
        return $this->belongsTo(Horario::class, 'horario_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function asistencias(): HasMany
    {
        return $this->hasMany(Asistencia::class);
    }

    public function horasExtras(): HasMany
    {
        return $this->hasMany(HorasExtrasAprobacion::class);
    }

    // Prefijo de la cédula provisional que se asigna cuando la ficha se crea
    // automáticamente desde un usuario del sistema (cedula_ruc es NOT NULL y
    // única). Mientras la tenga, la ficha está "pendiente de completar".
    public const PREFIJO_CEDULA_PENDIENTE = 'PEND-';

    public function getFichaPendienteAttribute(): bool
    {
        return str_starts_with((string) $this->cedula_ruc, self::PREFIJO_CEDULA_PENDIENTE);
    }

    // ÚNICO punto donde se crea/rompe el vínculo Colaborador ↔ Usuario. Mantiene
    // sincronizadas las dos columnas (colaboradores.usuario_id — fuente de verdad —
    // y usuarios.colaborador_id) y libera cualquier vínculo previo cruzado.
    // Actualiza con query builder a propósito: no dispara UsuarioObserver.
    public function vincularUsuario(?Usuario $usuario): void
    {
        DB::transaction(function () use ($usuario) {
            if ($this->usuario_id && $this->usuario_id !== $usuario?->id) {
                Usuario::where('id', $this->usuario_id)->update(['colaborador_id' => null]);
            }

            if ($usuario) {
                Colaborador::where('usuario_id', $usuario->id)
                    ->where('id', '!=', $this->id)
                    ->update(['usuario_id' => null]);
                Usuario::where('id', $usuario->id)->update(['colaborador_id' => $this->id]);
            }

            $this->usuario_id = $usuario?->id;
            $this->save();
        });
    }

    public function scopeActivos($query)
    {
        return $query->where('estado', true);
    }
}
