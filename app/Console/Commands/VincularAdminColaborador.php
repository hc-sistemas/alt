<?php

namespace App\Console\Commands;

use App\Models\Colaborador;
use App\Models\Horario;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VincularAdminColaborador extends Command
{
    protected $signature = 'altamira:vincular-admin-colaborador
                            {--email=admin@altamira.com : Email del usuario a vincular}
                            {--force : Forzar re-vinculación aunque ya exista}';

    protected $description = 'Vincula el usuario admin (o el indicado) con un colaborador de prueba para habilitar el Timbre Digital.';

    public function handle(): int
    {
        $email = $this->option('email');

        $usuario = Usuario::where('email', $email)->first();
        if (!$usuario) {
            $this->error("No se encontró el usuario con email: {$email}");
            return self::FAILURE;
        }

        $this->line("Usuario: [{$usuario->id}] {$usuario->nombre} <{$usuario->email}>");

        // Verificar si ya tiene colaborador vinculado
        $existente = Colaborador::where('usuario_id', $usuario->id)->first();
        if ($existente && !$this->option('force')) {
            $this->info("Ya tiene colaborador vinculado: [{$existente->id}] {$existente->apellidos} {$existente->nombres}");
            $this->line('Usa --force para re-vincular.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($usuario) {
            // 1. Asegurar que exista al menos un horario
            $horario = Horario::first();
            if (!$horario) {
                $horario = Horario::create([
                    'descripcion'         => 'Jornada General (Lun-Vie)',
                    'hora_entrada'        => '08:00:00',
                    'hora_salida'         => '17:00:00',
                    'tolerancia_minutos'  => 5,
                    'lunes'               => true,
                    'martes'              => true,
                    'miercoles'           => true,
                    'jueves'              => true,
                    'viernes'             => true,
                    'sabado'              => false,
                    'domingo'             => false,
                ]);
                $this->line("  + Horario creado: [{$horario->id}] {$horario->descripcion}");
            } else {
                $this->line("  ✓ Horario existente: [{$horario->id}] {$horario->descripcion}");
            }

            // 2. Crear o recuperar el colaborador del admin
            $colaborador = Colaborador::firstOrCreate(
                ['cedula_ruc' => '9999999999901'],
                [
                    'empresa_id'    => $usuario->empresa_id,
                    'horario_id'    => $horario->id,
                    'apellidos'     => 'Sistema',
                    'nombres'       => 'Administrador',
                    'cargo'         => 'Administrador del Sistema',
                    'departamento'  => 'Sistemas',
                    'fecha_ingreso' => now()->toDateString(),
                    'sueldo_base'   => 1000.00,
                    'estado'        => true,
                ]
            );

            // Asegurarnos de que tenga horario si ya existía sin él
            if (!$colaborador->horario_id) {
                $colaborador->update(['horario_id' => $horario->id]);
            }

            $this->line("  ✓ Colaborador: [{$colaborador->id}] {$colaborador->apellidos} {$colaborador->nombres}");

            // 3. Quitar vínculo previo de este usuario
            Colaborador::where('usuario_id', $usuario->id)
                ->where('id', '!=', $colaborador->id)
                ->update(['usuario_id' => null]);

            // 4. Vincular
            $colaborador->update(['usuario_id' => $usuario->id]);

            $this->info("  ✓ Vinculación completada: usuario #{$usuario->id} → colaborador #{$colaborador->id}");
        });

        // 5. Verificar en BD
        $check = DB::selectOne(
            'SELECT c.id, c.apellidos, c.nombres, c.usuario_id, c.horario_id
             FROM colaboradores c
             WHERE c.usuario_id = ?',
            [$usuario->id]
        );

        if ($check) {
            $this->newLine();
            $this->info('✔ Verificación OK:');
            $this->table(
                ['colaborador_id', 'apellidos', 'nombres', 'usuario_id', 'horario_id'],
                [[(string) $check->id, $check->apellidos, $check->nombres, (string) $check->usuario_id, (string) $check->horario_id]]
            );
        } else {
            $this->error('✗ Verificación falló: no se encontró el vínculo en BD.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
