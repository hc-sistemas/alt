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
                'ruc_cedula' => '1712345678001',
                'nombre' => 'Comercial El Éxito S.A.',
                'telefono' => '022345678',
                'email' => 'contacto@elexito.ec',
                'ciudad' => 'Quito',
                'pais' => 'Ecuador',
                'tiene_credito' => true,
                'dias_credito' => 30,
                'cupo_credito' => 10000.00,
                'es_agente_retencion' => false,
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'ruc_cedula' => '0923456789001',
                'nombre' => 'Eventos & Producciones Guayaquil',
                'telefono' => '042345678',
                'email' => 'info@eventosgye.ec',
                'ciudad' => 'Guayaquil',
                'pais' => 'Ecuador',
                'tiene_credito' => true,
                'dias_credito' => 15,
                'cupo_credito' => 5000.00,
                'es_agente_retencion' => false,
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'ruc_cedula' => '1756789012001',
                'nombre' => 'Sonido Profesional del Norte Cía. Ltda.',
                'telefono' => '062345678',
                'email' => 'ventas@sonidopro.ec',
                'ciudad' => 'Ibarra',
                'pais' => 'Ecuador',
                'tiene_credito' => false,
                'dias_credito' => null,
                'cupo_credito' => null,
                'es_agente_retencion' => true,
                'estado' => true,
            ],
        ];

        foreach ($clientes as $c) {
            Cliente::firstOrCreate(
                ['empresa_id' => $c['empresa_id'], 'ruc_cedula' => $c['ruc_cedula']],
                $c
            );
        }

        // Proveedores de prueba
        $empresaIdImport = $import?->id ?? $matriz->id;

        $proveedores = [
            [
                'empresa_id' => $matriz->id,
                'tipo' => 'nacional',
                'ruc_cedula' => '1790012345001',
                'nombre' => 'Distribuidora Nacional de Audio S.A.',
                'telefono' => '022901234',
                'email' => 'ventas@distnaudio.ec',
                'ciudad' => 'Quito',
                'pais' => 'Ecuador',
                'divisa' => null,
                'tiene_credito' => true,
                'dias_credito' => 45,
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'tipo' => 'nacional',
                'ruc_cedula' => '0990123456001',
                'nombre' => 'TecnoImport Ecuador Cía. Ltda.',
                'telefono' => '042901234',
                'email' => 'compras@tecnoimport.ec',
                'ciudad' => 'Guayaquil',
                'pais' => 'Ecuador',
                'divisa' => null,
                'tiene_credito' => false,
                'dias_credito' => null,
                'estado' => true,
            ],
            [
                'empresa_id' => $empresaIdImport,
                'tipo' => 'internacional',
                'ruc_cedula' => null,
                'nombre' => 'Guangzhou Audio Equipment Co. Ltd.',
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
                'ruc_cedula' => 'EIN-45-678901',
                'nombre' => 'ProSound USA Inc.',
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
                [
                    'empresa_id' => $p['empresa_id'],
                    'nombre' => $p['nombre'],
                    'tipo' => $p['tipo'],
                ],
                $p
            );
        }

        // Transportistas de prueba
        $transportistas = [
            [
                'empresa_id' => $matriz->id,
                'razon_social' => 'Servicio de Courier Express S.A.',
                'ruc' => '1790567890001',
                'placa' => 'PBZ-1234',
                'contacto' => 'Carlos Méndez',
                'telefono' => '099 123 4567',
                'estado' => true,
            ],
            [
                'empresa_id' => $matriz->id,
                'razon_social' => 'Transportes Rápidos del Ecuador',
                'ruc' => '1792345678001',
                'placa' => 'QAB-5678',
                'contacto' => 'Ana Salazar',
                'telefono' => '098 765 4321',
                'estado' => true,
            ],
        ];

        foreach ($transportistas as $t) {
            Transportista::firstOrCreate(
                ['empresa_id' => $t['empresa_id'], 'ruc' => $t['ruc']],
                $t
            );
        }
    }
}
