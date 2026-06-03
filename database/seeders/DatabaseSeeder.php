<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EmpresaSeeder::class,
            PerfilSeeder::class,
            ModuloSeeder::class,
            TiposAprobacionSeeder::class,
            UsuarioSeeder::class,
            PlanCuentaSeeder::class,
            PersonasSeeder::class,
        ]);
    }
}
