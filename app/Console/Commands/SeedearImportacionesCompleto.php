<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SeedearImportacionesCompleto extends Command
{
    protected $signature = 'altamira:seedear-importaciones-completo';
    protected $description = 'Seedea productos, compras y saldos reales para las 4 importaciones del módulo Compras';

    private array $resumen = [];

    public function handle(): int
    {
        $this->info('Iniciando seeder de importaciones completo...');
        $this->newLine();

        DB::transaction(function () {
            $this->limpiarDatosYamaha();
            $this->crearProductos();
            $this->seedShure();
            $this->seedYamaha();
            $this->seedChauvet();
            $this->seedPekin();
        });

        $this->mostrarResumen();
        return self::SUCCESS;
    }

    // ─── Cleanup: revertir datos de prueba Yamaha ────────────────────────────

    private function limpiarDatosYamaha(): void
    {
        $this->line('Limpiando datos de prueba de YAMAHA Q2-2026...');

        // Desvincular compras de prueba 9 y 10 de la importacion
        DB::table('compras')->whereIn('id', [9, 10])->update(['importacion_id' => null]);

        // Revertir importacion 2 al estado previo a liquidación
        DB::table('importaciones')->where('id', 2)->update([
            'estado'             => 'en_aduana',
            'total_costos_extra' => 0,
            'costo_total'        => 32000,
            'fecha_liquidacion'  => null,
            'metodo_prorrateo'   => 'cantidad',
        ]);

        // Revertir AMP-001 (id=5) costo al valor FOB original
        DB::table('productos')->where('id', 5)->update(['costo' => 420.00]);

        // Revertir CAB-002 (id=17) costo y costo_promedio al valor FOB original
        DB::table('productos')->where('id', 17)->update(['costo' => 15.00]);
        DB::table('inventario_saldos')
            ->where('producto_id', 17)
            ->where('bodega_id', 1)
            ->update(['costo_promedio' => 15.00]);

        $this->resumen[] = ['accion' => 'REVERT', 'detalle' => 'Yamaha test data revertido (compras 9/10 desvinculadas, AMP-001 y CAB-002 costos restaurados)'];
        $this->line('  ✓ Yamaha limpiado');
    }

    // ─── Crear todos los productos necesarios ────────────────────────────────

    private function crearProductos(): void
    {
        $this->line('Creando productos...');

        $empresa = 1;
        $now     = now();

        $definiciones = [
            // SHURE Q1-2026 — costos ya con prorrateo aplicado (liquidada, 184 uds, extra=3200)
            // extra/ud = 3200/184 = 17.3913
            ['codigo' => 'SHR-001', 'nombre' => 'Micrófono Dinámico Shure SM58-LC',        'costo' => 106.39, 'pvp' => 165.00, 'pvd' => 135.00, 'marca_id' => 1, 'cat' => 7],
            ['codigo' => 'SHR-002', 'nombre' => 'Micrófono Instrumental Shure SM57-LC',     'costo' =>  96.39, 'pvp' => 148.00, 'pvd' => 120.00, 'marca_id' => 1, 'cat' => 7],
            ['codigo' => 'SHR-003', 'nombre' => 'Sistema Inalámbrico Shure BLX288/PG58',   'costo' => 227.39, 'pvp' => 360.00, 'pvd' => 290.00, 'marca_id' => 1, 'cat' => 7],

            // YAMAHA Q2-2026 — costos FOB (pendiente de prorrateo, en_aduana)
            ['codigo' => 'YAM-001', 'nombre' => 'Consola de Mezcla Yamaha MG20XU 20 Canales', 'costo' => 420.00, 'pvp' => 680.00, 'pvd' => 550.00, 'marca_id' => 2, 'cat' => 8],
            ['codigo' => 'YAM-002', 'nombre' => 'Monitor de Estudio Yamaha HS8 8"',          'costo' => 380.00, 'pvp' => 620.00, 'pvd' => 500.00, 'marca_id' => 2, 'cat' => 10],
            ['codigo' => 'YAM-003', 'nombre' => 'Procesador Digital Yamaha SPX2000',         'costo' => 650.00, 'pvp' => 1050.00, 'pvd' => 850.00, 'marca_id' => 2, 'cat' => 12],
            ['codigo' => 'YAM-004', 'nombre' => 'Amplificador de Potencia Yamaha P7000S',    'costo' => 780.00, 'pvp' => 1250.00, 'pvd' => 1020.00, 'marca_id' => 2, 'cat' => 9],

            // CHAUVET Q2-2026 — costos FOB (en_transito)
            ['codigo' => 'CHV-001', 'nombre' => 'Cabeza Móvil Chauvet Pro Rogue R3 Wash',    'costo' => 850.00, 'pvp' => 1350.00, 'pvd' => 1100.00, 'marca_id' => 5, 'cat' => 14],
            ['codigo' => 'CHV-002', 'nombre' => 'Controlador DMX Chauvet Obey 70',           'costo' => 180.00, 'pvp' =>  280.00, 'pvd' =>  230.00, 'marca_id' => 5, 'cat' => 16],
            ['codigo' => 'CHV-003', 'nombre' => 'Par LED Chauvet SlimPAR Pro RGBA IP',       'costo' => 120.00, 'pvp' =>  195.00, 'pvd' =>  160.00, 'marca_id' => 5, 'cat' => 17],
            ['codigo' => 'CHV-004', 'nombre' => 'Máquina de Humo Chauvet Nimbus Dry Ice',    'costo' => 290.00, 'pvp' =>  450.00, 'pvd' =>  370.00, 'marca_id' => 5, 'cat' => 17],

            // PEKIN-001 — costos FOB (en_transito)
            ['codigo' => 'DJ-003',  'nombre' => 'Auriculares DJ Pioneer HDJ-X5 Negro',       'costo' =>  85.00, 'pvp' =>  145.00, 'pvd' =>  115.00, 'marca_id' => 3, 'cat' => 11],
            ['codigo' => 'CAB-010', 'nombre' => 'Cable XLR Balanceado 10m Canare L-4E6S',   'costo' =>  18.00, 'pvp' =>   32.00, 'pvd' =>   26.00, 'marca_id' => 12, 'cat' => 13],
        ];

        foreach ($definiciones as $def) {
            $existe = DB::table('productos')
                ->where('empresa_id', $empresa)
                ->where('codigo', $def['codigo'])
                ->exists();

            if ($existe) {
                // Actualizar solo costo/pvp/pvd si el producto ya fue creado antes
                DB::table('productos')
                    ->where('empresa_id', $empresa)
                    ->where('codigo', $def['codigo'])
                    ->update(['costo' => $def['costo'], 'pvp' => $def['pvp'], 'pvd' => $def['pvd'], 'updated_at' => $now]);
                $this->resumen[] = ['accion' => 'UPDATE', 'detalle' => "Producto {$def['codigo']} actualizado (costos)"];
            } else {
                DB::table('productos')->insert([
                    'empresa_id'    => $empresa,
                    'marca_id'      => $def['marca_id'],
                    'categoria_id'  => $def['cat'],
                    'codigo'        => $def['codigo'],
                    'nombre'        => $def['nombre'],
                    'tipo'          => 'producto',
                    'unidad'        => 'UND',
                    'costo'         => $def['costo'],
                    'pvp'           => $def['pvp'],
                    'pvd'           => $def['pvd'],
                    'porcentaje_iva' => 15,
                    'descuento_maximo' => 10,
                    'stock_minimo'  => 0,
                    'stock_maximo'  => 0,
                    'estado'        => true,
                    'requiere_serie' => false,
                    'tiene_ice'     => false,
                    'porcentaje_ice' => 0,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
                $this->resumen[] = ['accion' => 'CREATE', 'detalle' => "Producto {$def['codigo']} — {$def['nombre']}"];
            }
        }
        $this->line('  ✓ Productos procesados: ' . count($definiciones));
    }

    // ─── IMPORTACIÓN SHURE Q1-2026 (id=1, liquidada) ─────────────────────────

    private function seedShure(): void
    {
        $this->line('Seeding SHURE Q1-2026...');

        $impId     = 1;
        $empresa   = 1;
        $bodega    = 1; // Bodega Principal UIO (mercancía ya recibida y distribuida)
        $provId    = 6; // SHURE INCORPORATED
        $now       = now();

        // Evitar duplicar la compra si ya existe
        $yaExiste = DB::table('compras')
            ->where('importacion_id', $impId)
            ->where('empresa_id', $empresa)
            ->exists();

        if ($yaExiste) {
            $this->line('  ⚠ SHURE: compra ya existe, omitiendo');
            $this->resumen[] = ['accion' => 'SKIP', 'detalle' => 'SHURE Q1-2026: compra ya existía'];
            return;
        }

        // Productos y precios FOB (pre-prorrateo)
        $lineas = [
            ['codigo' => 'SHR-001', 'cantidad' => 80,  'precio_unitario' => 89.00],
            ['codigo' => 'SHR-002', 'cantidad' => 80,  'precio_unitario' => 79.00],
            ['codigo' => 'SHR-003', 'cantidad' => 24,  'precio_unitario' => 210.00],
        ];

        $subtotal = collect($lineas)->sum(fn($l) => $l['cantidad'] * $l['precio_unitario']);

        $compraId = DB::table('compras')->insertGetId([
            'empresa_id'         => $empresa,
            'centro_costo_id'    => 1,
            'proveedor_id'       => $provId,
            'importacion_id'     => $impId,
            'bodega_id'          => $bodega,
            'tipo_documento'     => 'EXT',
            'num_documento'      => 'SHR-2026-FAC-001',
            'fecha_emision'      => '2026-02-15',
            'fecha_registro'     => '2026-02-15',
            'subtotal_0'         => $subtotal,
            'subtotal_iva'       => 0,
            'total_iva'          => 0,
            'total_ice'          => 0,
            'total'              => $subtotal,
            'iva_asumido'        => false,
            'gasto_no_deducible' => false,
            'sustento_tributario' => 7,
            'tiene_pago'         => false,
            'estado'             => 'activa',
            'created_by'         => 1,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $this->insertarDetalles($compraId, $lineas, $empresa, 0);
        $this->upsertSaldos($lineas, $bodega, $empresa, 'post_proration');
        $this->resumen[] = ['accion' => 'CREATE', 'detalle' => "SHURE Q1-2026: compra #{$compraId} + " . count($lineas) . " líneas + saldos en Bodega Principal UIO"];
        $this->line('  ✓ SHURE seeded');
    }

    // ─── IMPORTACIÓN YAMAHA Q2-2026 (id=2, en_aduana) ───────────────────────

    private function seedYamaha(): void
    {
        $this->line('Seeding YAMAHA Q2-2026...');

        $impId   = 2;
        $empresa = 1;
        $bodega  = 4; // Bodega Importaciones (en aduana, no recibida)
        $provId  = 7; // YAMAHA CORPORATION
        $now     = now();

        $yaExiste = DB::table('compras')
            ->where('importacion_id', $impId)
            ->where('empresa_id', $empresa)
            ->exists();

        if ($yaExiste) {
            $this->line('  ⚠ YAMAHA: compra ya existe, omitiendo');
            $this->resumen[] = ['accion' => 'SKIP', 'detalle' => 'YAMAHA Q2-2026: compra ya existía'];
            return;
        }

        $lineas = [
            ['codigo' => 'YAM-001', 'cantidad' => 20, 'precio_unitario' => 420.00],
            ['codigo' => 'YAM-002', 'cantidad' => 20, 'precio_unitario' => 380.00],
            ['codigo' => 'YAM-003', 'cantidad' => 12, 'precio_unitario' => 650.00],
            ['codigo' => 'YAM-004', 'cantidad' => 10, 'precio_unitario' => 780.00],
        ];

        $subtotal = collect($lineas)->sum(fn($l) => $l['cantidad'] * $l['precio_unitario']);

        $compraId = DB::table('compras')->insertGetId([
            'empresa_id'         => $empresa,
            'centro_costo_id'    => 1,
            'proveedor_id'       => $provId,
            'importacion_id'     => $impId,
            'bodega_id'          => $bodega,
            'tipo_documento'     => 'EXT',
            'num_documento'      => 'YMH-2026-INV-0234',
            'fecha_emision'      => '2026-04-01',
            'fecha_registro'     => '2026-04-01',
            'subtotal_0'         => $subtotal,
            'subtotal_iva'       => 0,
            'total_iva'          => 0,
            'total_ice'          => 0,
            'total'              => $subtotal,
            'iva_asumido'        => false,
            'gasto_no_deducible' => false,
            'sustento_tributario' => 7,
            'tiene_pago'         => false,
            'estado'             => 'activa',
            'created_by'         => 1,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $this->insertarDetalles($compraId, $lineas, $empresa, 0);
        $this->upsertSaldos($lineas, $bodega, $empresa, 'fob');
        $this->resumen[] = ['accion' => 'CREATE', 'detalle' => "YAMAHA Q2-2026: compra #{$compraId} + " . count($lineas) . " líneas + saldos en Bodega Importaciones (total: \$$subtotal)"];
        $this->line('  ✓ YAMAHA seeded');
    }

    // ─── IMPORTACIÓN CHAUVET Q2-2026 (id=3, en_transito) ────────────────────

    private function seedChauvet(): void
    {
        $this->line('Seeding CHAUVET Q2-2026...');

        $impId   = 3;
        $empresa = 1;
        $bodega  = 4; // Bodega Importaciones
        $provId  = 8; // CHAUVET PROFESSIONAL LLC
        $now     = now();

        $yaExiste = DB::table('compras')
            ->where('importacion_id', $impId)
            ->where('empresa_id', $empresa)
            ->exists();

        if ($yaExiste) {
            $this->line('  ⚠ CHAUVET: compra ya existe, omitiendo');
            $this->resumen[] = ['accion' => 'SKIP', 'detalle' => 'CHAUVET Q2-2026: compra ya existía'];
            return;
        }

        $lineas = [
            ['codigo' => 'CHV-001', 'cantidad' => 20, 'precio_unitario' => 850.00],
            ['codigo' => 'CHV-002', 'cantidad' => 10, 'precio_unitario' => 180.00],
            ['codigo' => 'CHV-003', 'cantidad' => 20, 'precio_unitario' => 120.00],
            ['codigo' => 'CHV-004', 'cantidad' => 10, 'precio_unitario' => 290.00],
        ];

        $subtotal = collect($lineas)->sum(fn($l) => $l['cantidad'] * $l['precio_unitario']);

        $compraId = DB::table('compras')->insertGetId([
            'empresa_id'         => $empresa,
            'centro_costo_id'    => 1,
            'proveedor_id'       => $provId,
            'importacion_id'     => $impId,
            'bodega_id'          => $bodega,
            'tipo_documento'     => 'EXT',
            'num_documento'      => 'CHV-2026-INV-0456',
            'fecha_emision'      => '2026-04-15',
            'fecha_registro'     => '2026-04-15',
            'subtotal_0'         => $subtotal,
            'subtotal_iva'       => 0,
            'total_iva'          => 0,
            'total_ice'          => 0,
            'total'              => $subtotal,
            'iva_asumido'        => false,
            'gasto_no_deducible' => false,
            'sustento_tributario' => 7,
            'tiene_pago'         => false,
            'estado'             => 'activa',
            'created_by'         => 1,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $this->insertarDetalles($compraId, $lineas, $empresa, 0);
        $this->upsertSaldos($lineas, $bodega, $empresa, 'fob');
        $this->resumen[] = ['accion' => 'CREATE', 'detalle' => "CHAUVET Q2-2026: compra #{$compraId} + " . count($lineas) . " líneas + saldos en Bodega Importaciones (total: \$$subtotal)"];
        $this->line('  ✓ CHAUVET seeded');
    }

    // ─── IMPORTACIÓN PEKIN-001 (id=4, en_transito) ───────────────────────────

    private function seedPekin(): void
    {
        $this->line('Seeding PEKIN-001...');

        $impId   = 4;
        $empresa = 1;
        $bodega  = 4; // Bodega Importaciones
        $now     = now();

        $yaExiste = DB::table('compras')
            ->where('importacion_id', $impId)
            ->where('empresa_id', $empresa)
            ->exists();

        if ($yaExiste) {
            $this->line('  ⚠ PEKIN: compra ya existe, omitiendo');
            $this->resumen[] = ['accion' => 'SKIP', 'detalle' => 'PEKIN-001: compra ya existía'];
            return;
        }

        // Crear proveedor Pioneer si no existe (PEKIN es importación de Pioneer DJ)
        $provId = DB::table('proveedores')
            ->where('empresa_id', $empresa)
            ->where('razon_social', 'PIONEER DJ CORPORATION')
            ->value('id');

        if (!$provId) {
            $provId = DB::table('proveedores')->insertGetId([
                'empresa_id'         => $empresa,
                'razon_social'       => 'PIONEER DJ CORPORATION',
                'tipo'               => 'internacional',
                'tipo_identificacion' => '08',
                'identificacion'     => 'JPN-PIONEER-001',
                'pais'               => 'JAPÓN',
                'divisa'             => 'USD',
                'tiene_credito'      => false,
                'dias_credito'       => 0,
                'estado'             => true,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
            $this->resumen[] = ['accion' => 'CREATE', 'detalle' => "Proveedor PIONEER DJ CORPORATION (id={$provId})"];
        }

        // DJ-001 y DJ-002 ya existen; se usan sus precios FOB actuales como precio de compra
        $lineas = [
            ['codigo' => 'DJ-001',  'cantidad' => 3,  'precio_unitario' => 650.00],
            ['codigo' => 'DJ-002',  'cantidad' => 3,  'precio_unitario' => 480.00],
            ['codigo' => 'DJ-003',  'cantidad' => 8,  'precio_unitario' =>  85.00],
            ['codigo' => 'CAB-010', 'cantidad' => 50, 'precio_unitario' =>  18.00],
        ];

        $subtotal = collect($lineas)->sum(fn($l) => $l['cantidad'] * $l['precio_unitario']);

        // Actualizar importacion FOB al total real de esta compra
        DB::table('importaciones')->where('id', $impId)->update(['costo_fob' => $subtotal]);

        $compraId = DB::table('compras')->insertGetId([
            'empresa_id'         => $empresa,
            'centro_costo_id'    => 1,
            'proveedor_id'       => $provId,
            'importacion_id'     => $impId,
            'bodega_id'          => $bodega,
            'tipo_documento'     => 'EXT',
            'num_documento'      => 'PKN-2026-0001',
            'fecha_emision'      => '2026-04-20',
            'fecha_registro'     => '2026-04-20',
            'subtotal_0'         => $subtotal,
            'subtotal_iva'       => 0,
            'total_iva'          => 0,
            'total_ice'          => 0,
            'total'              => $subtotal,
            'iva_asumido'        => false,
            'gasto_no_deducible' => false,
            'sustento_tributario' => 7,
            'tiene_pago'         => false,
            'estado'             => 'activa',
            'created_by'         => 1,
            'created_at'         => $now,
            'updated_at'         => $now,
        ]);

        $this->insertarDetalles($compraId, $lineas, $empresa, 0);
        $this->upsertSaldos($lineas, $bodega, $empresa, 'fob');
        $this->resumen[] = ['accion' => 'CREATE', 'detalle' => "PEKIN-001: compra #{$compraId} + " . count($lineas) . " líneas + saldos en Bodega Importaciones (total: \$$subtotal)"];
        $this->line('  ✓ PEKIN seeded');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Inserta las líneas de compra_detalles.
     * $porcentajeIva: 0 para facturas de importación (FOB, sin IVA local).
     */
    private function insertarDetalles(int $compraId, array $lineas, int $empresa, float $porcentajeIva): void
    {
        foreach ($lineas as $linea) {
            $prod = DB::table('productos')
                ->where('empresa_id', $empresa)
                ->where('codigo', $linea['codigo'])
                ->first(['id', 'nombre']);

            if (!$prod) {
                $this->warn("  ⚠ Producto {$linea['codigo']} no encontrado — línea omitida");
                continue;
            }

            $subtotal  = round($linea['cantidad'] * $linea['precio_unitario'], 4);
            $valorIva  = round($subtotal * $porcentajeIva / 100, 4);
            $total     = $subtotal + $valorIva;

            DB::table('compra_detalles')->insert([
                'compra_id'       => $compraId,
                'producto_id'     => $prod->id,
                'descripcion'     => $prod->nombre,
                'cantidad'        => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'descuento'       => 0,
                'subtotal'        => $subtotal,
                'porcentaje_iva'  => $porcentajeIva,
                'valor_iva'       => $valorIva,
                'total'           => $total,
                'es_activo_fijo'  => false,
            ]);
        }
    }

    /**
     * Upsert de inventario_saldos.
     * $modo 'fob' = costo FOB como costo_promedio inicial.
     * $modo 'post_proration' = usa el costo actual del producto (ya prorateado).
     */
    private function upsertSaldos(array $lineas, int $bodegaId, int $empresa, string $modo): void
    {
        foreach ($lineas as $linea) {
            $prod = DB::table('productos')
                ->where('empresa_id', $empresa)
                ->where('codigo', $linea['codigo'])
                ->first(['id', 'costo']);

            if (!$prod) continue;

            $costoProm = $modo === 'post_proration'
                ? (float) $prod->costo
                : (float) $linea['precio_unitario'];

            $saldoExiste = DB::table('inventario_saldos')
                ->where('producto_id', $prod->id)
                ->where('bodega_id', $bodegaId)
                ->exists();

            if ($saldoExiste) {
                // Incrementar stock, recalcular costo promedio ponderado
                $saldoActual = DB::table('inventario_saldos')
                    ->where('producto_id', $prod->id)
                    ->where('bodega_id', $bodegaId)
                    ->first();

                $stockNuevo  = (float) $saldoActual->stock_actual + (float) $linea['cantidad'];
                $costoNuevo  = $stockNuevo > 0
                    ? (((float) $saldoActual->stock_actual * (float) $saldoActual->costo_promedio)
                        + ((float) $linea['cantidad'] * $costoProm)) / $stockNuevo
                    : $costoProm;

                DB::table('inventario_saldos')
                    ->where('producto_id', $prod->id)
                    ->where('bodega_id', $bodegaId)
                    ->update([
                        'stock_actual'   => $stockNuevo,
                        'costo_promedio' => round($costoNuevo, 4),
                        'updated_at'     => now(),
                    ]);
            } else {
                DB::table('inventario_saldos')->insert([
                    'producto_id'       => $prod->id,
                    'bodega_id'         => $bodegaId,
                    'stock_actual'      => $linea['cantidad'],
                    'cantidad_reservada' => 0,
                    'costo_promedio'    => $costoProm,
                    'updated_at'        => now(),
                ]);
            }
        }
    }

    // ─── Resumen final ────────────────────────────────────────────────────────

    private function mostrarResumen(): void
    {
        $this->newLine();
        $this->info('══════════════════════════════════════════════');
        $this->info('  RESUMEN DEL SEEDER DE IMPORTACIONES');
        $this->info('══════════════════════════════════════════════');

        $grupos = ['CREATE' => [], 'UPDATE' => [], 'SKIP' => [], 'REVERT' => []];
        foreach ($this->resumen as $r) {
            $grupos[$r['accion']][] = $r['detalle'];
        }

        if (!empty($grupos['REVERT'])) {
            $this->line('<fg=yellow>REVERT:</>');
            foreach ($grupos['REVERT'] as $d) $this->line("  • $d");
        }
        if (!empty($grupos['CREATE'])) {
            $this->line('<fg=green>CREADO:</>');
            foreach ($grupos['CREATE'] as $d) $this->line("  ✓ $d");
        }
        if (!empty($grupos['UPDATE'])) {
            $this->line('<fg=cyan>ACTUALIZADO:</>');
            foreach ($grupos['UPDATE'] as $d) $this->line("  ↻ $d");
        }
        if (!empty($grupos['SKIP'])) {
            $this->line('<fg=gray>OMITIDO (ya existía):</>');
            foreach ($grupos['SKIP'] as $d) $this->line("  ⊘ $d");
        }

        $this->newLine();
        $this->info('Seeder completado exitosamente.');
    }
}
