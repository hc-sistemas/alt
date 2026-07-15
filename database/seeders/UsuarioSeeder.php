<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        $matriz = Empresa::where('ruc', '1711293454001')->first();
        $import = Empresa::where('ruc', '1755265848001')->first();
        $perfilAdmin = Perfil::where('nombre', 'super_admin')->first();
        $perfilVendedor = Perfil::where('nombre', 'vendedor')->first();

        if (!$matriz || !$perfilAdmin) {
            $this->command->warn('Empresa o perfil no encontrado, saltando UsuarioSeeder.');
            return;
        }

        // Nunca hardcodear una contraseña de prueba fija en el repo (público): cada
        // corrida genera una nueva y la muestra una sola vez por consola. Puede fijarse
        // con las variables de entorno SEED_ADMIN_PASSWORD / SEED_ADMIN_PIN si se
        // prefiere un valor conocido en un entorno local no público.
        $yaExisteAdmin = Usuario::where('email', 'admin@altamira.com')->exists();
        $adminPassword = env('SEED_ADMIN_PASSWORD') ?: Str::password(16, true, true, false, false);
        $adminPin      = env('SEED_ADMIN_PIN') ?: (string) random_int(100000, 999999);

        $superAdmin = Usuario::firstOrCreate(
            ['email' => 'admin@altamira.com'],
            [
                'empresa_id' => $matriz->id,
                'perfil_id' => $perfilAdmin->id,
                'nombre' => 'Administrador Sistema',
                'username' => 'admin',
                'password' => Hash::make($adminPassword),
                'codigo_aprobacion' => Hash::make($adminPin),
                'estado' => true,
            ]
        );

        if (!$yaExisteAdmin) {
            $this->command->warn("Usuario admin@altamira.com creado. Password: {$adminPassword} — PIN: {$adminPin} (guárdalos ahora, no se muestran de nuevo).");
        }

        // Agregar relación empresa_usuario si la tabla existe
        if (Schema::hasTable('empresa_usuario')) {
            $empresaIds = array_filter([$matriz->id, $import?->id]);
            $superAdmin->empresas()->syncWithoutDetaching($empresaIds);
        }

        if ($perfilVendedor) {
            $yaExisteVendedor = Usuario::where('email', 'vendedor@altamira.com')->exists();
            $vendedorPassword = env('SEED_VENDEDOR_PASSWORD') ?: Str::password(16, true, true, false, false);

            $vendedor = Usuario::firstOrCreate(
                ['email' => 'vendedor@altamira.com'],
                [
                    'empresa_id' => $matriz->id,
                    'perfil_id' => $perfilVendedor->id,
                    'nombre' => 'Vendedor Prueba',
                    'username' => 'vendedor',
                    'password' => Hash::make($vendedorPassword),
                    'estado' => true,
                ]
            );

            if (!$yaExisteVendedor) {
                $this->command->warn("Usuario vendedor@altamira.com creado. Password: {$vendedorPassword} (guárdalo ahora, no se muestra de nuevo).");
            }

            if (Schema::hasTable('empresa_usuario')) {
                $vendedor->empresas()->syncWithoutDetaching([$matriz->id]);
            }
        }
    }
}
