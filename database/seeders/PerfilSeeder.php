<?php

namespace Database\Seeders;

use App\Models\LimiteDescuento;
use App\Models\Perfil;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class PerfilSeeder extends Seeder
{
    public function run(): void
    {
        $perfiles = [
            ['nombre' => 'super_admin', 'descripcion' => 'Acceso total al sistema', 'estado' => true],
            ['nombre' => 'admin', 'descripcion' => 'Administrador general', 'estado' => true],
            ['nombre' => 'contador', 'descripcion' => 'Módulos contables y financieros', 'estado' => true],
            ['nombre' => 'vendedor', 'descripcion' => 'Módulo de ventas', 'estado' => true],
            ['nombre' => 'bodeguero', 'descripcion' => 'Módulo de inventario', 'estado' => true],
            ['nombre' => 'tecnico', 'descripcion' => 'Módulo de taller', 'estado' => true],
        ];

        // Columnas reales de limites_descuento: perfil_id, porcentaje_maximo,
        // puede_aprobar, porcentaje_aprobacion_max (sin empresa_id).
        //
        // Valores de EJEMPLO para poder probar el flujo de descuento especial
        // de punta a punta — deben confirmarse con el negocio antes de
        // considerarse la configuración final de producción.
        $limites = [
            'super_admin' => ['porcentaje_maximo' => 100, 'puede_aprobar' => true,  'porcentaje_aprobacion_max' => 100],
            'admin'       => ['porcentaje_maximo' => 30,  'puede_aprobar' => true,  'porcentaje_aprobacion_max' => 50],
            'contador'    => ['porcentaje_maximo' => 0,   'puede_aprobar' => false, 'porcentaje_aprobacion_max' => 0],
            'vendedor'    => ['porcentaje_maximo' => 5,   'puede_aprobar' => false, 'porcentaje_aprobacion_max' => 0],
            'bodeguero'   => ['porcentaje_maximo' => 0,   'puede_aprobar' => false, 'porcentaje_aprobacion_max' => 0],
            'tecnico'     => ['porcentaje_maximo' => 0,   'puede_aprobar' => false, 'porcentaje_aprobacion_max' => 0],
        ];

        $tieneEsSistema = Schema::hasColumn('perfiles', 'es_sistema');
        $tieneLimitesPuedeAprobar = Schema::hasColumn('limites_descuento', 'puede_aprobar');
        $tieneLimitesDescuentoPct = Schema::hasColumn('limites_descuento', 'porcentaje_maximo');

        foreach ($perfiles as $data) {
            if ($tieneEsSistema) {
                $data['es_sistema'] = true;
            }
            $perfil = Perfil::firstOrCreate(['nombre' => $data['nombre']], $data);

            if ($tieneLimitesPuedeAprobar && $tieneLimitesDescuentoPct) {
                LimiteDescuento::firstOrCreate(['perfil_id' => $perfil->id], $limites[$perfil->nombre]);
            }
        }
    }
}
