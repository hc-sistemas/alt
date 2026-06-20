<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrarProductos extends Command
{
    protected $signature   = 'altamira:migrar-productos
                              {--preview  : Ver estructura sin migrar}
                              {--seedear  : Crear datos de prueba si no hay legacy}
                              {--limpiar  : Limpiar tablas antes de migrar}';
    protected $description = 'Migra erp_mp → productos y datos relacionados';

    public function handle(): void
    {
        $empresaId = 1;

        // ── Verificar qué tablas legacy existen ──────────────
        $tieneErpMp       = Schema::hasTable('erp_mp');
        $tieneErpMarcas   = Schema::hasTable('erp_marcas');
        $tieneErpMpSet    = Schema::hasTable('erp_mp_set');

        $this->info('📋 Verificando tablas legacy:');
        $this->line('   erp_mp:      ' . ($tieneErpMp     ? '✅ existe' : '❌ no existe'));
        $this->line('   erp_marcas:  ' . ($tieneErpMarcas ? '✅ existe' : '❌ no existe'));
        $this->line('   erp_mp_set:  ' . ($tieneErpMpSet  ? '✅ existe' : '❌ no existe'));

        // ── Preview ───────────────────────────────────────────
        if ($this->option('preview')) {
            if ($tieneErpMp) {
                $cols = Schema::getColumnListing('erp_mp');
                $this->info('Columnas erp_mp: ' . implode(', ', $cols));
                $total = DB::table('erp_mp')->count();
                $this->info("Total registros: {$total}");
                $muestra = DB::table('erp_mp')->limit(3)->get();
                $this->table(
                    \array_slice($cols, 0, 10),
                    $muestra->map(fn($r) =>
                        \array_slice((array)$r, 0, 10)
                    )->toArray()
                );
            }
            if ($tieneErpMarcas) {
                $cols = Schema::getColumnListing('erp_marcas');
                $this->info('Columnas erp_marcas: ' . implode(', ', $cols));
            }
            $this->info('✅ Preview listo. Sin cambios.');
            return;
        }

        // ── Limpiar si se pide ────────────────────────────────
        if ($this->option('limpiar')) {
            if ($this->confirm('⚠️ ¿Eliminar productos, marcas y categorías actuales?', false)) {
                DB::statement('ALTER TABLE inventario_saldos   DISABLE TRIGGER ALL');
                DB::statement('ALTER TABLE producto_series     DISABLE TRIGGER ALL');
                DB::statement('ALTER TABLE productos           DISABLE TRIGGER ALL');
                DB::statement('ALTER TABLE marcas              DISABLE TRIGGER ALL');
                DB::statement('ALTER TABLE categorias_producto DISABLE TRIGGER ALL');
                DB::table('inventario_saldos')->delete();
                DB::table('producto_series')->delete();
                DB::table('productos')->delete();
                DB::table('marcas')->delete();
                DB::table('categorias_producto')->delete();
                DB::statement('ALTER TABLE inventario_saldos   ENABLE TRIGGER ALL');
                DB::statement('ALTER TABLE producto_series     ENABLE TRIGGER ALL');
                DB::statement('ALTER TABLE productos           ENABLE TRIGGER ALL');
                DB::statement('ALTER TABLE marcas              ENABLE TRIGGER ALL');
                DB::statement('ALTER TABLE categorias_producto ENABLE TRIGGER ALL');
                $this->warn('🗑️ Tablas limpiadas.');
            }
        }

        // ── MIGRAR MARCAS ─────────────────────────────────────
        if ($tieneErpMarcas) {
            $this->migrarMarcas($empresaId);
        } else {
            $this->seedearMarcas($empresaId);
        }

        // ── MIGRAR CATEGORÍAS (siempre seedear) ───────────────
        $this->seedearCategorias($empresaId);

        // ── MIGRAR BODEGAS ────────────────────────────────────
        $this->seedearBodegas($empresaId);

        // ── MIGRAR PRODUCTOS ──────────────────────────────────
        if ($tieneErpMp) {
            $this->migrarProductos($empresaId);
        } else {
            $this->seedearProductos($empresaId);
        }

        // ── RESUMEN FINAL ─────────────────────────────────────
        $this->newLine();
        $this->info('✅ Migración completada.');
        $this->line('   Marcas:      ' . DB::table('marcas')->count());
        $this->line('   Categorías:  ' . DB::table('categorias_producto')->count());
        $this->line('   Bodegas:     ' . DB::table('bodegas')->where('empresa_id', $empresaId)->count());
        $this->line('   Productos:   ' . DB::table('productos')->where('empresa_id', $empresaId)->count());
    }

    // ── MIGRAR MARCAS DESDE erp_marcas ───────────────────────
    private function migrarMarcas(int $empresaId = 1): void
    {
        $this->info('🏷️  Migrando marcas desde erp_marcas...');
        $cols   = Schema::getColumnListing('erp_marcas');
        $colNom = $this->detectar($cols, ['mar_descripcion', 'nombre', 'descripcion', 'name', 'mar_nombre']);

        $registros = DB::table('erp_marcas')->get();
        $migradas  = 0;

        foreach ($registros as $reg) {
            $arr    = (array)$reg;
            $nombre = $colNom ? trim($arr[$colNom] ?? '') : '';
            if (empty($nombre)) continue;

            DB::table('marcas')->updateOrInsert(
                ['empresa_id' => $empresaId, 'nombre' => $nombre],
                [
                    'empresa_id' => $empresaId,
                    'nombre'     => $nombre,
                    'estado'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $migradas++;
        }
        $this->line("   ✅ {$migradas} marcas migradas.");
    }

    // ── SEEDEAR MARCAS SI NO HAY LEGACY ──────────────────────
    private function seedearMarcas(int $empresaId = 1): void
    {
        $this->info('🏷️  Creando marcas de prueba...');
        $marcas = [
            'Shure', 'Yamaha', 'Pioneer DJ', 'QSC', 'Chauvet',
            'Martin', 'Sennheiser', 'Behringer', 'JBL', 'Crown',
            'Allen & Heath', 'Genérico',
        ];

        foreach ($marcas as $nombre) {
            DB::table('marcas')->updateOrInsert(
                ['empresa_id' => $empresaId, 'nombre' => $nombre],
                [
                    'empresa_id' => $empresaId,
                    'nombre'     => $nombre,
                    'estado'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $this->line("   ✅ {$nombre}");
        }
    }

    // ── SEEDEAR CATEGORÍAS ────────────────────────────────────
    private function seedearCategorias(int $empresaId = 1): void
    {
        $this->info('📂 Creando categorías...');

        $categorias = [
            // Nivel 1
            ['nombre' => 'Audio Profesional',     'padre' => null],
            ['nombre' => 'Iluminación',            'padre' => null],
            ['nombre' => 'Video y Proyección',     'padre' => null],
            ['nombre' => 'Repuestos y Partes',     'padre' => null],
            ['nombre' => 'Insumos y Accesorios',   'padre' => null],
            ['nombre' => 'Servicios',              'padre' => null],
            // Audio — nivel 2
            ['nombre' => 'Micrófonos',             'padre' => 'Audio Profesional'],
            ['nombre' => 'Consolas de Mezcla',     'padre' => 'Audio Profesional'],
            ['nombre' => 'Amplificadores',         'padre' => 'Audio Profesional'],
            ['nombre' => 'Parlantes y Cabinas',    'padre' => 'Audio Profesional'],
            ['nombre' => 'Controladores DJ',       'padre' => 'Audio Profesional'],
            ['nombre' => 'Procesadores de Señal',  'padre' => 'Audio Profesional'],
            ['nombre' => 'Cables de Audio',        'padre' => 'Audio Profesional'],
            // Iluminación — nivel 2
            ['nombre' => 'Cabezas Móviles',        'padre' => 'Iluminación'],
            ['nombre' => 'Láseres',                'padre' => 'Iluminación'],
            ['nombre' => 'Controladores DMX',      'padre' => 'Iluminación'],
            ['nombre' => 'Efectos LED',             'padre' => 'Iluminación'],
            // Repuestos — nivel 2
            ['nombre' => 'Repuestos Electrónicos', 'padre' => 'Repuestos y Partes'],
            ['nombre' => 'Cables de Poder',        'padre' => 'Repuestos y Partes'],
            ['nombre' => 'Conectores',             'padre' => 'Repuestos y Partes'],
        ];

        foreach ($categorias as $cat) {
            $padreId = null;
            if ($cat['padre']) {
                $padreId = DB::table('categorias_producto')
                    ->where('empresa_id', $empresaId)
                    ->where('nombre', $cat['padre'])
                    ->value('id');
            }

            $existe = DB::table('categorias_producto')
                ->where('empresa_id', $empresaId)
                ->where('nombre', $cat['nombre'])
                ->exists();

            if (!$existe) {
                DB::table('categorias_producto')->insert([
                    'empresa_id'         => $empresaId,
                    'nombre'             => $cat['nombre'],
                    'categoria_padre_id' => $padreId,
                    'estado'             => true,
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }

            $prefijo = $cat['padre'] ? '      ↳ ' : '   ';
            $this->line("{$prefijo}✅ {$cat['nombre']}");
        }
    }

    // ── SEEDEAR BODEGAS ───────────────────────────────────────
    private function seedearBodegas(int $empresaId): void
    {
        $this->info('🏭 Creando bodegas...');

        $bodegas = [
            ['nombre' => 'Bodega Principal',    'tipo' => 'general'],
            ['nombre' => 'Bodega Taller',        'tipo' => 'taller'],
            ['nombre' => 'Bodega Importaciones', 'tipo' => 'importacion'],
            ['nombre' => 'Bodega Cuarentena',    'tipo' => 'cuarentena'],
            ['nombre' => 'Bodega Reservas',      'tipo' => 'reserva'],
        ];

        foreach ($bodegas as $b) {
            $existe = DB::table('bodegas')
                ->where('empresa_id', $empresaId)
                ->where('nombre', $b['nombre'])
                ->exists();

            if (!$existe) {
                DB::table('bodegas')->insert([
                    'empresa_id' => $empresaId,
                    'nombre'     => $b['nombre'],
                    'tipo'       => $b['tipo'],
                    'es_virtual' => false,
                    'estado'     => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            $this->line("   ✅ {$b['nombre']} [{$b['tipo']}]");
        }
    }

    // ── MIGRAR PRODUCTOS DESDE erp_mp ────────────────────────
    private function migrarProductos(int $empresaId): void
    {
        $this->info('📦 Migrando productos desde erp_mp...');
        $cols = Schema::getColumnListing('erp_mp');
        $this->info('   Columnas detectadas: ' . implode(', ', $cols));

        // mp_a=codigo, mp_b=nombre, mp_e=pvp, mp_f=pvd, mp_g=costo
        $colCodigo = $this->detectar($cols, ['mp_a', 'codigo', 'code', 'cod_producto', 'sku']);
        $colNombre = $this->detectar($cols, ['mp_b', 'nombre', 'name', 'descripcion', 'mp_descripcion']);
        $colPvp    = $this->detectar($cols, ['mp_e', 'pvp', 'precio_venta', 'precio', 'price']);
        $colPvd    = $this->detectar($cols, ['mp_f', 'pvd', 'precio_distribuidor', 'precio_dist']);
        $colCosto  = $this->detectar($cols, ['mp_g', 'costo', 'cost', 'precio_costo', 'costo_unitario']);
        $colUnidad = $this->detectar($cols, ['mp_unidad', 'unidad', 'unit', 'mp_c']);
        $colIva    = $this->detectar($cols, ['mp_iva', 'iva', 'porcentaje_iva', 'ice_iva']);
        $colStock  = $this->detectar($cols, ['mp_stock', 'stock', 'existencia', 'cantidad']);
        $colEstado = $this->detectar($cols, ['mp_estado', 'estado', 'activo', 'status', 'mp_activo']);

        $this->info("   Mapeando: codigo={$colCodigo} | nombre={$colNombre} | pvp={$colPvp} | costo={$colCosto}");

        $migrados = 0;
        $errores  = 0;
        $bodegaId = DB::table('bodegas')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'general')
            ->value('id');

        DB::table('erp_mp')->orderBy('id')->chunk(100,
            function ($registros) use (
                $empresaId, $bodegaId,
                $colCodigo, $colNombre, $colPvp, $colPvd,
                $colCosto, $colUnidad, $colIva, $colStock,
                $colEstado, &$migrados, &$errores
            ) {
                foreach ($registros as $reg) {
                    try {
                        $arr    = (array)$reg;
                        $codigo = $colCodigo
                            ? trim($arr[$colCodigo] ?? '')
                            : 'PROD-' . str_pad($migrados + 1, 4, '0', STR_PAD_LEFT);
                        $nombre = $colNombre
                            ? trim($arr[$colNombre] ?? '')
                            : 'Producto migrado';

                        if (empty($codigo) || empty($nombre)) {
                            $errores++;
                            continue;
                        }

                        if (DB::table('productos')
                            ->where('empresa_id', $empresaId)
                            ->where('codigo', $codigo)
                            ->exists()) {
                            continue;
                        }

                        $pvp    = $colPvp    ? (float)($arr[$colPvp]    ?? 0)  : 0;
                        $pvd    = $colPvd    ? (float)($arr[$colPvd]    ?? 0)  : 0;
                        $costo  = $colCosto  ? (float)($arr[$colCosto]  ?? 0)  : 0;
                        $stock  = $colStock  ? (float)($arr[$colStock]  ?? 0)  : 0;
                        $iva    = $colIva    ? (float)($arr[$colIva]    ?? 15) : 15;
                        $unidad = $colUnidad ? trim($arr[$colUnidad] ?? 'unidad') : 'unidad';
                        $estado = true;

                        if ($colEstado) {
                            $val    = $arr[$colEstado] ?? 1;
                            $estado = \in_array(
                                strtolower((string)$val),
                                ['1', 'true', 'activo', 'active', 's', 'si', 'yes']
                            );
                        }

                        $tipo = 'producto';
                        if (str_contains(strtolower($nombre), 'servicio') ||
                            str_contains(strtolower($nombre), 'mano de obra') ||
                            str_contains(strtolower($nombre), 'instalacion')) {
                            $tipo = 'servicio';
                        } elseif (str_contains(strtolower($nombre), 'repuesto') ||
                                  str_contains(strtolower($nombre), 'parte')) {
                            $tipo = 'repuesto';
                        }

                        $productoId = DB::table('productos')->insertGetId([
                            'empresa_id'     => $empresaId,
                            'codigo'         => $codigo,
                            'nombre'         => $nombre,
                            'tipo'           => $tipo,
                            'unidad'         => $unidad ?: 'unidad',
                            'costo'          => $costo,
                            'pvp'            => $pvp,
                            'pvd'            => $pvd,
                            'porcentaje_iva' => $iva ?: 15,
                            'stock_minimo'   => 0,
                            'estado'         => $estado,
                            'created_at'     => now(),
                            'updated_at'     => now(),
                        ]);

                        if ($stock > 0 && $bodegaId) {
                            DB::table('inventario_saldos')->updateOrInsert(
                                ['producto_id' => $productoId, 'bodega_id' => $bodegaId],
                                [
                                    'stock_actual'   => $stock,
                                    'costo_promedio' => $costo,
                                    'updated_at'     => now(),
                                ]
                            );
                        }

                        $migrados++;

                    } catch (\Exception $e) {
                        $errores++;
                        $this->warn('   ⚠️ Error: ' . $e->getMessage());
                    }
                }
            }
        );

        $this->line("   ✅ {$migrados} productos migrados. ⚠️ {$errores} errores.");
    }

    // ── SEEDEAR PRODUCTOS DE PRUEBA ───────────────────────────
    private function seedearProductos(int $empresaId): void
    {
        $this->info('📦 Creando productos de prueba (Altamira Light & Sound)...');

        $bodegaId = DB::table('bodegas')
            ->where('empresa_id', $empresaId)
            ->where('tipo', 'general')
            ->value('id');

        $getMarca = fn(string $nombre) => DB::table('marcas')
            ->where('empresa_id', $empresaId)
            ->where('nombre', 'like', "%{$nombre}%")
            ->value('id');

        $getCat = fn(string $nombre) => DB::table('categorias_producto')
            ->where('empresa_id', $empresaId)
            ->where('nombre', 'like', "%{$nombre}%")
            ->value('id');

        $productos = [
            // ── AUDIO PROFESIONAL ──
            ['codigo' => 'MIC-001', 'nombre' => 'Micrófono Inalámbrico Shure BLX24/SM58',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Shure', 'categoria' => 'Micrófonos',
             'costo' => 320.00, 'pvp' => 480.00, 'pvd' => 420.00,
             'iva' => 15, 'stock' => 8, 'serie' => false],

            ['codigo' => 'MIC-002', 'nombre' => 'Micrófono Condensador Shure SM7B',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Shure', 'categoria' => 'Micrófonos',
             'costo' => 280.00, 'pvp' => 420.00, 'pvd' => 370.00,
             'iva' => 15, 'stock' => 5, 'serie' => false],

            ['codigo' => 'CON-001', 'nombre' => 'Consola Yamaha MG16XU 16 Canales USB',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Yamaha', 'categoria' => 'Consolas de Mezcla',
             'costo' => 680.00, 'pvp' => 980.00, 'pvd' => 850.00,
             'iva' => 15, 'stock' => 3, 'serie' => true],

            ['codigo' => 'CON-002', 'nombre' => 'Consola Allen & Heath SQ-5 48 Canales Digital',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Allen & Heath', 'categoria' => 'Consolas de Mezcla',
             'costo' => 2800.00, 'pvp' => 3950.00, 'pvd' => 3500.00,
             'iva' => 15, 'stock' => 2, 'serie' => true],

            ['codigo' => 'AMP-001', 'nombre' => 'Amplificador QSC GX5 500W Potencia',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'QSC', 'categoria' => 'Amplificadores',
             'costo' => 420.00, 'pvp' => 620.00, 'pvd' => 550.00,
             'iva' => 15, 'stock' => 6, 'serie' => true],

            ['codigo' => 'AMP-002', 'nombre' => 'Amplificador Crown XTi 2002 650W',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Crown', 'categoria' => 'Amplificadores',
             'costo' => 580.00, 'pvp' => 850.00, 'pvd' => 750.00,
             'iva' => 15, 'stock' => 4, 'serie' => true],

            ['codigo' => 'PAR-001', 'nombre' => 'Parlante JBL SRX835P 15" Activo 2000W',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'JBL', 'categoria' => 'Parlantes y Cabinas',
             'costo' => 1200.00, 'pvp' => 1750.00, 'pvd' => 1550.00,
             'iva' => 15, 'stock' => 4, 'serie' => true],

            ['codigo' => 'PAR-002', 'nombre' => 'Subwoofer JBL SRX818SP 18" Activo 1000W',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'JBL', 'categoria' => 'Parlantes y Cabinas',
             'costo' => 980.00, 'pvp' => 1450.00, 'pvd' => 1280.00,
             'iva' => 15, 'stock' => 2, 'serie' => true],

            ['codigo' => 'DJ-001', 'nombre' => 'Controlador Pioneer DDJ-FLX6 4 Decks',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Pioneer DJ', 'categoria' => 'Controladores DJ',
             'costo' => 650.00, 'pvp' => 950.00, 'pvd' => 820.00,
             'iva' => 15, 'stock' => 5, 'serie' => true],

            ['codigo' => 'DJ-002', 'nombre' => 'Tornamesa Pioneer PLX-1000 Direct Drive',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Pioneer DJ', 'categoria' => 'Controladores DJ',
             'costo' => 480.00, 'pvp' => 720.00, 'pvd' => 640.00,
             'iva' => 15, 'stock' => 3, 'serie' => true],

            ['codigo' => 'PRO-001', 'nombre' => 'Procesador de Señal DBX DriveRack PA2',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Behringer', 'categoria' => 'Procesadores de Señal',
             'costo' => 220.00, 'pvp' => 340.00, 'pvd' => 300.00,
             'iva' => 15, 'stock' => 4, 'serie' => false],

            // ── ILUMINACIÓN ──
            ['codigo' => 'ILU-001', 'nombre' => 'Cabeza Móvil Chauvet Intimidator Spot 375Z IRC',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Chauvet', 'categoria' => 'Cabezas Móviles',
             'costo' => 680.00, 'pvp' => 980.00, 'pvd' => 860.00,
             'iva' => 15, 'stock' => 6, 'serie' => true],

            ['codigo' => 'ILU-002', 'nombre' => 'Cabeza Móvil Martin MAC Aura XB LED',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Martin', 'categoria' => 'Cabezas Móviles',
             'costo' => 2200.00, 'pvp' => 3100.00, 'pvd' => 2750.00,
             'iva' => 15, 'stock' => 2, 'serie' => true],

            ['codigo' => 'ILU-003', 'nombre' => 'Controlador DMX Chauvet Obey 40 32 Canales',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Chauvet', 'categoria' => 'Controladores DMX',
             'costo' => 85.00, 'pvp' => 130.00, 'pvd' => 115.00,
             'iva' => 15, 'stock' => 8, 'serie' => false],

            ['codigo' => 'ILU-004', 'nombre' => 'Efecto LED ADJ Mega Bar 50RGB RC',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Chauvet', 'categoria' => 'Efectos LED',
             'costo' => 120.00, 'pvp' => 180.00, 'pvd' => 160.00,
             'iva' => 15, 'stock' => 10, 'serie' => false],

            // ── CABLES Y ACCESORIOS ──
            ['codigo' => 'CAB-001', 'nombre' => 'Cable XLR Macho-Hembra 10 metros Neutrik',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Genérico', 'categoria' => 'Cables de Audio',
             'costo' => 12.00, 'pvp' => 22.00, 'pvd' => 18.00,
             'iva' => 15, 'stock' => 50, 'serie' => false],

            ['codigo' => 'CAB-002', 'nombre' => 'Cable Speakon 4P 10 metros',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Genérico', 'categoria' => 'Cables de Audio',
             'costo' => 15.00, 'pvp' => 28.00, 'pvd' => 23.00,
             'iva' => 15, 'stock' => 30, 'serie' => false],

            ['codigo' => 'CAB-003', 'nombre' => 'Cable de Poder Uso Rudo 3x14 AWG 5m',
             'tipo' => 'producto', 'unidad' => 'unidad',
             'marca' => 'Genérico', 'categoria' => 'Cables de Poder',
             'costo' => 8.00, 'pvp' => 15.00, 'pvd' => 12.00,
             'iva' => 15, 'stock' => 40, 'serie' => false],

            // ── REPUESTOS ──
            ['codigo' => 'REP-001', 'nombre' => 'Transistor de Potencia IRFP250 MOSFET',
             'tipo' => 'repuesto', 'unidad' => 'unidad',
             'marca' => 'Genérico', 'categoria' => 'Repuestos Electrónicos',
             'costo' => 2.50, 'pvp' => 6.00, 'pvd' => 5.00,
             'iva' => 15, 'stock' => 100, 'serie' => false],

            ['codigo' => 'REP-002', 'nombre' => 'Conector XLR 3P Macho Neutrik NC3MXX',
             'tipo' => 'repuesto', 'unidad' => 'unidad',
             'marca' => 'Genérico', 'categoria' => 'Conectores',
             'costo' => 1.80, 'pvp' => 4.50, 'pvd' => 3.50,
             'iva' => 15, 'stock' => 200, 'serie' => false],

            ['codigo' => 'REP-003', 'nombre' => 'Soldadura de Estaño 60/40 Rollo 250g',
             'tipo' => 'insumo', 'unidad' => 'rollo',
             'marca' => 'Genérico', 'categoria' => 'Repuestos Electrónicos',
             'costo' => 8.00, 'pvp' => 15.00, 'pvd' => 12.00,
             'iva' => 15, 'stock' => 20, 'serie' => false],

            // ── SERVICIOS ──
            ['codigo' => 'SRV-001', 'nombre' => 'Servicio de Reparación Electrónica — Hora',
             'tipo' => 'servicio', 'unidad' => 'hora',
             'marca' => 'Genérico', 'categoria' => 'Servicios',
             'costo' => 0, 'pvp' => 45.00, 'pvd' => 40.00,
             'iva' => 15, 'stock' => 0, 'serie' => false],

            ['codigo' => 'SRV-002', 'nombre' => 'Alquiler Sistema de Sonido Completo — Día',
             'tipo' => 'servicio', 'unidad' => 'dia',
             'marca' => 'Genérico', 'categoria' => 'Servicios',
             'costo' => 0, 'pvp' => 350.00, 'pvd' => 300.00,
             'iva' => 15, 'stock' => 0, 'serie' => false],

            ['codigo' => 'SRV-003', 'nombre' => 'Instalación y Configuración de Equipos',
             'tipo' => 'servicio', 'unidad' => 'servicio',
             'marca' => 'Genérico', 'categoria' => 'Servicios',
             'costo' => 0, 'pvp' => 120.00, 'pvd' => 100.00,
             'iva' => 15, 'stock' => 0, 'serie' => false],
        ];

        $creados = 0;
        foreach ($productos as $data) {
            if (DB::table('productos')
                ->where('empresa_id', $empresaId)
                ->where('codigo', $data['codigo'])
                ->exists()) {
                $this->line("   ⏭️  Ya existe: {$data['codigo']}");
                continue;
            }

            $marcaId     = $getMarca($data['marca']);
            $categoriaId = $getCat($data['categoria']);

            $productoId = DB::table('productos')->insertGetId([
                'empresa_id'     => $empresaId,
                'marca_id'       => $marcaId,
                'categoria_id'   => $categoriaId,
                'codigo'         => $data['codigo'],
                'nombre'         => $data['nombre'],
                'tipo'           => $data['tipo'],
                'unidad'         => $data['unidad'],
                'costo'          => $data['costo'],
                'pvp'            => $data['pvp'],
                'pvd'            => $data['pvd'],
                'porcentaje_iva' => $data['iva'],
                'requiere_serie' => $data['serie'],
                'stock_minimo'   => 2,
                'estado'         => true,
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            if ($data['stock'] > 0 && $bodegaId) {
                DB::table('inventario_saldos')->insertOrIgnore([
                    'producto_id'    => $productoId,
                    'bodega_id'      => $bodegaId,
                    'stock_actual'   => $data['stock'],
                    'costo_promedio' => $data['costo'],
                    'updated_at'     => now(),
                ]);
            }

            $this->line("   ✅ [{$data['codigo']}] {$data['nombre']} — Stock: {$data['stock']}");
            $creados++;
        }

        $this->line("   ✅ {$creados} productos creados.");
    }

    // ── HELPER: detectar columna ──────────────────────────────
    private function detectar(array $cols, array $opciones): ?string
    {
        foreach ($opciones as $op) {
            if (\in_array($op, $cols)) return $op;
        }
        return null;
    }
}
