<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EjercicioContable;
use App\Models\AsientoContable;
use App\Models\AsientoDetalle;
use App\Models\PlanCuenta;
use App\Models\ParametroContable;

class SeedearContabilidad extends Command
{
    protected $signature   = 'altamira:seedear-contabilidad';
    protected $description = 'Datos de prueba para Contabilidad';

    public function handle(): void
    {
        $empresaId = 1;
        $this->info('📒 Seeding Contabilidad...');

        // ── 1. EJERCICIOS CONTABLES ──────────────────────────
        $this->info('  📅 Ejercicios contables...');
        $meses = [
            ['anio' => 2026, 'mes' => 1, 'estado' => 'cerrado'],
            ['anio' => 2026, 'mes' => 2, 'estado' => 'cerrado'],
            ['anio' => 2026, 'mes' => 3, 'estado' => 'cerrado'],
            ['anio' => 2026, 'mes' => 4, 'estado' => 'cerrado'],
            ['anio' => 2026, 'mes' => 5, 'estado' => 'cerrado'],
            ['anio' => 2026, 'mes' => 6, 'estado' => 'cerrado'],
            ['anio' => 2026, 'mes' => 7, 'estado' => 'abierto'],
        ];

        $nombres = [
            1 => 'Enero',    2 => 'Febrero',   3 => 'Marzo',
            4 => 'Abril',    5 => 'Mayo',       6 => 'Junio',
            7 => 'Julio',    8 => 'Agosto',     9 => 'Septiembre',
            10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        $ejercicioIds = [];
        foreach ($meses as $m) {
            $ej = EjercicioContable::firstOrCreate(
                ['empresa_id' => $empresaId, 'anio' => $m['anio'], 'mes' => $m['mes']],
                [
                    'descripcion'    => $nombres[$m['mes']] . ' ' . $m['anio'],
                    'fecha_apertura' => "{$m['anio']}-" . str_pad($m['mes'], 2, '0', STR_PAD_LEFT) . "-01",
                    'fecha_cierre'   => $m['estado'] === 'cerrado'
                        ? date('Y-m-t', mktime(0, 0, 0, $m['mes'], 1, $m['anio']))
                        : null,
                    'estado'         => $m['estado'],
                ]
            );
            $ejercicioIds[$m['mes']] = $ej->id;
            $this->line("    ✅ {$nombres[$m['mes']]} {$m['anio']} [{$m['estado']}]");
        }

        // ── 2. PARÁMETROS CONTABLES ──────────────────────────
        $this->info('  ⚙️  Parámetros contables...');
        $mapeo = [
            'cta_caja_general'          => '1.1.01.01',
            'cta_bancos_locales'        => '1.1.01.02',
            'cta_vouchers'              => '1.1.01.05',
            'cta_clientes_locales'      => '1.1.02.01',
            'cta_ventas_locales'        => '4.1.01.01',
            'cta_iva_ventas'            => '2.1.03.04',
            'cta_anticipos_clientes'    => '2.1.01.03',
            'cta_costo_ventas'          => '5.1.01.01',
            'cta_proveedores_locales'   => '2.1.01.01',
            'cta_iva_compras'           => '1.1.05.01',
            'cta_retencion_ir'          => '2.1.03.01',
            'cta_retencion_iva'         => '2.1.03.02',
            'cta_gasto_compras'         => '5.2.02.01',
            'cta_inventario_mercaderia' => '1.1.04.01',
            'cta_ajuste_inventario'     => '5.1.01.04',
            'cta_comisiones_bancarias'  => '5.3.01.02',
            'cta_retencion_iva_cobrada' => '1.1.05.02',
            'cta_retencion_ir_cobrada'  => '1.1.05.03',
            'cta_sueldos_salarios'      => '5.2.01.01',
            'cta_aporte_patronal'       => '5.2.01.04',
            'cta_iess_por_pagar'        => '2.1.04.02',
            'cta_nomina_por_pagar'      => '2.1.04.01',
            'cta_anticipos_empleados'   => '1.1.03.04',
            'cta_gastos_no_deducibles'  => '5.4.01.01',
        ];

        $configurados = 0;
        foreach ($mapeo as $codigo => $codCuenta) {
            $cuenta = PlanCuenta::where('codigo', $codCuenta)->first()
                   ?? PlanCuenta::where('codigo', 'like', "%{$codCuenta}%")->first();
            if (!$cuenta) {
                $this->warn("    ⚠️  Cuenta {$codCuenta} no encontrada");
                continue;
            }
            ParametroContable::updateOrCreate(
                ['empresa_id' => $empresaId, 'codigo' => $codigo],
                ['cuenta_id' => $cuenta->id, 'descripcion' => $codigo]
            );
            $configurados++;
        }
        $this->line("    ✅ {$configurados} parámetros configurados");

        // ── 3. ASIENTOS MANUALES DE PRUEBA ───────────────────
        $this->info('  📝 Asientos de prueba...');

        $cuentas = PlanCuenta::where('permite_asientos', true)
            ->where('estado', true)
            ->orderBy('codigo')
            ->pluck('id', 'codigo')
            ->toArray();

        $getCta = function (array $opciones) use ($cuentas): ?int {
            foreach ($opciones as $cod) {
                foreach ($cuentas as $codigo => $id) {
                    if (str_contains($codigo, $cod)) return $id;
                }
            }
            return array_values($cuentas)[0] ?? null;
        };

        $ctaCaja   = $getCta(['1.1.01.01', '1.1.1.1', 'Caja']);
        $ctaBancos = $getCta(['1.1.01.02', '1.1.1.2', 'Banco']);
        $ctaVentas = $getCta(['4.1.01', '4.1.1', 'Ventas']);
        $ctaIvaV   = $getCta(['2.1.03.04', '2.1.3.4', 'IVA Ventas']);
        $ctaGasto  = $getCta(['5.2', '5.3', 'Gasto']);
        $ctaProvee = $getCta(['2.1.01.01', '2.1.1.1', 'Proveedor']);
        $ctaInvent = $getCta(['1.1.04', '1.1.4', 'Inventario', 'Mercadería']);

        $asientosDePrueba = [
            [
                'numero'         => 'AS-2026-0001',
                'fecha'          => '2026-01-15',
                'concepto'       => 'Venta de equipos de audio — Factura 001-001-000045',
                'documento_tipo' => 'FAC',
                'documento_ref'  => '001-001-000045',
                'es_automatico'  => true,
                'ejercicio_mes'  => 1,
                'partidas'       => [
                    ['cuenta' => $ctaCaja,   'debe' => 1725.00, 'haber' => 0,       'desc' => 'Cobro contado'],
                    ['cuenta' => $ctaVentas, 'debe' => 0,       'haber' => 1500.00, 'desc' => 'Venta equipos'],
                    ['cuenta' => $ctaIvaV,   'debe' => 0,       'haber' => 225.00,  'desc' => 'IVA 15%'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0002',
                'fecha'          => '2026-01-20',
                'concepto'       => 'Compra de mercadería — TecnoSound Factura 001-001-000123',
                'documento_tipo' => 'COMPRA',
                'documento_ref'  => '001-001-000123',
                'es_automatico'  => true,
                'ejercicio_mes'  => 1,
                'partidas'       => [
                    ['cuenta' => $ctaInvent, 'debe' => 3000.00, 'haber' => 0,       'desc' => 'Mercadería comprada'],
                    ['cuenta' => $ctaIvaV,   'debe' => 450.00,  'haber' => 0,       'desc' => 'IVA compras'],
                    ['cuenta' => $ctaProvee, 'debe' => 0,       'haber' => 3450.00, 'desc' => 'CxP TecnoSound'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0003',
                'fecha'          => '2026-02-05',
                'concepto'       => 'Pago servicios básicos — Planilla febrero',
                'documento_tipo' => 'MANUAL',
                'documento_ref'  => null,
                'es_automatico'  => false,
                'ejercicio_mes'  => 2,
                'partidas'       => [
                    ['cuenta' => $ctaGasto,  'debe' => 580.00, 'haber' => 0,      'desc' => 'Servicios básicos'],
                    ['cuenta' => $ctaBancos, 'debe' => 0,      'haber' => 580.00, 'desc' => 'Pago Banco Pichincha'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0004',
                'fecha'          => '2026-02-28',
                'concepto'       => 'Nómina mensual — Febrero 2026',
                'documento_tipo' => 'NOM',
                'documento_ref'  => 'NOM-2026-02',
                'es_automatico'  => true,
                'ejercicio_mes'  => 2,
                'partidas'       => [
                    ['cuenta' => $ctaGasto,  'debe' => 8500.00, 'haber' => 0,       'desc' => 'Sueldos y salarios'],
                    ['cuenta' => $ctaBancos, 'debe' => 0,       'haber' => 8500.00, 'desc' => 'Pago nómina'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0005',
                'fecha'          => '2026-03-10',
                'concepto'       => 'Venta de servicio técnico — OT-2026-0023',
                'documento_tipo' => 'FAC',
                'documento_ref'  => '001-001-000067',
                'es_automatico'  => true,
                'ejercicio_mes'  => 3,
                'partidas'       => [
                    ['cuenta' => $ctaBancos, 'debe' => 920.00, 'haber' => 0,      'desc' => 'Transferencia cliente'],
                    ['cuenta' => $ctaVentas, 'debe' => 0,      'haber' => 800.00, 'desc' => 'Servicio técnico'],
                    ['cuenta' => $ctaIvaV,   'debe' => 0,      'haber' => 120.00, 'desc' => 'IVA 15%'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0006',
                'fecha'          => '2026-04-15',
                'concepto'       => 'Pago proveedor — IluPro Factura 002-001-000456',
                'documento_tipo' => 'BANCO',
                'documento_ref'  => 'TRF-2026-004',
                'es_automatico'  => true,
                'ejercicio_mes'  => 4,
                'partidas'       => [
                    ['cuenta' => $ctaProvee, 'debe' => 2300.00, 'haber' => 0,       'desc' => 'CxP IluPro'],
                    ['cuenta' => $ctaBancos, 'debe' => 0,       'haber' => 2300.00, 'desc' => 'Transferencia'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0007',
                'fecha'          => '2026-05-20',
                'concepto'       => 'Ajuste inventario — Conteo físico mayo',
                'documento_tipo' => 'INV',
                'documento_ref'  => 'INV-2026-05',
                'es_automatico'  => true,
                'ejercicio_mes'  => 5,
                'partidas'       => [
                    ['cuenta' => $ctaGasto,  'debe' => 450.00, 'haber' => 0,      'desc' => 'Faltante inventario'],
                    ['cuenta' => $ctaInvent, 'debe' => 0,      'haber' => 450.00, 'desc' => 'Rebaja inventario'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0008',
                'fecha'          => '2026-06-01',
                'concepto'       => 'Depreciación mensual activos fijos — Junio 2026',
                'documento_tipo' => 'MANUAL',
                'documento_ref'  => null,
                'es_automatico'  => false,
                'ejercicio_mes'  => 6,
                'partidas'       => [
                    ['cuenta' => $ctaGasto,  'debe' => 1200.00, 'haber' => 0,       'desc' => 'Depreciación equipos'],
                    ['cuenta' => $ctaInvent, 'debe' => 0,       'haber' => 1200.00, 'desc' => 'Depreciación acumulada'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0009',
                'fecha'          => '2026-06-15',
                'concepto'       => 'Cobro factura cliente — Transferencia Bancaria',
                'documento_tipo' => 'CXC',
                'documento_ref'  => 'TRF-CLI-001',
                'es_automatico'  => true,
                'ejercicio_mes'  => 6,
                'partidas'       => [
                    ['cuenta' => $ctaBancos, 'debe' => 5750.00, 'haber' => 0,       'desc' => 'Cobro CxC'],
                    ['cuenta' => $ctaVentas, 'debe' => 0,       'haber' => 5000.00, 'desc' => 'Ventas cobradas'],
                    ['cuenta' => $ctaIvaV,   'debe' => 0,       'haber' => 750.00,  'desc' => 'IVA cobrado'],
                ],
            ],
            [
                'numero'         => 'AS-2026-0010',
                'fecha'          => '2026-07-01',
                'concepto'       => 'Anticipo cliente — Reserva evento corporativo',
                'documento_tipo' => 'FAC',
                'documento_ref'  => '001-001-000089',
                'es_automatico'  => true,
                'ejercicio_mes'  => 7,
                'partidas'       => [
                    ['cuenta' => $ctaCaja,   'debe' => 2300.00, 'haber' => 0,       'desc' => 'Anticipo efectivo'],
                    ['cuenta' => $ctaVentas, 'debe' => 0,       'haber' => 2000.00, 'desc' => 'Anticipo cliente'],
                    ['cuenta' => $ctaIvaV,   'debe' => 0,       'haber' => 300.00,  'desc' => 'IVA anticipo'],
                ],
            ],
        ];

        $creados = 0;
        foreach ($asientosDePrueba as $data) {
            if (AsientoContable::where('empresa_id', $empresaId)
                ->where('numero', $data['numero'])->exists()) {
                $this->line("    ⏭️  Ya existe: {$data['numero']}");
                continue;
            }

            $ejercicioId = $ejercicioIds[$data['ejercicio_mes']] ?? null;
            if (!$ejercicioId) continue;

            $partidasValidas = collect($data['partidas'])
                ->filter(fn($p) => $p['cuenta'] !== null)
                ->toArray();

            if (count($partidasValidas) < 2) {
                $this->warn("    ⚠️  {$data['numero']}: cuentas no encontradas");
                continue;
            }

            $totalDebe  = collect($partidasValidas)->sum('debe');
            $totalHaber = collect($partidasValidas)->sum('haber');

            $asiento = AsientoContable::create([
                'empresa_id'     => $empresaId,
                'ejercicio_id'   => $ejercicioId,
                'numero'         => $data['numero'],
                'fecha'          => $data['fecha'],
                'concepto'       => $data['concepto'],
                'documento_tipo' => $data['documento_tipo'],
                'documento_ref'  => $data['documento_ref'],
                'total_debe'     => $totalDebe,
                'total_haber'    => $totalHaber,
                'es_automatico'  => $data['es_automatico'],
                'estado'         => 1,
                'creado_por'     => 1,
            ]);

            foreach ($partidasValidas as $p) {
                AsientoDetalle::create([
                    'asiento_id'  => $asiento->id,
                    'cuenta_id'   => $p['cuenta'],
                    'debe'        => $p['debe'],
                    'haber'       => $p['haber'],
                    'descripcion' => $p['desc'],
                ]);
            }

            $this->line("    ✅ {$data['numero']}: {$data['concepto']}");
            $creados++;
        }

        $this->info("  ✅ Contabilidad seeded: {$creados} asientos nuevos");
    }
}
