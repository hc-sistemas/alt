<?php

namespace App\Console\Commands;

use App\Models\Asistencia;
use App\Models\Colaborador;
use App\Models\HorasExtrasAprobacion;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ResetearAsistenciaHoy extends Command
{
    protected $signature = 'altamira:resetear-asistencia-hoy
                            {usuario_id? : ID del usuario cuyo colaborador se reseteará}
                            {--colaborador_id= : ID directo del colaborador (alternativa)}';

    protected $description = '[DEV ONLY] Elimina el registro de asistencia de HOY de un colaborador para repetir pruebas del Timbre Digital.';

    public function handle(): int
    {
        // ── Guardia: solo entornos locales/testing ──────────────────────────
        if (!app()->environment(['local', 'testing'])) {
            $this->error('Este comando solo puede ejecutarse en entorno local o testing.');
            $this->line('  APP_ENV actual: ' . app()->environment());
            return self::FAILURE;
        }

        $hoy = now()->toDateString();
        $this->line("Fecha a resetear: <fg=yellow>{$hoy}</>");

        // ── Resolver colaborador ─────────────────────────────────────────────
        $colaborador = $this->resolverColaborador();
        if (!$colaborador) {
            return self::FAILURE;
        }

        $this->line("Colaborador: <fg=cyan>[#{$colaborador->id}]</> {$colaborador->apellidos} {$colaborador->nombres}");

        // ── Buscar registro de hoy ───────────────────────────────────────────
        $asistencia = Asistencia::where('colaborador_id', $colaborador->id)
            ->where('fecha', $hoy)
            ->first();

        if (!$asistencia) {
            $this->warn("No había registro de asistencia hoy para este colaborador. Nada que resetear.");
            return self::SUCCESS;
        }

        // ── Mostrar estado actual antes de eliminar ──────────────────────────
        $this->line('');
        $this->line('Registro actual:');
        $this->table(
            ['Campo', 'Valor'],
            [
                ['hora_entrada',   $asistencia->hora_entrada ? $asistencia->hora_entrada->format('H:i:s') : '—'],
                ['hora_salida',    $asistencia->hora_salida  ? $asistencia->hora_salida->format('H:i:s')  : '—'],
                ['minutos_atraso', $asistencia->minutos_atraso . ' min'],
                ['horas_extra',    $asistencia->horas_extra . 'h'],
                ['ip_entrada',     $asistencia->ip_entrada ?? '—'],
            ]
        );

        // ── Confirmar ────────────────────────────────────────────────────────
        if (!$this->confirm('¿Eliminar este registro?', true)) {
            $this->line('Cancelado.');
            return self::SUCCESS;
        }

        // ── Eliminar en transacción ──────────────────────────────────────────
        DB::transaction(function () use ($asistencia) {
            // Primero: quitar registros de horas extras vinculados (FK sin CASCADE)
            $extras = HorasExtrasAprobacion::where('asistencia_id', $asistencia->id)->count();
            if ($extras > 0) {
                HorasExtrasAprobacion::where('asistencia_id', $asistencia->id)->delete();
                $this->line("  → {$extras} registro(s) de horas extras eliminado(s).");
            }

            $asistencia->delete();
        });

        $this->info("✔ Asistencia de hoy ({$hoy}) reseteada para: {$colaborador->apellidos} {$colaborador->nombres} (colaborador #{$colaborador->id})");
        $this->line('  Ahora puedes volver a registrar entrada en el Timbre Digital.');

        return self::SUCCESS;
    }

    private function resolverColaborador(): ?Colaborador
    {
        // Opción 1: --colaborador_id
        if ($colabId = $this->option('colaborador_id')) {
            $colab = Colaborador::find((int) $colabId);
            if (!$colab) {
                $this->error("No se encontró colaborador con id={$colabId}.");
            }
            return $colab;
        }

        // Opción 2: argumento usuario_id
        if ($usuarioId = $this->argument('usuario_id')) {
            $usuario = Usuario::find((int) $usuarioId);
            if (!$usuario) {
                $this->error("No se encontró usuario con id={$usuarioId}.");
                return null;
            }
            $colab = Colaborador::where('usuario_id', $usuario->id)->where('estado', true)->first();
            if (!$colab) {
                $this->error("El usuario [{$usuario->id}] {$usuario->nombre} no tiene colaborador activo vinculado.");
                return null;
            }
            return $colab;
        }

        // Sin argumento: listar colaboradores vinculados para elegir
        $this->warn('No se especificó usuario_id. Colaboradores disponibles:');
        $this->line('');

        $vinculados = Colaborador::with('usuario')
            ->whereNotNull('usuario_id')
            ->where('estado', true)
            ->orderBy('apellidos')
            ->get();

        if ($vinculados->isEmpty()) {
            $this->error('No hay colaboradores vinculados a ningún usuario.');
            return null;
        }

        $this->table(
            ['Usuario ID', 'Colaborador ID', 'Nombre completo', 'Email usuario'],
            $vinculados->map(fn($c) => [
                $c->usuario_id,
                $c->id,
                "{$c->apellidos} {$c->nombres}",
                $c->usuario?->email ?? '—',
            ])->toArray()
        );

        $this->line('');
        $this->line('Vuelve a ejecutar con el usuario_id deseado:');
        $this->line('  <fg=yellow>php artisan altamira:resetear-asistencia-hoy {usuario_id}</>');

        return null;
    }
}
