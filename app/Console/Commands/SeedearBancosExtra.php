<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\BancoCaja;
use App\Models\Cheque;
use App\Models\CierreCaja;
use App\Models\ConciliacionBancaria;
use App\Models\PartidaTransito;
use App\Models\MovimientoBancario;

class SeedearBancosExtra extends Command
{
    protected $signature   = 'altamira:seedear-bancos-extra';
    protected $description = 'Datos extra para Bancos: cheques, cierres, conciliaciones';

    public function handle(): void
    {
        $empresaId = 1;
        $this->info('🏦 Seeding datos extra de Bancos...');

        $bancoPichincha = BancoCaja::where('empresa_id', $empresaId)
            ->where('nombre', 'like', '%Pichincha%')->first();
        $cajGeneral = BancoCaja::where('empresa_id', $empresaId)
            ->where('nombre', 'like', '%General%')->first();

        // ── CHEQUES ──────────────────────────────────────────
        $this->info('  🧾 Cheques...');
        if ($bancoPichincha) {
            $cheques = [
                [
                    'numero'        => '000001',
                    'beneficiario'  => 'TecnoSound S.A.',
                    'banco'         => 'Banco Pichincha',
                    'monto'         => 3500.00,
                    'fecha_emision' => '2026-04-10',
                    'estado'        => 'cobrado',
                    'fecha_cobro'   => '2026-04-12',
                    'observacion'   => null,
                ],
                [
                    'numero'        => '000002',
                    'beneficiario'  => 'IluPro S.A.',
                    'banco'         => 'Banco Pichincha',
                    'monto'         => 1200.00,
                    'fecha_emision' => '2026-05-15',
                    'estado'        => 'emitido',
                    'fecha_cobro'   => null,
                    'observacion'   => null,
                ],
                [
                    'numero'        => '000003',
                    'beneficiario'  => 'Empresa Eléctrica Quito',
                    'banco'         => 'Banco Pichincha',
                    'monto'         => 580.00,
                    'fecha_emision' => '2026-05-20',
                    'estado'        => 'emitido',
                    'fecha_cobro'   => null,
                    'observacion'   => null,
                ],
                [
                    'numero'        => '000004',
                    'beneficiario'  => 'CablesEC S.A.',
                    'banco'         => 'Banco Pichincha',
                    'monto'         => 890.00,
                    'fecha_emision' => '2026-06-01',
                    'estado'        => 'protestado',
                    'fecha_cobro'   => '2026-06-03',
                    'observacion'   => 'Sin fondos suficientes',
                ],
                [
                    'numero'        => '000005',
                    'beneficiario'  => 'DisMusicEC',
                    'banco'         => 'Banco Pichincha',
                    'monto'         => 2100.00,
                    'fecha_emision' => '2026-06-15',
                    'estado'        => 'emitido',
                    'fecha_cobro'   => null,
                    'observacion'   => null,
                ],
                [
                    'numero'        => '000006',
                    'beneficiario'  => 'LogiAndina S.A.',
                    'banco'         => 'Banco Pichincha',
                    'monto'         => 450.00,
                    'fecha_emision' => '2026-07-01',
                    'estado'        => 'cobrado',
                    'fecha_cobro'   => '2026-07-02',
                    'observacion'   => null,
                ],
            ];

            foreach ($cheques as $data) {
                Cheque::firstOrCreate(
                    [
                        'empresa_id'   => $empresaId,
                        'banco_caja_id' => $bancoPichincha->id,
                        'numero'        => $data['numero'],
                    ],
                    [
                        'beneficiario'  => $data['beneficiario'],
                        'banco'         => $data['banco'],
                        'cuenta'        => $bancoPichincha->num_cuenta,
                        'monto'         => $data['monto'],
                        'fecha_emision' => $data['fecha_emision'],
                        'fecha_cobro'   => $data['fecha_cobro'],
                        'estado'        => $data['estado'],
                        'observacion'   => $data['observacion'],
                        'created_at'    => now(),
                    ]
                );
                $this->line("    ✅ Cheque N°{$data['numero']} — {$data['beneficiario']} [{$data['estado']}]");
            }
        } else {
            $this->warn('  ⚠️  Banco Pichincha no encontrado — ejecuta altamira:seedear-bancos primero');
        }

        // ── CIERRES DE CAJA ───────────────────────────────────
        $this->info('  💰 Cierres de caja...');
        if ($cajGeneral) {
            $cierres = [
                [
                    'fecha'           => '2026-06-02',
                    'monto_inicial'   => 500.00,
                    'total_facturado' => 3200.00,
                    'total_cobrado'   => 3180.00,
                    'total_efectivo'  => 1800.00,
                    'total_tarjeta'   => 1380.00,
                    'diferencia'      => -20.00,
                    'estado'          => 'cerrado',
                ],
                [
                    'fecha'           => '2026-06-03',
                    'monto_inicial'   => 500.00,
                    'total_facturado' => 2800.00,
                    'total_cobrado'   => 2800.00,
                    'total_efectivo'  => 2000.00,
                    'total_tarjeta'   => 800.00,
                    'diferencia'      => 0.00,
                    'estado'          => 'cerrado',
                ],
                [
                    'fecha'           => '2026-06-04',
                    'monto_inicial'   => 500.00,
                    'total_facturado' => 4100.00,
                    'total_cobrado'   => 4115.00,
                    'total_efectivo'  => 2500.00,
                    'total_tarjeta'   => 1615.00,
                    'diferencia'      => 15.00,
                    'estado'          => 'cerrado',
                ],
                [
                    'fecha'           => '2026-06-05',
                    'monto_inicial'   => 500.00,
                    'total_facturado' => 1950.00,
                    'total_cobrado'   => 1950.00,
                    'total_efectivo'  => 1200.00,
                    'total_tarjeta'   => 750.00,
                    'diferencia'      => 0.00,
                    'estado'          => 'cerrado',
                ],
                [
                    'fecha'           => '2026-07-07',
                    'monto_inicial'   => 500.00,
                    'total_facturado' => 0.00,
                    'total_cobrado'   => 0.00,
                    'total_efectivo'  => 0.00,
                    'total_tarjeta'   => 0.00,
                    'diferencia'      => 0.00,
                    'estado'          => 'abierto',
                ],
            ];

            foreach ($cierres as $data) {
                $existe = CierreCaja::where('empresa_id', $empresaId)
                    ->where('banco_caja_id', $cajGeneral->id)
                    ->where('fecha', $data['fecha'])
                    ->exists();

                if ($existe) {
                    $this->line("    ⏭️  Ya existe cierre {$data['fecha']}");
                    continue;
                }

                CierreCaja::create([
                    'empresa_id'          => $empresaId,
                    'banco_caja_id'       => $cajGeneral->id,
                    'fecha'               => $data['fecha'],
                    'usuario_apertura_id' => 1,
                    'usuario_cierre_id'   => $data['estado'] === 'cerrado' ? 1 : null,
                    'monto_inicial'       => $data['monto_inicial'],
                    'total_facturado'     => $data['total_facturado'],
                    'total_cobrado'       => $data['total_cobrado'],
                    'total_efectivo'      => $data['total_efectivo'],
                    'total_tarjeta'       => $data['total_tarjeta'],
                    'total_cheque'        => 0,
                    'total_transferencia' => 0,
                    'diferencia'          => $data['diferencia'],
                    'estado'              => $data['estado'],
                    'hora_apertura'       => $data['fecha'] . ' 08:00:00',
                    'hora_cierre'         => $data['estado'] === 'cerrado'
                        ? $data['fecha'] . ' 18:00:00'
                        : null,
                    'observaciones'       => $data['diferencia'] != 0
                        ? 'Diferencia de $' . abs($data['diferencia']) . ' detectada'
                        : null,
                    'created_at'          => now(),
                ]);

                $difLabel = $data['diferencia'] != 0
                    ? "⚠️ Dif: \${$data['diferencia']}"
                    : '✓ Sin diferencia';
                $this->line("    ✅ Cierre {$data['fecha']} — {$difLabel}");
            }
        } else {
            $this->warn('  ⚠️  Caja General no encontrada — ejecuta altamira:seedear-bancos primero');
        }

        // ── CONCILIACIONES BANCARIAS ──────────────────────────
        $this->info('  🔄 Conciliaciones bancarias...');
        if ($bancoPichincha) {
            $movNoConciliados = MovimientoBancario::where('empresa_id', $empresaId)
                ->where('banco_caja_id', $bancoPichincha->id)
                ->where('conciliado', false)
                ->where('anulado', false)
                ->where('fecha', '<', '2026-06-01')
                ->get();

            $saldoSistema = (float) MovimientoBancario::where('empresa_id', $empresaId)
                ->where('banco_caja_id', $bancoPichincha->id)
                ->where('anulado', false)
                ->where('fecha', '<=', '2026-05-31')
                ->selectRaw("SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END) as saldo")
                ->value('saldo') ?? 0;
            $saldoSistema += (float) $bancoPichincha->saldo_inicial;

            $saldoBanco = $saldoSistema - 250.00;

            // Conciliación mayo 2026 — pendiente
            $existe = ConciliacionBancaria::where('empresa_id', $empresaId)
                ->where('banco_caja_id', $bancoPichincha->id)
                ->where('fecha_corte', '2026-05-31')
                ->exists();

            if (!$existe) {
                $conc = ConciliacionBancaria::create([
                    'empresa_id'    => $empresaId,
                    'banco_caja_id' => $bancoPichincha->id,
                    'fecha_corte'   => '2026-05-31',
                    'saldo_banco'   => $saldoBanco,
                    'saldo_sistema' => $saldoSistema,
                    'diferencia'    => $saldoBanco - $saldoSistema,
                    'descripcion'   => 'Conciliación mayo 2026 — pendiente de revisión',
                    'estado'        => 'pendiente',
                    'created_by'    => 1,
                    'created_at'    => now(),
                ]);

                foreach ($movNoConciliados->take(3) as $mov) {
                    PartidaTransito::create([
                        'conciliacion_id' => $conc->id,
                        'tipo'            => 'sistema',
                        'fecha'           => $mov->fecha,
                        'descripcion'     => $mov->descripcion,
                        'monto'           => $mov->monto,
                        'movimiento_id'   => $mov->id,
                        'conciliada'      => false,
                    ]);
                }

                PartidaTransito::create([
                    'conciliacion_id' => $conc->id,
                    'tipo'            => 'banco',
                    'fecha'           => '2026-05-28',
                    'descripcion'     => 'Débito bancario — Comisión mantenimiento cuenta',
                    'monto'           => 15.00,
                    'movimiento_id'   => null,
                    'conciliada'      => false,
                ]);

                $dif = number_format(abs($saldoBanco - $saldoSistema), 2);
                $this->line("    ✅ Conciliación mayo 2026 — Dif: \${$dif}");
            } else {
                $this->line('    ⏭️  Ya existe conciliación mayo 2026');
            }

            // Conciliación abril 2026 — conciliada
            $existe2 = ConciliacionBancaria::where('empresa_id', $empresaId)
                ->where('banco_caja_id', $bancoPichincha->id)
                ->where('fecha_corte', '2026-04-30')
                ->exists();

            if (!$existe2) {
                $saldoAbril = $saldoSistema - 1500.00;
                ConciliacionBancaria::create([
                    'empresa_id'    => $empresaId,
                    'banco_caja_id' => $bancoPichincha->id,
                    'fecha_corte'   => '2026-04-30',
                    'saldo_banco'   => $saldoAbril,
                    'saldo_sistema' => $saldoAbril,
                    'diferencia'    => 0,
                    'descripcion'   => 'Conciliación abril 2026 — Aprobada',
                    'estado'        => 'conciliada',
                    'created_by'    => 1,
                    'created_at'    => now(),
                ]);
                $this->line('    ✅ Conciliación abril 2026 — Conciliada ✓');
            } else {
                $this->line('    ⏭️  Ya existe conciliación abril 2026');
            }
        }

        $this->newLine();
        $this->info('✅ Bancos extra seeded correctamente.');
        $this->line('   Cheques:        ' . Cheque::where('empresa_id', $empresaId)->count());
        $this->line('   Cierres caja:   ' . CierreCaja::where('empresa_id', $empresaId)->count());
        $this->line('   Conciliaciones: ' . ConciliacionBancaria::where('empresa_id', $empresaId)->count());
    }
}
