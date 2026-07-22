<?php

namespace App\Console\Commands;

use App\Models\Cheque;
use Illuminate\Console\Command;

class SeedearCheques extends Command
{
    protected $signature   = 'altamira:seedear-cheques';
    protected $description = 'Inserta 10 cheques de prueba (idempotente)';

    public function handle(): int
    {
        $empresaId = 1;

        $datos = [
            [
                'numero'        => 'CHQ-001',
                'banco_caja_id' => 1,
                'banco'         => 'Banco Pichincha',
                'cuenta'        => '2200412601',
                'monto'         => 1250.00,
                'fecha_emision' => '2026-06-01',
                'fecha_cobro'   => '2026-06-05',
                'beneficiario'  => 'YAMAHA DEL ECUADOR S.A.',
                'estado'        => 'cobrado',
                'observacion'   => 'Pago factura proveedor',
            ],
            [
                'numero'        => 'CHQ-002',
                'banco_caja_id' => 1,
                'banco'         => 'Banco Pichincha',
                'cuenta'        => '2200412601',
                'monto'         => 3800.50,
                'fecha_emision' => '2026-06-05',
                'fecha_cobro'   => null,
                'beneficiario'  => 'MUSICALES ANDINOS CIA. LTDA.',
                'estado'        => 'emitido',
                'observacion'   => 'Anticipo compra instrumentos',
            ],
            [
                'numero'        => 'CHQ-003',
                'banco_caja_id' => 2,
                'banco'         => 'Banco del Pacífico',
                'cuenta'        => '7130045822',
                'monto'         => 920.00,
                'fecha_emision' => '2026-06-08',
                'fecha_cobro'   => null,
                'beneficiario'  => 'SERVICIOS TÉCNICOS RIVERA',
                'estado'        => 'posfechado',
                'observacion'   => 'Servicio mantenimiento',
            ],
            [
                'numero'        => 'CHQ-004',
                'banco_caja_id' => 1,
                'banco'         => 'Banco Pichincha',
                'cuenta'        => '2200412601',
                'monto'         => 540.75,
                'fecha_emision' => '2026-06-10',
                'fecha_cobro'   => '2026-06-11',
                'beneficiario'  => 'SUMINISTROS OFICINA DEL NORTE',
                'estado'        => 'cobrado',
                'observacion'   => null,
            ],
            [
                'numero'        => 'CHQ-005',
                'banco_caja_id' => 2,
                'banco'         => 'Banco del Pacífico',
                'cuenta'        => '7130045822',
                'monto'         => 200.00,
                'fecha_emision' => '2026-06-12',
                'fecha_cobro'   => null,
                'beneficiario'  => 'TRANS NORTE LOGÍSTICA',
                'estado'        => 'anulado',
                'observacion'   => 'Anulado por duplicado',
            ],
            [
                'numero'        => 'CHQ-006',
                'banco_caja_id' => 1,
                'banco'         => 'Banco Pichincha',
                'cuenta'        => '2200412601',
                'monto'         => 7500.00,
                'fecha_emision' => '2026-06-15',
                'fecha_cobro'   => null,
                'beneficiario'  => 'IMPORTADORA SONIDO PRO S.A.',
                'estado'        => 'emitido',
                'observacion'   => 'Pago parcial importación',
            ],
            [
                'numero'        => 'CHQ-007',
                'banco_caja_id' => 2,
                'banco'         => 'Banco del Pacífico',
                'cuenta'        => '7130045822',
                'monto'         => 1100.00,
                'fecha_emision' => '2026-06-17',
                'fecha_cobro'   => '2026-06-20',
                'beneficiario'  => 'ARRIENDO LOCAL COMERCIAL',
                'estado'        => 'cobrado',
                'observacion'   => 'Arriendo junio 2026',
            ],
            [
                'numero'        => 'CHQ-008',
                'banco_caja_id' => 1,
                'banco'         => 'Banco Pichincha',
                'cuenta'        => '2200412601',
                'monto'         => 450.00,
                'fecha_emision' => '2026-06-18',
                'fecha_cobro'   => null,
                'beneficiario'  => 'SERVICIOS GRÁFICOS QUITO',
                'estado'        => 'protestado',
                'observacion'   => 'Devuelto por fondos insuficientes',
            ],
            [
                'numero'        => 'CHQ-009',
                'banco_caja_id' => 2,
                'banco'         => 'Banco del Pacífico',
                'cuenta'        => '7130045822',
                'monto'         => 3200.00,
                'fecha_emision' => '2026-06-20',
                'fecha_cobro'   => null,
                'beneficiario'  => 'ELECTRÓNICA TOTAL CIA. LTDA.',
                'estado'        => 'posfechado',
                'observacion'   => 'Cobro programado 15 julio',
            ],
            [
                'numero'        => 'CHQ-010',
                'banco_caja_id' => 1,
                'banco'         => 'Banco Pichincha',
                'cuenta'        => '2200412601',
                'monto'         => 680.25,
                'fecha_emision' => '2026-06-22',
                'fecha_cobro'   => null,
                'beneficiario'  => 'MANT. VEHÍCULOS ALTAMIRA',
                'estado'        => 'emitido',
                'observacion'   => 'Mantenimiento flota vehicular',
            ],
        ];

        $creados    = 0;
        $existentes = 0;

        foreach ($datos as $data) {
            $cheque = Cheque::firstOrCreate(
                ['numero' => $data['numero'], 'banco_caja_id' => $data['banco_caja_id']],
                array_merge($data, ['empresa_id' => $empresaId, 'created_at' => now()])
            );

            $cheque->wasRecentlyCreated ? $creados++ : $existentes++;
        }

        $this->info("Cheques: {$creados} creados, {$existentes} ya existían.");

        return self::SUCCESS;
    }
}
