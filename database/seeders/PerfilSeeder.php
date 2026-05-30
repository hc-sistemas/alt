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

        // Columnas reales de limites_descuento
        $limites = [
            'super_admin' => ['descuento_maximo_pct' => 100, 'puede_aprobar' => true, 'descuento_aprobacion_max_pct' => 100],
            'admin'       => ['descuento_maximo_pct' => 30,  'puede_aprobar' => true, 'descuento_aprobacion_max_pct' => 50],
            'contador'    => ['descuento_maximo_pct' => 0,   'puede_aprobar' => false, 'descuento_aprobacion_max_pct' => 0],
            'vendedor'    => ['descuento_maximo_pct' => 5,   'puede_aprobar' => false, 'descuento_aprobacion_max_pct' => 0],
            'bodeguero'   => ['descuento_maximo_pct' => 0,   'puede_aprobar' => false, 'descuento_aprobacion_max_pct' => 0],
            'tecnico'     => ['descuento_maximo_pct' => 0,   'puede_aprobar' => false, 'descuento_aprobacion_max_pct' => 0],
        ];

        $tieneEsSistema = Schema::hasColumn('perfiles', 'es_sistema');
        $tieneLimitesPuedeAprobar = Schema::hasColumn('limites_descuento', 'puede_aprobar');
        $tieneLimitesDescuentoPct = Schema::hasColumn('limites_descuento', 'descuento_maximo_pct');
        $tieneLimitesEmpresaId = Schema::hasColumn('limites_descuento', 'empresa_id');

        // Obtener la primera empresa para asociar límites
        $primeraEmpresaId = \App\Models\Empresa::first()?->id;

        foreach ($perfiles as $data) {
            if ($tieneEsSistema) {
                $data['es_sistema'] = true;
            }
            $perfil = Perfil::firstOrCreate(['nombre' => $data['nombre']], $data);

            if ($tieneLimitesPuedeAprobar && $tieneLimitesDescuentoPct) {
                $limiteData = $limites[$perfil->nombre];
                $buscar = ['perfil_id' => $perfil->id];
                if ($tieneLimitesEmpresaId && $primeraEmpresaId) {
                    $buscar['empresa_id'] = $primeraEmpresaId;
                    $limiteData['empresa_id'] = $primeraEmpresaId;
                }
                LimiteDescuento::firstOrCreate($buscar, $limiteData);
            }
        }
    }
}
