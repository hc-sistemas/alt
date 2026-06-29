<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Transportista;
use Illuminate\Database\Seeder;

class PersonasSeeder extends Seeder
{
    public function run(): void
    {
        $matriz = Empresa::where('ruc', '1711293454001')->first();
        $import = Empresa::where('ruc', '1755265848001')->first();

        if (!$matriz) {
            return;
        }

        // Clientes de prueba
        $clientes = [
            [
                'empresa_id' => $matriz->id,
                'identificacion' => '1712345678001',
                'razon_social' => 'Comercial El Éxito S.A.',
                'telefono' => '022345678',
                'email' => 'contacto@elexito.ec',
                'ciudad' => 'Quito',
                'pais' => 'ECUADOR',
                'tiene_credito' => true,
                'dias_credito' => 30,
                'cupo_maximo' => 10000.00,
                'agente_retencion' => false,
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'identificacion' => '0923456789001',
                'razon_social' => 'Eventos & Producciones Guayaquil',
                'telefono' => '042345678',
                'email' => 'info@eventosgye.ec',
                'ciudad' => 'Guayaquil',
                'pais' => 'ECUADOR',
                'tiene_credito' => true,
                'dias_credito' => 15,
                'cupo_maximo' => 5000.00,
                'agente_retencion' => false,
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'identificacion' => '1756789012001',
                'razon_social' => 'Sonido Profesional del Norte Cía. Ltda.',
                'telefono' => '062345678',
                'email' => 'ventas@sonidopro.ec',
                'ciudad' => 'Ibarra',
                'pais' => 'ECUADOR',
                'tiene_credito' => false,
                'dias_credito' => 0,
                'cupo_maximo' => 0,
                'agente_retencion' => true,
                'estado' => true,
            ],
        ];

        foreach ($clientes as $c) {
            Cliente::firstOrCreate(
                ['empresa_id' => $c['empresa_id'], 'identificacion' => $c['identificacion']],
                $c
            );
        }

        // Proveedores de prueba
        $empresaIdImport = $import?->id ?? $matriz->id;

        $proveedores = [
            [
                'empresa_id' => $matriz->id,
                'tipo' => 'nacional',
                'tipo_identificacion' => '04',
                'identificacion' => '1790012345001',
                'razon_social' => 'Distribuidora Nacional de Audio S.A.',
                'telefono' => '022901234',
                'email' => 'ventas@distnaudio.ec',
                'ciudad' => 'Quito',
                'pais' => 'ECUADOR',
                'divisa' => 'USD',
                'tiene_credito' => true,
                'dias_credito' => 45,
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'tipo' => 'nacional',
                'tipo_identificacion' => '04',
                'identificacion' => '0990123456001',
                'razon_social' => 'TecnoImport Ecuador Cía. Ltda.',
                'telefono' => '042901234',
                'email' => 'compras@tecnoimport.ec',
                'ciudad' => 'Guayaquil',
                'pais' => 'ECUADOR',
                'divisa' => 'USD',
                'tiene_credito' => false,
                'dias_credito' => 0,
                'estado' => true,
            ],
            [
                'empresa_id' => $empresaIdImport,
                'tipo' => 'internacional',
                'tipo_identificacion' => '04',
                'identificacion' => 'CN-GZ-001',
                'razon_social' => 'Guangzhou Audio Equipment Co. Ltd.',
                'telefono' => '+86 20 8888 9999',
                'email' => 'export@gzaudio.cn',
                'ciudad' => 'Guangzhou',
                'pais' => 'China',
                'divisa' => 'CNY',
                'tiene_credito' => true,
                'dias_credito' => 60,
                'estado' => true,
            ],
            [
                'empresa_id' => $empresaIdImport,
                'tipo' => 'internacional',
                'tipo_identificacion' => '04',
                'identificacion' => 'EIN-45-678901',
                'razon_social' => 'ProSound USA Inc.',
                'telefono' => '+1 305 555 0100',
                'email' => 'sales@prosoundusa.com',
                'ciudad' => 'Miami',
                'pais' => 'Estados Unidos',
                'divisa' => 'USD',
                'tiene_credito' => true,
                'dias_credito' => 30,
                'estado' => true,
            ],
        ];

        foreach ($proveedores as $p) {
            Proveedor::firstOrCreate(
                ['empresa_id' => $p['empresa_id'], 'identificacion' => $p['identificacion']],
                $p
            );
        }

        // Transportistas de prueba
        $transportistas = [
            [
                'identificacion' => '1790567890001',
                'razon_social' => 'Servicio de Courier Express S.A.',
                'placa' => 'PBZ-1234',
                'telefono' => '099 123 4567',
                'estado' => true,
            ],
            [
                'identificacion' => '1792345678001',
                'razon_social' => 'Transportes Rápidos del Ecuador',
                'placa' => 'QAB-5678',
                'telefono' => '098 765 4321',
                'estado' => true,
            ],
        ];

        foreach ($transportistas as $t) {
            Transportista::firstOrCreate(
                ['identificacion' => $t['identificacion']],
                $t
            );
        }
    }
}
