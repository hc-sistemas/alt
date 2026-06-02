<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Proveedor;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\CuentaPagar;
use App\Models\Importacion;
use App\Models\PlanCuenta;

class SeedearCompras extends Command
{
    protected $signature   = 'altamira:seedear-compras';
    protected $description = 'Crea datos de prueba para el módulo de Compras';

    public function handle(): void
    {
        $empresaId = 1;

        $this->info('🌱 Seeding módulo Compras...');

        // ── PROVEEDORES ─────────────────────────────────────
        $this->info('📦 Creando proveedores...');

        $proveedoresData = [
            // Nacionales
            ['tipo'=>'nacional','identificacion'=>'0990012345001',
             'razon_social'=>'TECNOLOGÍA Y SONIDO S.A.',
             'nombre_comercial'=>'TecnoSound',
             'email'=>'ventas@tecnosound.com','telefono'=>'042-123456',
             'ciudad'=>'Guayaquil','tiene_credito'=>true,'dias_credito'=>30],
            ['tipo'=>'nacional','identificacion'=>'1790045678001',
             'razon_social'=>'DISTRIBUIDORA MUSICAL DEL ECUADOR CIA. LTDA.',
             'nombre_comercial'=>'DisMusicEC',
             'email'=>'info@dismusicec.com','telefono'=>'02-345-6789',
             'ciudad'=>'Quito','tiene_credito'=>true,'dias_credito'=>15],
            ['tipo'=>'nacional','identificacion'=>'1234567890001',
             'razon_social'=>'ILUMINACIÓN PROFESIONAL S.A.',
             'nombre_comercial'=>'IluPro',
             'email'=>'compras@ilupro.com','telefono'=>'02-987-6543',
             'ciudad'=>'Quito','tiene_credito'=>false,'dias_credito'=>0],
            ['tipo'=>'nacional','identificacion'=>'0991234567001',
             'razon_social'=>'CABLES Y ACCESORIOS DEL ECUADOR',
             'nombre_comercial'=>'CablesEC',
             'email'=>'ventas@cablesec.com','telefono'=>'04-567-8901',
             'ciudad'=>'Guayaquil','tiene_credito'=>true,'dias_credito'=>45],
            ['tipo'=>'nacional','identificacion'=>'1791234567001',
             'razon_social'=>'SERVICIOS LOGÍSTICOS ANDINOS S.A.',
             'nombre_comercial'=>'LogiAndina',
             'email'=>'logistica@logiandina.com','telefono'=>'02-111-2222',
             'ciudad'=>'Quito','tiene_credito'=>true,'dias_credito'=>30],
            // Internacionales
            ['tipo'=>'internacional','identificacion'=>'CHN-SHURE-001',
             'razon_social'=>'SHURE INCORPORATED',
             'nombre_comercial'=>'Shure Inc.',
             'email'=>'orders@shure.com','telefono'=>'+1-800-025-5679',
             'ciudad'=>'Niles, Illinois','pais'=>'ESTADOS UNIDOS',
             'divisa'=>'USD','tiene_credito'=>true,'dias_credito'=>60],
            ['tipo'=>'internacional','identificacion'=>'CHN-YAMAHA-001',
             'razon_social'=>'YAMAHA CORPORATION',
             'nombre_comercial'=>'Yamaha Corp.',
             'email'=>'export@yamaha.com','telefono'=>'+81-3-5488-6600',
             'ciudad'=>'Hamamatsu','pais'=>'JAPÓN',
             'divisa'=>'USD','tiene_credito'=>true,'dias_credito'=>90],
            ['tipo'=>'internacional','identificacion'=>'CHN-CHAUVET-001',
             'razon_social'=>'CHAUVET PROFESSIONAL LLC',
             'nombre_comercial'=>'Chauvet Pro',
             'email'=>'sales@chauvetprofessional.com',
             'ciudad'=>'Sunrise, Florida','pais'=>'ESTADOS UNIDOS',
             'divisa'=>'USD','tiene_credito'=>true,'dias_credito'=>60],
        ];

        $proveedores = [];
        foreach ($proveedoresData as $data) {
            $p = Proveedor::firstOrCreate(
                ['empresa_id' => $empresaId,
                 'identificacion' => $data['identificacion']],
                array_merge($data, [
                    'empresa_id'         => $empresaId,
                    'tipo_identificacion'=> $data['tipo'] === 'nacional' ? '04' : '08',
                    'pais'               => $data['pais'] ?? 'ECUADOR',
                    'divisa'             => $data['divisa'] ?? 'USD',
                    'estado'             => true,
                ])
            );
            $proveedores[$data['nombre_comercial']] = $p;
            $this->line("   ✅ Proveedor: {$data['nombre_comercial']}");
        }

        // ── CUENTAS PARA DETALLES ────────────────────────────
        $ctaInventario = PlanCuenta::where('codigo', '1.1.4.1')->first();
        $ctaGasto      = PlanCuenta::where('codigo', '5.2.2.03')->first();
        $ctaRepuestos  = PlanCuenta::where('codigo', '1.1.4.2')->first();

        if (!$ctaInventario) {
            $ctaInventario = PlanCuenta::where('permite_asientos', true)
                ->where('tipo','activo')->first();
        }
        if (!$ctaGasto) {
            $ctaGasto = PlanCuenta::where('permite_asientos', true)
                ->where('tipo','gasto')->first();
        }

        // ── COMPRAS LOCALES ──────────────────────────────────
        $this->info('🧾 Creando compras locales...');

        $comprasData = [
            [
                'proveedor'      => 'TecnoSound',
                'tipo_documento' => 'FAC',
                'num_documento'  => '001-001-000123',
                'num_autorizacion'=> '1234567890123456789012345678901234567890123456789',
                'fecha_emision'  => now()->subDays(45)->toDateString(),
                'dias_credito'   => 30,
                'concepto'       => 'Compra de equipos de audio profesional',
                'detalles' => [
                    ['desc'=>'Mezcladora de audio 32 canales',
                     'qty'=>2, 'precio'=>1850.00, 'iva'=>15],
                    ['desc'=>'Amplificador de potencia 2000W',
                     'qty'=>3, 'precio'=>1200.00, 'iva'=>15],
                    ['desc'=>'Cables XLR profesionales x10',
                     'qty'=>5, 'precio'=>45.00, 'iva'=>15],
                ],
            ],
            [
                'proveedor'      => 'IluPro',
                'tipo_documento' => 'FAC',
                'num_documento'  => '002-001-000456',
                'num_autorizacion'=> '9876543210987654321098765432109876543210987654321',
                'fecha_emision'  => now()->subDays(30)->toDateString(),
                'dias_credito'   => 0,
                'concepto'       => 'Compra de equipos de iluminación',
                'detalles' => [
                    ['desc'=>'Cabeza móvil LED 200W',
                     'qty'=>8, 'precio'=>890.00, 'iva'=>15],
                    ['desc'=>'Moving head spot 300W',
                     'qty'=>4, 'precio'=>1450.00, 'iva'=>15],
                ],
            ],
            [
                'proveedor'      => 'CablesEC',
                'tipo_documento' => 'FAC',
                'num_documento'  => '001-002-000789',
                'num_autorizacion'=> '1111111111111111111111111111111111111111111111111',
                'fecha_emision'  => now()->subDays(20)->toDateString(),
                'dias_credito'   => 45,
                'concepto'       => 'Compra de cables y accesorios varios',
                'detalles' => [
                    ['desc'=>'Cable de poder 3x14 AWG (rollo 100m)',
                     'qty'=>3, 'precio'=>125.00, 'iva'=>15],
                    ['desc'=>'Conectores speakon 4p x50',
                     'qty'=>2, 'precio'=>89.00, 'iva'=>15],
                    ['desc'=>'Rack case 12U con ruedas',
                     'qty'=>2, 'precio'=>320.00, 'iva'=>15],
                ],
            ],
            [
                'proveedor'      => 'LogiAndina',
                'tipo_documento' => 'FAC',
                'num_documento'  => '003-001-000321',
                'num_autorizacion'=> '2222222222222222222222222222222222222222222222222',
                'fecha_emision'  => now()->subDays(15)->toDateString(),
                'dias_credito'   => 30,
                'concepto'       => 'Servicio de transporte y logística',
                'gasto'          => true,
                'detalles' => [
                    ['desc'=>'Flete nacional Guayaquil-Quito',
                     'qty'=>1, 'precio'=>380.00, 'iva'=>15],
                    ['desc'=>'Servicio de carga y descarga',
                     'qty'=>1, 'precio'=>120.00, 'iva'=>15],
                ],
            ],
            [
                'proveedor'      => 'DisMusicEC',
                'tipo_documento' => 'FAC',
                'num_documento'  => '001-003-000654',
                'num_autorizacion'=> '3333333333333333333333333333333333333333333333333',
                'fecha_emision'  => now()->subDays(10)->toDateString(),
                'dias_credito'   => 15,
                'concepto'       => 'Compra de instrumentos y accesorios musicales',
                'detalles' => [
                    ['desc'=>'Controlador DJ profesional',
                     'qty'=>5, 'precio'=>650.00, 'iva'=>15],
                    ['desc'=>'Auriculares DJ closed-back',
                     'qty'=>10,'precio'=>185.00, 'iva'=>15],
                    ['desc'=>'Interfaz de audio USB 4 canales',
                     'qty'=>6, 'precio'=>220.00, 'iva'=>15],
                ],
            ],
            [
                'proveedor'      => 'TecnoSound',
                'tipo_documento' => 'FAC',
                'num_documento'  => '001-001-000200',
                'num_autorizacion'=> '4444444444444444444444444444444444444444444444444',
                'fecha_emision'  => now()->subDays(5)->toDateString(),
                'dias_credito'   => 30,
                'concepto'       => 'Repuestos y suministros para taller técnico',
                'detalles' => [
                    ['desc'=>'Condensadores electrolíticos surtidos',
                     'qty'=>1, 'precio'=>85.00, 'iva'=>15],
                    ['desc'=>'Transistores de potencia MOSFET',
                     'qty'=>1, 'precio'=>120.00, 'iva'=>15],
                    ['desc'=>'Soldadura de estaño 60/40 500g',
                     'qty'=>5, 'precio'=>18.00, 'iva'=>15],
                ],
            ],
        ];

        foreach ($comprasData as $data) {
            $proveedor = $proveedores[$data['proveedor']] ?? null;
            if (!$proveedor) continue;

            $existe = Compra::where('empresa_id', $empresaId)
                ->where('num_documento', $data['num_documento'])
                ->exists();
            if ($existe) {
                $this->line("   ⏭️  Ya existe: {$data['num_documento']}");
                continue;
            }

            // Calcular totales
            $subtotalIva = 0;
            $totalIva    = 0;
            $detalles    = [];

            foreach ($data['detalles'] as $d) {
                $sub  = round($d['qty'] * $d['precio'], 4);
                $iva  = round($sub * $d['iva'] / 100, 4);
                $subtotalIva += $sub;
                $totalIva    += $iva;
                $detalles[]   = [
                    'descripcion'    => $d['desc'],
                    'cantidad'       => $d['qty'],
                    'precio_unitario'=> $d['precio'],
                    'descuento'      => 0,
                    'subtotal'       => $sub,
                    'porcentaje_iva' => $d['iva'],
                    'valor_iva'      => $iva,
                    'total'          => $sub + $iva,
                    'cuenta_id'      => (!empty($data['gasto'])
                        ? $ctaGasto?->id
                        : $ctaInventario?->id),
                ];
            }

            $total = $subtotalIva + $totalIva;
            $fechaVenc = $data['dias_credito'] > 0
                ? now()->subDays(45)->addDays($data['dias_credito'])->toDateString()
                : $data['fecha_emision'];

            $compra = Compra::create([
                'empresa_id'         => $empresaId,
                'proveedor_id'       => $proveedor->id,
                'tipo_documento'     => $data['tipo_documento'],
                'num_documento'      => $data['num_documento'],
                'num_autorizacion'   => $data['num_autorizacion'],
                'fecha_emision'      => $data['fecha_emision'],
                'fecha_registro'     => now()->toDateString(),
                'fecha_vencimiento'  => $fechaVenc,
                'dias_credito'       => $data['dias_credito'],
                'subtotal_0'         => 0,
                'subtotal_iva'       => $subtotalIva,
                'total_iva'          => $totalIva,
                'total'              => $total,
                'gasto_no_deducible' => false,
                'concepto'           => $data['concepto'],
                'estado'             => 'activa',
                'created_by'         => 1,
            ]);

            foreach ($detalles as $det) {
                CompraDetalle::create(array_merge(
                    $det, ['compra_id' => $compra->id]
                ));
            }

            // Crear CxP si es a crédito
            if ($data['dias_credito'] > 0) {
                CuentaPagar::create([
                    'empresa_id'       => $empresaId,
                    'proveedor_id'     => $proveedor->id,
                    'compra_id'        => $compra->id,
                    'monto'            => $total,
                    'saldo'            => $total,
                    'fecha_emision'    => $data['fecha_emision'],
                    'fecha_vencimiento'=> $fechaVenc,
                    'estado'           => 'pendiente',
                ]);
            }

            $this->line("   ✅ Compra: {$data['num_documento']} — \${$total}");
        }

        // ── IMPORTACIONES ────────────────────────────────────
        $this->info('✈️  Creando importaciones...');

        $importacionesData = [
            [
                'nombre'         => 'IMPORTACIÓN SHURE Q1-2026',
                'proveedor'      => 'Shure Inc.',
                'num_invoice'    => 'SHR-2026-0089',
                'agente_aduanero'=> 'Agencia Aduanera Andes S.A.',
                'pais_embarque'  => 'ESTADOS UNIDOS',
                'costo_fob'      => 18500.00,
                'divisa'         => 'USD',
                'fecha_partida'  => now()->subDays(60)->toDateString(),
                'fecha_llegada'  => now()->subDays(35)->toDateString(),
                'fecha_liquidacion'=> now()->subDays(30)->toDateString(),
                'total_costos_extra'=> 3200.00,
                'costo_total'    => 21700.00,
                'metodo_prorrateo'=> 'cantidad',
                'estado'         => 'liquidada',
                'observaciones'  => 'Micrófonos, inalámbricos y accesorios Shure. Liquidación completada.',
            ],
            [
                'nombre'         => 'IMPORTACIÓN YAMAHA Q2-2026',
                'proveedor'      => 'Yamaha Corp.',
                'num_invoice'    => 'YMH-2026-0234',
                'agente_aduanero'=> 'Agencia Aduanera Ecuaduanas',
                'pais_embarque'  => 'JAPÓN',
                'costo_fob'      => 32000.00,
                'divisa'         => 'USD',
                'fecha_partida'  => now()->subDays(25)->toDateString(),
                'fecha_llegada'  => now()->subDays(5)->toDateString(),
                'total_costos_extra'=> 0,
                'costo_total'    => 32000.00,
                'metodo_prorrateo'=> 'precio',
                'estado'         => 'en_aduana',
                'observaciones'  => 'Consolas de mezcla y procesadores de señal Yamaha. En proceso de desaduanización.',
            ],
            [
                'nombre'         => 'IMPORTACIÓN CHAUVET Q2-2026',
                'proveedor'      => 'Chauvet Pro',
                'num_invoice'    => 'CHV-2026-0456',
                'agente_aduanero'=> 'Agencia Aduanera Global Trade',
                'pais_embarque'  => 'ESTADOS UNIDOS',
                'costo_fob'      => 24500.00,
                'divisa'         => 'USD',
                'fecha_partida'  => now()->addDays(5)->toDateString(),
                'total_costos_extra'=> 0,
                'costo_total'    => 24500.00,
                'metodo_prorrateo'=> 'cantidad',
                'estado'         => 'en_transito',
                'observaciones'  => 'Cabezas móviles y controladores DMX Chauvet Pro. En tránsito marítimo.',
            ],
        ];

        foreach ($importacionesData as $data) {
            $proveedor = $proveedores[$data['proveedor']] ?? null;

            $existe = Importacion::where('empresa_id', $empresaId)
                ->where('num_invoice', $data['num_invoice'])
                ->exists();
            if ($existe) {
                $this->line("   ⏭️  Ya existe: {$data['num_invoice']}");
                continue;
            }

            Importacion::create(array_merge($data, [
                'empresa_id'  => $empresaId,
                'proveedor_id'=> $proveedor?->id,
                'created_by'  => 1,
            ]));

            $this->line("   ✅ Importación: {$data['nombre']} [{$data['estado']}]");
        }

        $this->newLine();
        $this->info('✅ Módulo Compras seeded correctamente.');
        $this->line('   Proveedores: ' . Proveedor::where('empresa_id',$empresaId)->count());
        $this->line('   Compras:     ' . Compra::where('empresa_id',$empresaId)->count());
        $this->line('   CxP:         ' . CuentaPagar::where('empresa_id',$empresaId)->count());
        $this->line('   Importaciones:' . Importacion::where('empresa_id',$empresaId)->count());
    }
}
