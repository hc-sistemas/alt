<?php

namespace App\Console\Commands;

use App\Models\Colaborador;
use App\Models\Usuario;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SincronizarUsuariosColaboradores extends Command
{
    protected $signature = 'rrhh:sincronizar-usuarios
        {--dry-run : Solo muestra qué se haría, sin escribir en la base de datos}
        {--excluir-perfil=* : Nombre de perfil a omitir (ej. --excluir-perfil=contador)}';

    protected $description = 'Crea la ficha de colaborador de cada usuario que no la tenga y repara vínculos desalineados';

    public function handle(): int
    {
        $dry      = (bool) $this->option('dry-run');
        $excluir  = (array) $this->option('excluir-perfil');
        $creados  = 0;
        $reparados = 0;

        Usuario::with('perfil')->orderBy('id')->each(function (Usuario $u) use ($dry, $excluir, &$creados, &$reparados) {
            $colab = Colaborador::where('usuario_id', $u->id)->first();

            // 1. Ya tiene ficha: solo asegurar que las dos columnas coincidan.
            if ($colab) {
                if ((int) $u->colaborador_id !== (int) $colab->id) {
                    $this->line("  reparar vínculo: {$u->username} ↔ colaborador #{$colab->id}");
                    if (!$dry) {
                        Usuario::where('id', $u->id)->update(['colaborador_id' => $colab->id]);
                    }
                    $reparados++;
                }
                return;
            }

            if (in_array($u->perfil?->nombre, $excluir, true)) {
                $this->line("  omitido (perfil excluido): {$u->username}");
                return;
            }

            // 2. No tiene ficha: crear una provisional pendiente de completar.
            $this->line("  crear ficha: {$u->username} ({$u->nombre})");
            $creados++;

            if ($dry) {
                return;
            }

            DB::transaction(function () use ($u) {
                $colab = Colaborador::create([
                    'empresa_id'    => $u->empresa_id,
                    'apellidos'     => $u->nombre,
                    'nombres'       => '(completar)',
                    'cedula_ruc'    => Colaborador::PREFIJO_CEDULA_PENDIENTE . str_pad((string) $u->id, 6, '0', STR_PAD_LEFT),
                    'email'         => Colaborador::where('email', $u->email)->exists() ? null : $u->email,
                    'telefono'      => $u->telefono,
                    'fecha_ingreso' => ($u->created_at ?? now())->toDateString(),
                    'sueldo_base'   => 0,
                    'estado'        => (bool) $u->estado,
                ]);
                $colab->vincularUsuario($u);
            });
        });

        $prefijo = $dry ? '[dry-run] ' : '';
        $this->info("{$prefijo}Fichas creadas: {$creados} · vínculos reparados: {$reparados}");
        if ($creados > 0) {
            $this->warn('Las fichas nuevas tienen cédula provisional (PEND-…) y sueldo 0: completarlas en RRHH → Colaboradores. No entran a nómina hasta tener sueldo.');
        }

        return self::SUCCESS;
    }
}
