<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Perfil;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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

        $superAdmin = Usuario::firstOrCreate(
            ['email' => 'admin@altamira.com'],
            [
                'empresa_id' => $matriz->id,
                'perfil_id' => $perfilAdmin->id,
                'nombre' => 'Administrador Sistema',
                'username' => 'admin',
                'password' => Hash::make('Altamira2026*'),
                'codigo_aprobacion' => Hash::make('1234'),
                'estado' => true,
            ]
        );

        // Agregar relación empresa_usuario si la tabla existe
        if (Schema::hasTable('empresa_usuario')) {
            $empresaIds = array_filter([$matriz->id, $import?->id]);
            $superAdmin->empresas()->syncWithoutDetaching($empresaIds);
        }

        if ($perfilVendedor) {
            $vendedor = Usuario::firstOrCreate(
                ['email' => 'vendedor@altamira.com'],
                [
                    'empresa_id' => $matriz->id,
                    'perfil_id' => $perfilVendedor->id,
                    'nombre' => 'Vendedor Prueba',
                    'username' => 'vendedor',
                    'password' => Hash::make('Vendedor2026*'),
                    'estado' => true,
                ]
            );

            if (Schema::hasTable('empresa_usuario')) {
                $vendedor->empresas()->syncWithoutDetaching([$matriz->id]);
            }
        }
    }
}
