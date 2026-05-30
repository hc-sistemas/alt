<?php

namespace Database\Seeders;

use App\Models\CentroCosto;
use App\Models\Empresa;
use Illuminate\Database\Seeder;

class EmpresaSeeder extends Seeder
{
    public function run(): void
    {
        $matriz = Empresa::firstOrCreate(
            ['ruc' => '1711293454001'],
            [
                'razon_social' => 'ALTAMIRA LIGHT & SOUND CIA. LTDA.',
                'nombre_comercial' => 'Altamira Light & Sound',
                'direccion_matriz' => 'Quito, Ecuador',
                'email_notificaciones' => 'admin@altamira.com',
                'slogan' => 'Ahora las luces se ven Diferente',
                'ambiente_sri' => 1,
                'cod_establecimiento' => '001',
                'cod_punto_emision' => '001',
                'obligado_contabilidad' => true,
                'estado' => true,
            ]
        );

        $import = Empresa::firstOrCreate(
            ['ruc' => '1755265848001'],
            [
                'razon_social' => 'ALTAMIRA IMPORT CIA. LTDA.',
                'nombre_comercial' => 'Altamira Import',
                'direccion_matriz' => 'Quito, Ecuador',
                'email_notificaciones' => 'import@altamira.com',
                'slogan' => 'Solo un DJ sabe lo que otro DJ necesita',
                'ambiente_sri' => 1,
                'cod_establecimiento' => '001',
                'cod_punto_emision' => '001',
                'obligado_contabilidad' => true,
                'estado' => true,
            ]
        );

        CentroCosto::firstOrCreate(
            ['codigo' => 'MATRIZ'],
            ['empresa_id' => $matriz->id, 'nombre' => 'Altamira Matriz', 'tipo' => 'empresa', 'estado' => true]
        );

        CentroCosto::firstOrCreate(
            ['codigo' => 'IMPORT'],
            ['empresa_id' => $import->id, 'nombre' => 'Altamira Import', 'tipo' => 'empresa', 'estado' => true]
        );

        CentroCosto::firstOrCreate(
            ['codigo' => 'FIX'],
            ['empresa_id' => $matriz->id, 'nombre' => 'Altamira Fix', 'tipo' => 'centro_costo_interno', 'es_taller' => true, 'estado' => true]
        );
    }
}
