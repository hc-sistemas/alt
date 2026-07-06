<?php

namespace Database\Seeders;

use App\Services\AsientoService;
use App\Services\SecuencialService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegracionSeeder extends Seeder
{
    public function __construct(
        private AsientoService    $asiento,
        private SecuencialService $secuencial,
    ) {}

    public function run(): void
    {
        $empresaId = 1;
        $adminId   = DB::table('usuarios')->where('email', 'admin@altamira.com')->value('id') ?? 1;

        DB::transaction(function () use ($empresaId, $adminId) {
            $this->seedClientes($empresaId);
            $this->seedStockCritico($empresaId);
            $this->seedTallerBase();
            $this->seedFacturas($empresaId, $adminId);
            $this->seedPrefacturas($empresaId, $adminId);
            $this->seedNotaCredito($empresaId, $adminId);
            $this->seedDatafastLiquidados($empresaId, $adminId);
            $this->seedChequeAnulado($empresaId, $adminId);
            $this->seedImportacionConAnticipo($empresaId, $adminId);
            $this->seedTallerOrdenes($empresaId, $adminId);
        });
    }

    // ── 1. CLIENTES ──────────────────────────────────────────────────────────

    private function seedClientes(int $empresaId): void
    {
        $clientes = [
            ['identificacion' => '1790123456001', 'razon_social' => 'Audio Tech Ecuador S.A.',           'credito' => true,  'dias' => 30, 'cupo' => 15000],
            ['identificacion' => '0990456789001', 'razon_social' => 'DJ Paradise Guayaquil Cía. Ltda.',  'credito' => true,  'dias' => 60, 'cupo' => 20000],
            ['identificacion' => '1791234567001', 'razon_social' => 'Producciones Élite Quito',          'credito' => false, 'dias' => 0,  'cupo' => 0],
            ['identificacion' => '1701234567',    'razon_social' => 'Carlos Mendoza Vásquez',            'credito' => false, 'dias' => 0,  'cupo' => 0],
            ['identificacion' => '1800234567001', 'razon_social' => 'Mega Audio Ambato S.A.',            'credito' => true,  'dias' => 45, 'cupo' => 10000],
            ['identificacion' => '0501234567001', 'razon_social' => 'Latacunga Sound & Light Cía.',     'credito' => true,  'dias' => 30, 'cupo' => 8000],
            ['identificacion' => '1702345678',    'razon_social' => 'Diego Herrera Salinas',             'credito' => false, 'dias' => 0,  'cupo' => 0],
            ['identificacion' => '1791567890001', 'razon_social' => 'Eventos Corporativos Premium S.A.', 'credito' => true,  'dias' => 90, 'cupo' => 25000],
            ['identificacion' => '1703456789',    'razon_social' => 'María Fernanda Sánchez',            'credito' => false, 'dias' => 0,  'cupo' => 0],
            ['identificacion' => '0991234567001', 'razon_social' => 'GByte Sound Systems Guayaquil',    'credito' => true,  'dias' => 30, 'cupo' => 12000],
            ['identificacion' => '1704567890',    'razon_social' => 'Roberto Espinoza Mora',             'credito' => false, 'dias' => 0,  'cupo' => 0],
            ['identificacion' => '1791890123001', 'razon_social' => 'Conciertos del Ecuador S.A.',      'credito' => true,  'dias' => 60, 'cupo' => 18000],
        ];

        foreach ($clientes as $c) {
            $exists = DB::table('clientes')
                ->where('identificacion', $c['identificacion'])
                ->where('empresa_id', $empresaId)
                ->exists();
            if ($exists) continue;

            DB::table('clientes')->insert([
                'empresa_id'          => $empresaId,
                'tipo_identificacion' => '05',
                'identificacion'      => $c['identificacion'],
                'razon_social'        => $c['razon_social'],
                'nombre_comercial'    => $c['razon_social'],
                'email'               => 'cli' . substr($c['identificacion'], -4) . '@example.com',
                'telefono'            => '02' . rand(2000000, 2999999),
                'celular'             => '09' . rand(10000000, 99999999),
                'direccion'           => 'Av. Principal ' . rand(100, 999) . ', Quito',
                'ciudad'              => 'Quito',
                'provincia'           => 'Pichincha',
                'pais'                => 'Ecuador',
                'tiene_credito'       => $c['credito'],
                'dias_credito'        => $c['dias'],
                'cupo_maximo'         => $c['cupo'],
                'agente_retencion'    => false,
                'es_cliente_nuevo'    => false,
                'estado'              => true,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);
        }
    }

    // ── 2. STOCK CRÍTICO ─────────────────────────────────────────────────────

    private function seedStockCritico(int $empresaId): void
    {
        // Asegurar stock de repuestos para taller
        $prod19 = DB::table('productos')->find(19);
        if ($prod19) {
            DB::table('inventario_saldos')->updateOrInsert(
                ['producto_id' => 19, 'bodega_id' => 1],
                ['stock_actual' => 50, 'stock_reservado' => 0, 'costo_promedio' => $prod19->costo]
            );
        }

        // 3 productos con stock_minimo > stock_actual para alertas críticas
        $prods = DB::table('productos')->where('empresa_id', $empresaId)->orderBy('id')->limit(3)->pluck('id');
        foreach ($prods as $pid) {
            $actual = DB::table('inventario_saldos')
                ->where('producto_id', $pid)->where('bodega_id', 1)->value('stock_actual') ?? 2;
            DB::table('productos')->where('id', $pid)
                ->update(['stock_minimo' => max(10, (int)$actual + 5)]);
        }
    }

    // ── 3. TALLER BASE (tipos equipo, equipos) ────────────────────────────────

    private function seedTallerBase(): void
    {
        if (DB::table('taller_tipos_equipo')->count() > 0) return;

        $tipos = ['Consola de Mezcla', 'Amplificador de Potencia', 'Procesador de Audio', 'Controlador de Iluminación', 'Subwoofer / Bafle'];
        $tipoIds = [];
        foreach ($tipos as $desc) {
            $tipoIds[] = DB::table('taller_tipos_equipo')->insertGetId([
                'descripcion' => $desc,
                'estado'      => true,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // taller_equipos: sin empresa_id
        $equipos = [
            [$tipoIds[0], 'Allen & Heath', 'SQ-5',    'AH-SQ5-78234'],
            [$tipoIds[1], 'Crown',         'XLS 2502','CR-XLS-45123'],
            [$tipoIds[2], 'dbx',           '231S',    'DBX-231-99012'],
            [$tipoIds[3], 'Chauvet',       'Obey 40', 'CH-OBY-33401'],
            [$tipoIds[4], 'JBL',           'SRX818SP','JBL-SRX-61900'],
        ];
        foreach ($equipos as [$tid, $marca, $modelo, $serie]) {
            DB::table('taller_equipos')->insert([
                'tipo_id'      => $tid,
                'marca'        => $marca,
                'modelo'       => $modelo,
                'numero_serie' => $serie,
                'estado'       => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
    }

    // ── 4. FACTURAS ───────────────────────────────────────────────────────────

    private function seedFacturas(int $empresaId, int $adminId): void
    {
        if (DB::table('facturas')->where('empresa_id', $empresaId)->count() >= 5) return;

        $clientes  = DB::table('clientes')->where('empresa_id', $empresaId)->pluck('id')->toArray();
        $productos = DB::table('productos')->where('empresa_id', $empresaId)->limit(8)->get();
        if (empty($clientes) || $productos->isEmpty()) return;

        $escenarios = [
            ['efectivo',     false, 'activa'],
            ['credito',      true,  'activa'],
            ['datafast',     false, 'activa'],
            ['transferencia',false, 'activa'],
            ['efectivo',     false, 'activa'],
            ['credito',      true,  'activa'],
            ['efectivo',     false, 'anulada'],
        ];

        foreach ($escenarios as $idx => [$formaPago, $esCredito, $estado]) {
            $cliId  = $clientes[$idx % count($clientes)];
            $cliRec = DB::table('clientes')->find($cliId);
            $prod   = $productos[$idx % $productos->count()];
            $precio = (float)$prod->pvp;
            $cant   = rand(1, 4);
            $sub15  = round($precio * $cant, 2);
            $iva    = round($sub15 * 0.15, 2);
            $total  = round($sub15 + $iva, 2);

            $numero = $this->secuencial->siguiente($empresaId, 'FAC');
            [$est, $pe, $sec] = explode('-', $numero);

            $facturaId = DB::table('facturas')->insertGetId([
                'empresa_id'          => $empresaId,
                'cliente_id'          => $cliId,
                'usuario_id'          => $adminId,
                'establecimiento'     => $est,
                'punto_emision'       => $pe,
                'secuencial'          => $sec,
                'numero_completo'     => $numero,
                'fecha_emision'       => now()->subDays(rand(0, 20))->toDateString(),
                'hora_emision'        => now()->toTimeString(),
                'estado_sri'          => $estado === 'anulada' ? 'anulada' : 'autorizada',
                'tipo_identificacion' => $cliRec->tipo_identificacion,
                'identificacion'      => $cliRec->identificacion,
                'razon_social'        => $cliRec->razon_social,
                'email_cliente'       => $cliRec->email,
                'telefono_cliente'    => $cliRec->telefono,
                'subtotal_0'          => 0,
                'subtotal_15'         => $sub15,
                'descuento_total'     => 0,
                'total_iva'           => $iva,
                'total'               => $total,
                'tipo'                => 1,
                'estado'              => $estado,
                'email_enviado'       => false,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]);

            DB::table('factura_detalles')->insert([
                'factura_id'      => $facturaId,
                'producto_id'     => $prod->id,
                'codigo_producto' => $prod->codigo,
                'descripcion'     => $prod->nombre,
                'cantidad'        => $cant,
                'precio_unitario' => $precio,
                'descuento_pct'   => 0,
                'descuento_valor' => 0,
                'subtotal'        => $sub15,
                'porcentaje_iva'  => 15,
                'valor_iva'       => $iva,
                'total'           => $total,
            ]);

            DB::table('factura_pagos')->insert([
                'factura_id' => $facturaId,
                'forma_pago' => $formaPago,
                'valor'      => $total,
            ]);

            if ($esCredito && $estado === 'activa') {
                $dias = (int)($cliRec->dias_credito ?? 30);
                DB::table('cuentas_cobrar')->insertOrIgnore([
                    'empresa_id'        => $empresaId,
                    'cliente_id'        => $cliId,
                    'factura_id'        => $facturaId,
                    'monto'             => $total,
                    'saldo'             => $total,
                    'fecha_emision'     => now()->subDays(rand(0, 10))->toDateString(),
                    'fecha_vencimiento' => now()->addDays($dias)->toDateString(),
                    'forma_cobro'       => 'credito',
                    'estado'            => 'pendiente',
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);
            }

            if ($estado === 'activa') {
                try {
                    $this->asiento->facturaAutorizada(
                        empresaId:     $empresaId,
                        facturaId:     $facturaId,
                        numeroFactura: $numero,
                        subtotal:      $sub15,
                        iva:           $iva,
                        total:         $total,
                        formaPago:     $formaPago,
                    );
                } catch (\Throwable $e) {
                    $this->command->warn("  ⚠ Asiento {$numero}: " . $e->getMessage());
                }
            }
        }
    }

    // ── 5. PREFACTURAS con abono parcial ──────────────────────────────────────

    private function seedPrefacturas(int $empresaId, int $adminId): void
    {
        if (!Schema::hasTable('prefacturas')) return;
        // prefacturas.numero es int (no numero_completo); sin establecimiento/punto_emision
        if (DB::table('prefacturas')->where('empresa_id', $empresaId)->count() >= 2) return;

        $clientes  = DB::table('clientes')->where('empresa_id', $empresaId)->limit(4)->pluck('id');
        $productos = DB::table('productos')->where('empresa_id', $empresaId)->limit(4)->get();
        if ($clientes->isEmpty() || $productos->isEmpty()) return;

        $numMax = (int)(DB::table('prefacturas')->where('empresa_id', $empresaId)->max('numero') ?? 0);

        foreach ([0, 1] as $idx) {
            $cliId  = $clientes[$idx];
            $prod   = $productos[$idx];
            $precio = (float)$prod->pvp;
            $cant   = rand(2, 4);
            $total  = round($precio * $cant * 1.15, 2);
            $abono  = round($total * 0.5, 2);
            $numMax++;

            $prefId = DB::table('prefacturas')->insertGetId([
                'empresa_id'     => $empresaId,
                'cliente_id'     => $cliId,
                'usuario_id'     => $adminId,
                'numero'         => $numMax,
                'fecha_emision'  => now()->subDays(rand(5, 15))->toDateString(),
                'total'          => $total,
                'total_abonado'  => $abono,
                'saldo_pendiente'=> $total - $abono,
                'estado'         => 'activa',
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            if (Schema::hasTable('prefactura_detalles')) {
                DB::table('prefactura_detalles')->insert([
                    'prefactura_id'  => $prefId,
                    'producto_id'    => $prod->id,
                    'descripcion'    => $prod->nombre,
                    'cantidad'       => $cant,
                    'precio_unitario'=> $precio,
                    'total'          => $total,
                ]);
            }

            if (Schema::hasTable('prefactura_abonos')) {
                DB::table('prefactura_abonos')->insert([
                    'prefactura_id' => $prefId,
                    'fecha'         => now()->subDays(rand(1, 4))->toDateString(),
                    'valor'         => $abono,
                    'forma_pago'    => 'efectivo',
                    'usuario_id'    => $adminId,
                    'created_at'    => now(),
                ]);
            }
        }
    }

    // ── 6. NOTA DE CRÉDITO ────────────────────────────────────────────────────

    private function seedNotaCredito(int $empresaId, int $adminId): void
    {
        if (!Schema::hasTable('notas_credito')) return;
        if (DB::table('notas_credito')->where('empresa_id', $empresaId)->exists()) return;

        $factura = DB::table('facturas')
            ->where('empresa_id', $empresaId)->where('estado', 'activa')->first();
        if (!$factura) return;

        $numero = $this->secuencial->siguiente($empresaId, 'NC');
        [$est, $pe, $sec] = explode('-', $numero);

        $ncId = DB::table('notas_credito')->insertGetId([
            'empresa_id'         => $empresaId,
            'factura_id'         => $factura->id,
            'cliente_id'         => $factura->cliente_id,
            'usuario_id'         => $adminId,
            'establecimiento'    => $est,
            'punto_emision'      => $pe,
            'secuencial'         => $sec,
            'numero_completo'    => $numero,
            'fecha_emision'      => now()->subDays(2)->toDateString(),
            'motivo'             => 'Devolución de mercadería en perfectas condiciones',
            'tipo'               => 1,
            'subtotal'           => $factura->subtotal_15,
            'total_iva'          => $factura->total_iva,
            'total'              => $factura->total,
            'estado_sri'         => 'pendiente',
            'genera_saldo_favor' => false,
            'saldo_favor'        => 0,
            'estado'             => 'activa',
            'created_at'         => now(),
        ]);

        if (Schema::hasTable('nota_credito_detalles')) {
            $det = DB::table('factura_detalles')->where('factura_id', $factura->id)->first();
            if ($det) {
                DB::table('nota_credito_detalles')->insert([
                    'nota_credito_id' => $ncId,
                    'producto_id'     => $det->producto_id,
                    'descripcion'     => $det->descripcion,
                    'cantidad'        => $det->cantidad,
                    'precio_unitario' => $det->precio_unitario,
                    'total'           => $det->total,
                ]);
            }
        }
    }

    // ── 7. DATAFAST LOTES LIQUIDADOS ─────────────────────────────────────────

    private function seedDatafastLiquidados(int $empresaId, int $adminId): void
    {
        // Columnas reales: banco_caja_id, fecha, total_vouchers, created_by
        if (DB::table('datafast_lotes')->where('empresa_id', $empresaId)->count() >= 3) return;

        $banco = DB::table('bancos_cajas')
            ->where('empresa_id', $empresaId)->where('tipo', 'banco')->first();
        if (!$banco) return;

        foreach ([1, 2] as $n) {
            $valorBruto = round(rand(800, 2000) * 1.0, 2);
            $comision   = round($valorBruto * 0.035, 2);
            $retIva     = round($comision * 0.30, 2);
            $retIr      = round($valorBruto * 0.01, 2);
            $neto       = round($valorBruto - $comision - $retIva - $retIr, 2);
            $numLote    = 'L-' . now()->format('Ymd') . str_pad($n, 3, '0', STR_PAD_LEFT);

            if (DB::table('datafast_lotes')->where('numero_lote', $numLote)->exists()) continue;

            $loteId = DB::table('datafast_lotes')->insertGetId([
                'empresa_id'    => $empresaId,
                'banco_caja_id' => $banco->id,
                'numero_lote'   => $numLote,
                'fecha'         => now()->subDays(rand(5, 15))->toDateString(),
                'total_vouchers'=> $valorBruto,
                'estado'        => 'liquidado',
                'created_by'    => $adminId,
                'created_at'    => now()->subDays(rand(5, 15)),
            ]);

            DB::table('datafast_liquidaciones')->insert([
                'lote_id'           => $loteId,
                'fecha_deposito'    => now()->subDays(rand(1, 3))->toDateString(),
                'valor_bruto'       => $valorBruto,
                'comision_datafast' => $comision,
                'retencion_iva'     => $retIva,
                'retencion_ir'      => $retIr,
                'valor_neto'        => $neto,
                'banco_destino_id'  => $banco->id,
                'created_by'        => $adminId,
                'created_at'        => now(),
            ]);
        }
    }

    // ── 8. CHEQUE ANULADO ─────────────────────────────────────────────────────

    private function seedChequeAnulado(int $empresaId, int $adminId): void
    {
        // Columnas reales: banco_caja_id, fecha_emision, observacion (sin usuario_id, sin updated_at)
        if (DB::table('cheques')->where('empresa_id', $empresaId)->where('estado', 'anulado')->exists()) return;

        $banco     = DB::table('bancos_cajas')->where('empresa_id', $empresaId)->where('tipo', 'banco')->first();
        $proveedor = DB::table('proveedores')->where('empresa_id', $empresaId)->first();
        if (!$banco || !$proveedor) return;

        DB::table('cheques')->insert([
            'empresa_id'   => $empresaId,
            'banco_caja_id'=> $banco->id,
            'numero'       => 'CHQ-TEST-' . rand(10000, 99999),
            'banco'        => $banco->nombre,
            'monto'        => 500.00,
            'fecha_emision'=> now()->subDays(3)->toDateString(),
            'beneficiario' => $proveedor->razon_social,
            'estado'       => 'anulado',
            'observacion'  => 'Cheque anulado — validación integración Dev2',
            'created_at'   => now()->subDays(3),
        ]);
    }

    // ── 9. IMPORTACIÓN CON ANTICIPO ───────────────────────────────────────────

    private function seedImportacionConAnticipo(int $empresaId, int $adminId): void
    {
        // importaciones: costo_fob, num_invoice, total_costos_extra, costo_total, created_by
        // anticipos_proveedores: sin referencia, sin usuario_id
        if (DB::table('importaciones')->where('empresa_id', $empresaId)->where('estado', 'liquidada')->exists()) return;

        $proveedor = DB::table('proveedores')->where('empresa_id', $empresaId)->first();
        $banco     = DB::table('bancos_cajas')->where('empresa_id', $empresaId)->where('tipo', 'banco')->first();
        if (!$proveedor) return;

        DB::table('anticipos_proveedores')->insert([
            'empresa_id'   => $empresaId,
            'proveedor_id' => $proveedor->id,
            'fecha'        => now()->subDays(30)->toDateString(),
            'monto'        => 5000.00,
            'saldo'        => 0.00,
            'banco_id'     => $banco?->id,
            'estado'       => 'cruzado',
            'created_at'   => now()->subDays(30),
        ]);

        DB::table('importaciones')->insert([
            'empresa_id'        => $empresaId,
            'proveedor_id'      => $proveedor->id,
            'nombre'            => 'Importación Equipos Pro Audio 2026',
            'num_invoice'       => 'INV-TEST-' . rand(1000, 9999),
            'agente_aduanero'   => 'Agentes Aduaneros del Ecuador S.A.',
            'pais_embarque'     => 'China',
            'costo_fob'         => 12000.00,
            'divisa'            => 'USD',
            'fecha_partida'     => now()->subDays(25)->toDateString(),
            'fecha_llegada'     => now()->subDays(10)->toDateString(),
            'fecha_liquidacion' => now()->subDays(5)->toDateString(),
            'total_costos_extra'=> 2050.00,
            'costo_total'       => 15050.00,
            'metodo_prorrateo'  => 'peso',
            'estado'            => 'liquidada',
            'observaciones'     => 'Anticipo previo cruzado en liquidación — prueba integración',
            'created_by'        => $adminId,
            'created_at'        => now()->subDays(25),
            'updated_at'        => now()->subDays(5),
        ]);
    }

    // ── 10. ÓRDENES DE TRABAJO TALLER ─────────────────────────────────────────

    private function seedTallerOrdenes(int $empresaId, int $adminId): void
    {
        if (DB::table('taller_ordenes_trabajo')->where('empresa_id', $empresaId)->count() >= 4) return;

        $equipos  = DB::table('taller_equipos')->pluck('id')->toArray();
        $clientes = DB::table('clientes')->where('empresa_id', $empresaId)->limit(5)->pluck('id')->toArray();
        if (empty($equipos) || empty($clientes)) return;

        $ingresoIds = [];
        foreach (array_slice($equipos, 0, 4) as $idx => $equipoId) {
            $cliId = $clientes[$idx % count($clientes)];
            // taller_ingresos: sin updated_at
            $ingresoIds[] = DB::table('taller_ingresos')->insertGetId([
                'empresa_id'         => $empresaId,
                'cliente_id'         => $cliId,
                'equipo_id'          => $equipoId,
                'usuario_id'         => $adminId,
                'fecha'              => now()->subDays(rand(3, 12))->toDateString(),
                'hora'               => '09:' . str_pad(rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
                'diagnostico_inicial'=> 'Canal ' . rand(1, 8) . ' con ruido y distorsión al 70% de volumen.',
                'estado'             => 1,
                'created_at'         => now()->subDays(rand(3, 12)),
            ]);
        }

        $estados = ['diagnostico', 'aprobado', 'en_reparacion', 'facturado'];
        $otBase  = (int)(DB::table('taller_ordenes_trabajo')->max('id') ?? 0);

        foreach ($ingresoIds as $idx => $ingresoId) {
            $estado = $estados[$idx];

            $otId = DB::table('taller_ordenes_trabajo')->insertGetId([
                'empresa_id'         => $empresaId,
                'ingreso_id'         => $ingresoId,
                'tecnico_id'         => $adminId,
                'numero'             => 'OT-' . str_pad($otBase + $idx + 1, 4, '0', STR_PAD_LEFT),
                'fecha_inicio'       => now()->subDays(rand(2, 10))->toDateString(),
                'hora_inicio'        => '10:00:00',
                'fecha_fin_estimada' => now()->addDays(rand(2, 7))->toDateString(),
                'tipo_orden'         => 1,
                'descripcion_trabajo'=> 'Revisión etapa de potencia. Cambio de transistores dañados.',
                'costo_mano_obra'    => round(rand(50, 150) * 1.0, 2),
                'costo_repuestos'    => round(rand(20, 80) * 1.0, 2),
                'costo_total'        => round(rand(70, 230) * 1.0, 2),
                'es_garantia'        => $idx === 3,
                'estado'             => $estado,
                'created_at'         => now()->subDays(rand(2, 10)),
                'updated_at'         => now(),
            ]);

            // taller_diagnosticos: sin timestamps
            DB::table('taller_diagnosticos')->insert([
                'orden_id'         => $otId,
                'tecnico_id'       => $adminId,
                'fecha'            => now()->subDays(rand(1, 6))->toDateString(),
                'hora'             => '11:00',
                'diagnostico'      => 'Transistores IRFP250 quemados en etapa de salida. Requiere 4 unidades.',
                'tiempo_estimado'  => rand(2, 6),
                'tipo_tiempo'      => 'horas',
                'cliente_aprueba'  => in_array($estado, ['aprobado', 'en_reparacion', 'facturado']),
                'fecha_aprobacion' => in_array($estado, ['aprobado', 'en_reparacion', 'facturado'])
                                        ? now()->subDays(rand(0, 2))->toDateString() : null,
                'estado'           => in_array($estado, ['aprobado', 'en_reparacion', 'facturado'])
                                        ? 'aprobado' : 'pendiente',
            ]);

            // taller_ot_repuestos: sin timestamps
            $saldo = DB::table('inventario_saldos')
                ->where('producto_id', 19)->where('bodega_id', 1)->value('stock_actual') ?? 0;
            $repuesto = DB::table('productos')->find(19);
            if ($repuesto && $saldo > 0) {
                DB::table('taller_ot_repuestos')->insertOrIgnore([
                    'orden_id'      => $otId,
                    'producto_id'   => 19,
                    'cantidad'      => min(4, (int)$saldo),
                    'costo_unitario'=> (float)$repuesto->costo,
                    'precio_venta'  => (float)$repuesto->pvp,
                    'estado'        => 'reservado',
                ]);
            }
        }
    }
}
