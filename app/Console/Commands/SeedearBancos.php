<?php

namespace App\Console\Commands;

use App\Models\BancoCaja;
use App\Models\DatafastLote;
use App\Models\MovimientoBancario;
use App\Models\PlanCuenta;
use Illuminate\Console\Command;

class SeedearBancos extends Command
{
    protected $signature   = 'altamira:seedear-bancos';
    protected $description = 'Crea datos de prueba para el módulo de Bancos';

    public function handle(): void
    {
        $empresaId = 1;
        $this->info('Seeding módulo Bancos...');

        $ctaBancos  = PlanCuenta::where('codigo', '1.1.1.3')->first();
        $ctaCaja    = PlanCuenta::where('codigo', '1.1.1.1')->first();
        $ctaVoucher = PlanCuenta::where('codigo', '1.1.1.5')->first();

        $bancosData = [
            ['tipo' => 'banco', 'nombre' => 'Banco Pichincha Cta. Cte.',
             'num_cuenta' => '2100456789', 'tipo_cuenta' => 'corriente',
             'saldo_inicial' => 25000.00, 'cuenta_id' => $ctaBancos?->id],
            ['tipo' => 'banco', 'nombre' => 'Banco del Pacífico Cta. Ahorros',
             'num_cuenta' => '0987654321', 'tipo_cuenta' => 'ahorros',
             'saldo_inicial' => 18500.50, 'cuenta_id' => $ctaBancos?->id],
            ['tipo' => 'banco', 'nombre' => 'Banco Guayaquil Cta. Cte.',
             'num_cuenta' => '1122334455', 'tipo_cuenta' => 'corriente',
             'saldo_inicial' => 8200.00, 'cuenta_id' => $ctaBancos?->id],
            ['tipo' => 'caja', 'nombre' => 'Caja General Matriz',
             'num_cuenta' => null, 'tipo_cuenta' => null,
             'saldo_inicial' => 2500.00, 'cuenta_id' => $ctaCaja?->id],
            ['tipo' => 'caja', 'nombre' => 'Caja General Taller',
             'num_cuenta' => null, 'tipo_cuenta' => null,
             'saldo_inicial' => 800.00, 'cuenta_id' => $ctaCaja?->id],
            ['tipo' => 'caja_chica', 'nombre' => 'Caja Chica Administración',
             'num_cuenta' => null, 'tipo_cuenta' => null,
             'saldo_inicial' => 200.00, 'cuenta_id' => $ctaCaja?->id],
            ['tipo' => 'tarjeta', 'nombre' => 'Datafast Terminal Matriz',
             'num_cuenta' => 'TRM-001', 'tipo_cuenta' => null,
             'saldo_inicial' => 0, 'cuenta_id' => $ctaVoucher?->id],
        ];

        $bancos = [];
        foreach ($bancosData as $data) {
            $b = BancoCaja::firstOrCreate(
                ['empresa_id' => $empresaId, 'nombre' => $data['nombre']],
                array_merge($data, [
                    'empresa_id'   => $empresaId,
                    'saldo_actual' => $data['saldo_inicial'],
                    'estado'       => true,
                ])
            );
            $bancos[$data['nombre']] = $b;
            $this->line("   {$data['tipo']}: {$data['nombre']}");
        }

        $this->info('Creando movimientos bancarios...');
        $ctaProveedores = PlanCuenta::where('codigo', '2.1.1.1')->first();
        $ctaClientes    = PlanCuenta::where('codigo', '1.1.3.1')->first();
        $ctaGastos      = PlanCuenta::where('codigo', '5.2.2.03')->first();

        $movimientosData = [
            ['banco' => 'Banco Pichincha Cta. Cte.', 'tipo' => 'egreso',
             'sub_tipo' => 'transferencia', 'fecha' => now()->subDays(20)->toDateString(),
             'monto' => 3500.00, 'beneficiario' => 'TecnoSound S.A.',
             'descripcion' => 'Pago factura 001-001-000123', 'num_documento' => 'TRF-001',
             'cuenta' => $ctaProveedores?->id],
            ['banco' => 'Banco Pichincha Cta. Cte.', 'tipo' => 'ingreso',
             'sub_tipo' => 'deposito', 'fecha' => now()->subDays(15)->toDateString(),
             'monto' => 8200.00, 'beneficiario' => 'Cliente Varios',
             'descripcion' => 'Depósito ventas semana', 'num_documento' => 'DEP-001',
             'cuenta' => $ctaClientes?->id],
            ['banco' => 'Banco del Pacífico Cta. Ahorros', 'tipo' => 'egreso',
             'sub_tipo' => 'transferencia', 'fecha' => now()->subDays(10)->toDateString(),
             'monto' => 1200.00, 'beneficiario' => 'IluPro S.A.',
             'descripcion' => 'Pago factura equipos iluminación', 'num_documento' => 'TRF-002',
             'cuenta' => $ctaProveedores?->id],
            ['banco' => 'Banco Pichincha Cta. Cte.', 'tipo' => 'egreso',
             'sub_tipo' => 'cheque', 'fecha' => now()->subDays(8)->toDateString(),
             'monto' => 580.00, 'beneficiario' => 'Empresa Eléctrica Quito',
             'descripcion' => 'Pago planilla eléctrica mayo', 'num_documento' => 'CHQ-0001',
             'num_cheque' => '000001', 'cuenta' => $ctaGastos?->id],
            ['banco' => 'Banco Guayaquil Cta. Cte.', 'tipo' => 'ingreso',
             'sub_tipo' => 'transferencia', 'fecha' => now()->subDays(5)->toDateString(),
             'monto' => 4500.00, 'beneficiario' => 'Distribuidora Musical',
             'descripcion' => 'Cobro factura cliente', 'num_documento' => 'TRF-003',
             'cuenta' => $ctaClientes?->id],
            ['banco' => 'Caja General Matriz', 'tipo' => 'ingreso',
             'sub_tipo' => 'efectivo', 'fecha' => now()->subDays(3)->toDateString(),
             'monto' => 1850.00, 'beneficiario' => 'Ventas mostrador',
             'descripcion' => 'Ventas efectivo del día', 'num_documento' => null,
             'cuenta' => $ctaClientes?->id],
            ['banco' => 'Caja General Matriz', 'tipo' => 'egreso',
             'sub_tipo' => 'efectivo', 'fecha' => now()->subDays(2)->toDateString(),
             'monto' => 120.00, 'beneficiario' => 'Varios',
             'descripcion' => 'Gastos menores oficina', 'num_documento' => null,
             'cuenta' => $ctaGastos?->id],
            ['banco' => 'Banco Pichincha Cta. Cte.', 'tipo' => 'ingreso',
             'sub_tipo' => 'deposito', 'fecha' => now()->subDays(1)->toDateString(),
             'monto' => 12500.00, 'beneficiario' => 'Depósito ventas',
             'descripcion' => 'Ventas semana 23-30 mayo', 'num_documento' => 'DEP-002',
             'cuenta' => $ctaClientes?->id],
        ];

        foreach ($movimientosData as $data) {
            $banco = $bancos[$data['banco']] ?? null;
            if (!$banco) continue;

            MovimientoBancario::create([
                'empresa_id'              => $empresaId,
                'banco_caja_id'           => $banco->id,
                'tipo'                    => $data['tipo'],
                'sub_tipo'                => $data['sub_tipo'],
                'fecha'                   => $data['fecha'],
                'monto'                   => $data['monto'],
                'beneficiario'            => $data['beneficiario'],
                'descripcion'             => $data['descripcion'],
                'num_documento'           => $data['num_documento'] ?? null,
                'num_cheque'              => $data['num_cheque'] ?? null,
                'cuenta_contrapartida_id' => $data['cuenta'],
                'conciliado'              => false,
                'anulado'                 => false,
                'created_by'              => 1,
            ]);

            $banco->actualizarSaldo($data['monto'], $data['tipo']);
            $this->line("   {$data['tipo']}: {$data['descripcion']} \${$data['monto']}");
        }

        $this->info('Creando lotes Datafast...');
        $terminal = $bancos['Datafast Terminal Matriz'] ?? null;
        if ($terminal) {
            $lotesData = [
                ['numero_lote' => 'LOT-20260525', 'fecha' => now()->subDays(8)->toDateString(),
                 'total_vouchers' => 3250.00, 'estado' => 'liquidado'],
                ['numero_lote' => 'LOT-20260528', 'fecha' => now()->subDays(5)->toDateString(),
                 'total_vouchers' => 1890.50, 'estado' => 'liquidado'],
                ['numero_lote' => 'LOT-20260601', 'fecha' => now()->subDays(1)->toDateString(),
                 'total_vouchers' => 2100.00, 'estado' => 'pendiente'],
            ];

            foreach ($lotesData as $data) {
                DatafastLote::firstOrCreate(
                    ['empresa_id' => $empresaId, 'numero_lote' => $data['numero_lote']],
                    array_merge($data, [
                        'empresa_id'    => $empresaId,
                        'banco_caja_id' => $terminal->id,
                        'created_by'    => 1,
                        'created_at'    => now(),
                    ])
                );
                $this->line("   Lote: {$data['numero_lote']} [{$data['estado']}]");
            }
        }

        $this->newLine();
        $this->info('Módulo Bancos seeded correctamente.');
        $this->line('   Bancos/Cajas:   ' . BancoCaja::where('empresa_id', $empresaId)->count());
        $this->line('   Movimientos:    ' . MovimientoBancario::where('empresa_id', $empresaId)->count());
        $this->line('   Lotes Datafast: ' . DatafastLote::where('empresa_id', $empresaId)->count());
    }
}
