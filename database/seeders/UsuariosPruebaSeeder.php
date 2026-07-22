<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UsuariosPruebaSeeder extends Seeder
{
    /**
     * Un usuario de prueba por perfil (admin, contador, bodeguero, tecnico),
     * cada uno vinculado a UNA sola empresa para poder probar que los
     * permisos por perfil+empresa se aplican de forma independiente.
     * (super_admin y vendedor ya se crean en UsuarioSeeder).
     */
    public function run(): void
    {
        $matriz = Empresa::where('ruc', '1711293454001')->first();
        $import = Empresa::where('ruc', '1755265848001')->first();

        if (!$matriz || !$import) {
            $this->command->warn('Faltan empresas, saltando UsuariosPruebaSeeder.');
            return;
        }

        $usuarios = [
            ['perfil' => 'admin',      'email' => 'administrador@altamira.com', 'nombre' => 'Admin Prueba',      'username' => 'admin_prueba',      'empresa' => $import],
            ['perfil' => 'contador',   'email' => 'contador@altamira.com',      'nombre' => 'Contador Prueba',   'username' => 'contador_prueba',   'empresa' => $import],
            ['perfil' => 'bodeguero',  'email' => 'bodeguero@altamira.com',     'nombre' => 'Bodeguero Prueba',  'username' => 'bodeguero_prueba',  'empresa' => $matriz],
            ['perfil' => 'tecnico',    'email' => 'tecnico@altamira.com',       'nombre' => 'Tecnico Prueba',    'username' => 'tecnico_prueba',    'empresa' => $matriz],
        ];

        foreach ($usuarios as $u) {
            $perfil = Perfil::where('nombre', $u['perfil'])->first();
            if (!$perfil) {
                $this->command->warn("Perfil {$u['perfil']} no encontrado, saltando {$u['email']}.");
                continue;
            }

            $yaExiste = Usuario::where('email', $u['email'])->exists();
            $password = Str::password(16, true, true, false, false);

            $usuario = Usuario::firstOrCreate(
                ['email' => $u['email']],
                [
                    'empresa_id' => $u['empresa']->id,
                    'perfil_id' => $perfil->id,
                    'nombre' => $u['nombre'],
                    'username' => $u['username'],
                    'password' => Hash::make($password),
                    'estado' => true,
                ]
            );

            if (!$yaExiste) {
                $this->command->warn("Usuario {$u['email']} creado ({$u['perfil']} — {$u['empresa']->nombre_comercial}). Password: {$password} (guárdalo ahora, no se muestra de nuevo).");
            }

            if (Schema::hasTable('empresa_usuario')) {
                // Solo la empresa asignada, para probar acceso de una sola empresa.
                $usuario->empresas()->sync([$u['empresa']->id]);
            }
        }
    }
}
