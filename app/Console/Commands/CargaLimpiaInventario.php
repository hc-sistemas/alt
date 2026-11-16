<?php

namespace App\Console\Commands;

use App\Models\PlanCuenta;
use App\Services\AsientoService;
use App\Services\CuentasProductoService;
use App\Services\InventarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * CARGA LIMPIA DEL INVENTARIO INICIAL (decisión de Edwin del 12/11/2026, «Decisiones_pendientes_contador»).
 *
 * Qué hace, en este orden y dentro de UNA transacción:
 *  1. Borra directamente los 13 códigos dados de baja (sin ajuste de inventario ni asiento).
 *  2. Renumera los productos que el Excel marca en «Código anterior» (conserva el id y deja el código viejo en
 *     la descripción como «(COD. ANT. XXX)»).
 *  3. Actualiza cada producto del Excel (nombre, marca, descripción, referencia, unidad, tipo, precios, costo y
 *     categoría/subcategoría) y crea los nuevos (IVA 15 %). Los servicios SER se crean como tipo «servicio».
 *  4. Deja en cero TODO el stock y el kárdex que quedó del piloto (sin asientos).
 *  5. Carga el saldo inicial al 31/10/2026 (cantidad física y costo unitario) en la bodega Principal.
 *  6. Registra UN asiento de apertura: Debe → cuentas de inventario por subcategoría; Haber → 3.01.01 Capital Neto.
 *     (Los activos fijos de Activos_Fijos_ERP_Altamira_UIO.xlsx, $6.040,96, se registran aparte cuando llegue el detalle.)
 *
 * Por defecto SIMULA: valida todo, muestra el resumen y escribe un CSV en storage/app/private/carga-limpia/.
 * Con --aplicar guarda, después de escribir un respaldo JSON de productos y saldos. No toca ninguna otra tabla.
 *
 * Se niega a correr si existen documentos (facturas, compras, traslados, recepciones, taller, asientos…) que
 * referencien productos: primero hay que borrar los datos de prueba con `altamira:limpiar-datos-prueba`.
 */
class CargaLimpiaInventario extends Command
{
    protected $signature = 'inventario:carga-limpia
                            {archivo? : Excel de carga (por defecto docs/Oct2026/Corregidos/Inventario_Altamira_ERP_Carga.xlsx)}
                            {--aplicar : Guarda los cambios (sin esta opción solo simula)}
                            {--empresa=1 : Empresa dueña de los productos y de la bodega}
                            {--bodega=1 : Bodega donde entra el saldo inicial (Principal)}
                            {--fecha=2026-10-31 : Fecha del saldo inicial y del asiento de apertura}
                            {--valor-esperado=175015.00 : Valor total del inventario según el contador (0 = no validar)}';

    protected $description = 'Carga limpia del inventario inicial al 31/10/2026 (simula por defecto)';

    private const CODIGO_VALIDO = '/^[A-Z]{3}\.\d{6}$/';
    private const IVA_NUEVOS = 15;
    private const DOC_TIPO = 'SALDO_INICIAL';
    private const ASIENTO_TIPO = 'APERTURA_INVENTARIO';

    /** Códigos dados de baja por el cliente (hoja «4. No están en el Excel»): se borran de la base. */
    private const BAJAS = [
        'AYR.000045', 'AYR.000070', 'AYR.000110', 'AYR.000237', 'AYR.000352', 'AYR.000404',
        'CMP.000001', 'CMP.000005', 'DJS.000061', 'DJS.000072', 'DJS.000110', 'SON.000160', 'SON.000242',
    ];

    /** Tablas con productos que equivalen a «documentos reales»: si tienen filas, no se carga. */
    private const TABLAS_DOCUMENTOS = [
        'factura_detalles', 'compra_detalles', 'devoluciones_compra_detalles', 'guia_remision_detalles',
        'nota_credito_detalles', 'prefactura_detalles', 'proforma_detalles', 'recepcion_detalles',
        'recepcion_escaneos', 'taller_ot_repuestos', 'traslado_detalles',
    ];

    /** Nombres de categoría del Excel que difieren del sistema. */
    private const ALIAS_CATEGORIA = ['parlantes y sistema de sonido' => 'parlantes y sistemas de sonido'];
    /** Subcategorías del Excel que significan «la categoría misma». */
    private const SIN_SUBCATEGORIA = ['(sin subcategoria)', 'sin subcategoria', 'todas las subcategorias (venta esporadica)'];

    public function handle(AsientoService $asientos, InventarioService $inventario, CuentasProductoService $cuentas): int
    {
        ini_set('memory_limit', '1024M');
        $aplicar   = (bool) $this->option('aplicar');
        $empresaId = (int) $this->option('empresa');
        $bodegaId  = (int) $this->option('bodega');
        $fecha     = (string) $this->option('fecha');
        $archivo   = $this->argument('archivo') ?: base_path('docs/Oct2026/Corregidos/Inventario_Altamira_ERP_Carga.xlsx');

        $errores = [];
        $avisos  = [];

        // ── Lectura del Excel ───────────────────────────────────────────
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }
        $filas = $this->leerExcel($archivo, $errores);

        // ── Mapas de la base ────────────────────────────────────────────
        $cat = $this->mapaCategorias();
        $marcas = [];
        foreach (DB::table('marcas')->get() as $m) {
            $marcas[$this->norm($m->nombre)] = (int) $m->id;
        }
        $reqSerie = DB::table('categorias_producto')->pluck('requiere_serie', 'id');
        $existentes = DB::table('productos')->get()->keyBy('codigo');

        $bodega = DB::table('bodegas')->where('id', $bodegaId)->first();
        if (!$bodega || (int) $bodega->empresa_id !== $empresaId) {
            $errores[] = "La bodega {$bodegaId} no existe o no pertenece a la empresa {$empresaId}.";
        }
        $capital = PlanCuenta::where('codigo', '3.01.01')->first();
        if (!$capital || !$capital->permite_asientos) {
            $errores[] = 'No existe la cuenta 3.01.01 Capital Neto (o no permite asientos).';
        }
        $ejercicio = DB::table('ejercicios_contables')->where('empresa_id', $empresaId)
            ->where('anio', (int) substr($fecha, 0, 4))->where('mes', (int) substr($fecha, 5, 2))->first();
        if (!$ejercicio || $ejercicio->estado !== 'abierto') {
            $errores[] = 'El período contable de ' . substr($fecha, 0, 7) . ' no existe o no está abierto (Contabilidad → Ejercicios).';
        }
        if (DB::table('asientos_contables')->where('documento_tipo', self::ASIENTO_TIPO)->where('empresa_id', $empresaId)->exists()) {
            $errores[] = 'Ya existe el asiento de apertura de inventario: la carga limpia ya se aplicó en esta base.';
        }

        // ── Guardas: documentos reales con productos ────────────────────
        foreach (self::TABLAS_DOCUMENTOS as $tabla) {
            $n = DB::table($tabla)->count();
            if ($n > 0) {
                $errores[] = "La tabla {$tabla} tiene {$n} fila(s) con productos: borre los datos de prueba antes (php artisan altamira:limpiar-datos-prueba).";
            }
        }
        $nAsientos = DB::table('asientos_contables')->where('empresa_id', $empresaId)->count();
        if ($nAsientos > 0 && !DB::table('asientos_contables')->where('documento_tipo', self::ASIENTO_TIPO)->exists()) {
            $avisos[] = "Hay {$nAsientos} asiento(s) en la empresa: la apertura se suma a ellos (verifique que no sean pruebas).";
        }

        // ── Plan de operaciones ─────────────────────────────────────────
        $porCodigoAnterior = [];
        foreach ($filas as $f) {
            if ($f['anterior'] !== '') {
                $porCodigoAnterior[$f['anterior']] = $f['codigo'];
            }
        }

        $bajasExistentes = array_values(array_filter(self::BAJAS, fn ($c) => isset($existentes[$c])));
        foreach (self::BAJAS as $b) {
            if (isset($porCodigoAnterior[$b]) || isset(array_flip(array_column($filas, 'codigo'))[$b])) {
                $errores[] = "El código de baja {$b} aparece en el Excel de carga.";
            }
        }

        $plan = [];       // codigo nuevo => datos preparados
        $renombres = [];  // codigo anterior => codigo nuevo
        $crear = 0;
        $actualizar = 0;
        $marcasNuevas = [];
        $totalValor = 0.0;
        $totalUnidades = 0.0;
        $porInventario = []; // cuenta_inventario_id => valor
        $detalleCuentas = []; // codigo PC-CAT => [valor, unidades, productos]
        $sinStockSaldo = 0;

        foreach ($filas as $f) {
            $categoriaId = $this->resolverCategoria($f['categoria'], $f['subcategoria'], $cat);
            if (!$categoriaId) {
                $errores[] = "{$f['codigo']}: categoría «{$f['categoria']}» / subcategoría «{$f['subcategoria']}» no existe en el sistema.";
                continue;
            }
            $cuentasProd = $cuentas->paraCategoria($categoriaId);
            $esServicio = $f['servicio'];
            if (!$cuentasProd) {
                $errores[] = "{$f['codigo']}: la categoría no tiene cuentas contables (PC-CAT).";
                continue;
            }
            if (!$esServicio && !$cuentasProd['inventario']) {
                $errores[] = "{$f['codigo']}: producto de inventario en una subcategoría sin cuenta de inventario ({$cuentasProd['codigo']}).";
                continue;
            }
            if ($esServicio && !$cuentasProd['venta']) {
                $errores[] = "{$f['codigo']}: servicio en una subcategoría sin cuenta de venta ({$cuentasProd['codigo']}).";
                continue;
            }

            $marcaN = $this->norm($f['marca']);
            if ($marcaN !== '' && !isset($marcas[$marcaN]) && !isset($marcasNuevas[$marcaN])) {
                $marcasNuevas[$marcaN] = $f['marca'];
            }

            // ¿producto existente? por código nuevo o por código anterior
            $actual = $existentes[$f['codigo']] ?? null;
            $accion = 'crear';
            if ($f['anterior'] !== '') {
                $viejo = $existentes[$f['anterior']] ?? null;
                if ($viejo && $actual && $viejo->id !== $actual->id) {
                    $errores[] = "{$f['codigo']}: ya existe un producto con ese código y también con el anterior {$f['anterior']}.";
                    continue;
                }
                if ($viejo && !$actual) {
                    $renombres[$f['anterior']] = $f['codigo'];
                    $actual = $viejo;
                    $accion = 'renumerar';
                } elseif ($actual) {
                    $accion = 'actualizar'; // ya renumerado en una corrida anterior
                }
            } elseif ($actual) {
                $accion = 'actualizar';
            }
            $accion === 'crear' ? $crear++ : $actualizar++;

            $cantidad = $esServicio ? 0.0 : $f['cantidad'];
            $valor = round($cantidad * $f['costo'], 4);
            $totalValor += $valor;
            $totalUnidades += $cantidad;
            if ($cantidad > 0 && $cuentasProd['inventario']) {
                $porInventario[$cuentasProd['inventario']] = ($porInventario[$cuentasProd['inventario']] ?? 0) + $valor;
            }
            $d = &$detalleCuentas[$cuentasProd['codigo']];
            $d = ($d ?? ['valor' => 0.0, 'unidades' => 0.0, 'productos' => 0]);
            $d['valor'] += $valor;
            $d['unidades'] += $cantidad;
            $d['productos']++;
            unset($d);

            if ($cantidad > 0) {
                $sinStockSaldo++;
            }

            $plan[$f['codigo']] = $f + [
                'accion'       => $accion,
                'actual_id'    => $actual?->id,
                'categoria_id' => $categoriaId,
                'inv_cuenta'   => $cuentasProd['inventario'],
                'cantidad'     => $cantidad,
                'valor'        => $valor,
                'req_serie'    => $esServicio ? false : (bool) ($reqSerie[$categoriaId] ?? false),
            ];
        }

        // Productos del sistema que el Excel no incluye (ni bajas ni renumerados)
        $enExcel = array_flip(array_merge(array_column($filas, 'codigo'), array_keys($porCodigoAnterior)));
        $fueraDeExcel = [];
        foreach ($existentes as $codigo => $p) {
            if (!isset($enExcel[$codigo]) && !in_array($codigo, self::BAJAS, true)) {
                $fueraDeExcel[] = $codigo;
            }
        }
        if ($fueraDeExcel) {
            $avisos[] = count($fueraDeExcel) . ' producto(s) del sistema no están en el Excel ni en las bajas: quedan SIN TOCAR y con stock 0 (' . implode(', ', array_slice($fueraDeExcel, 0, 8)) . (count($fueraDeExcel) > 8 ? '…' : '') . ').';
        }

        $esperado = (float) $this->option('valor-esperado');
        if ($esperado > 0 && abs($totalValor - $esperado) > 0.05) {
            $errores[] = 'El valor total del inventario (' . number_format($totalValor, 2) . ') no coincide con el esperado (' . number_format($esperado, 2) . ').';
        }

        // ── Resumen ─────────────────────────────────────────────────────
        $this->info($aplicar ? 'APLICAR carga limpia' : 'SIMULACIÓN de la carga limpia (no se guarda nada)');
        $this->line('Excel: ' . count($filas) . ' filas · ' . count($plan) . ' válidas.');
        $this->table(['Concepto', 'Cantidad'], [
            ['Productos a borrar (bajas existentes en la base)', count($bajasExistentes) . ' de ' . count(self::BAJAS)],
            ['Productos a renumerar (código anterior → nuevo)', count($renombres)],
            ['Productos existentes a actualizar (incluye renumerados)', $actualizar],
            ['Productos / servicios nuevos a crear', $crear],
            ['Marcas nuevas a crear', count($marcasNuevas)],
            ['Saldos de stock a eliminar (piloto)', DB::table('inventario_saldos')->count()],
            ['Movimientos de kárdex a eliminar (piloto)', DB::table('inventario_movimientos')->count()],
            ['Productos con saldo inicial', $sinStockSaldo],
            ['Unidades del saldo inicial', number_format($totalUnidades, 0)],
            ['Valor del saldo inicial (asiento de apertura)', '$' . number_format($totalValor, 2)],
        ]);
        ksort($detalleCuentas);
        $this->table(['PC-CAT', 'Productos', 'Unidades', 'Valor'], array_map(
            fn ($k, $v) => [$k, $v['productos'], number_format($v['unidades'], 0), number_format($v['valor'], 2)],
            array_keys($detalleCuentas), $detalleCuentas
        ));

        $csv = $this->escribirCsv($plan, $bajasExistentes, $aplicar);
        $this->line("Detalle: {$csv}");

        foreach ($avisos as $a) {
            $this->warn('AVISO: ' . $a);
        }
        if ($errores) {
            foreach (array_slice($errores, 0, 40) as $e) {
                $this->error($e);
            }
            if (count($errores) > 40) {
                $this->error('… y ' . (count($errores) - 40) . ' error(es) más.');
            }
            $this->error('NO SE PUEDE CARGAR: corrija los errores de arriba.');

            return self::FAILURE;
        }

        if (!$aplicar) {
            $this->info('Simulación correcta. Ejecute con --aplicar para guardar.');

            return self::SUCCESS;
        }

        // ── Respaldo ────────────────────────────────────────────────────
        $ts = now()->format('Ymd_His');
        Storage::disk('local')->put("carga-limpia/respaldo_{$ts}.json", json_encode([
            'productos' => DB::table('productos')->get(),
            'inventario_saldos' => DB::table('inventario_saldos')->get(),
            'inventario_movimientos' => DB::table('inventario_movimientos')->get(),
            'listas_precio' => DB::table('listas_precio')->whereIn('producto_id', DB::table('productos')->whereIn('codigo', self::BAJAS)->pluck('id'))->get(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->line("Respaldo: storage/app/private/carga-limpia/respaldo_{$ts}.json");

        // ── Aplicación ──────────────────────────────────────────────────
        $moda = DB::table('productos')->select('descuento_maximo', DB::raw('count(*) n'))->groupBy('descuento_maximo')->orderByDesc('n')->value('descuento_maximo') ?? 0;

        DB::transaction(function () use (
            $plan, $bajasExistentes, $renombres, $marcasNuevas, &$marcas, $empresaId, $bodegaId, $fecha, $capital,
            $porInventario, $totalValor, $asientos, $inventario, $moda, $existentes
        ) {
            // 1. Bajas
            $idsBaja = DB::table('productos')->whereIn('codigo', $bajasExistentes)->pluck('id')->all();
            if ($idsBaja) {
                foreach (['inventario_saldos', 'inventario_movimientos', 'listas_precio', 'etiquetas_productos', 'producto_series'] as $t) {
                    DB::table($t)->whereIn('producto_id', $idsBaja)->delete();
                }
                DB::table('productos')->whereIn('id', $idsBaja)->delete();
            }

            // 2. Stock y kárdex del piloto en cero
            DB::table('inventario_saldos')->delete();
            DB::table('inventario_movimientos')->delete();

            // 3. Marcas nuevas
            foreach ($marcasNuevas as $norm => $nombre) {
                $marcas[$norm] = (int) DB::table('marcas')->insertGetId(['nombre' => $nombre, 'estado' => true]);
            }

            // 4. Productos
            foreach ($plan as $codigo => $p) {
                $marcaId = $p['marca'] !== '' ? ($marcas[$this->norm($p['marca'])] ?? null) : null;
                $descripcion = $p['descripcion'];
                if ($p['accion'] === 'renumerar' && $p['anterior'] !== '' && !str_contains($descripcion, $p['anterior'])) {
                    $descripcion = trim($descripcion . ' (COD. ANT. ' . $p['anterior'] . ')');
                }
                $campos = [
                    'codigo' => $codigo, 'nombre' => $p['producto'], 'descripcion' => $descripcion,
                    'ref_importacion' => $p['ref'] ?: null, 'unidad' => $p['unidad'] ?: 'UNIDAD',
                    'marca_id' => $marcaId, 'categoria_id' => $p['categoria_id'],
                    'tipo' => $p['servicio'] ? 'servicio' : 'producto', 'requiere_serie' => $p['req_serie'],
                    'estado' => true, 'updated_at' => now(),
                ];
                if ($p['costo'] !== null) {
                    $campos['costo'] = $p['costo'];
                }
                if ($p['pvp'] !== null) {
                    $campos['pvp'] = $p['pvp'];
                }
                if ($p['pvd'] !== null) {
                    $campos['pvd'] = $p['pvd'];
                }

                if ($p['actual_id']) {
                    DB::table('productos')->where('id', $p['actual_id'])->update($campos);
                } else {
                    DB::table('productos')->insert($campos + [
                        'empresa_id' => $empresaId, 'costo' => $p['costo'] ?? 0, 'pvp' => $p['pvp'] ?? 0, 'pvd' => $p['pvd'] ?? 0,
                        'descuento_maximo' => $moda, 'porcentaje_iva' => self::IVA_NUEVOS, 'created_at' => now(),
                    ]);
                }
            }

            // 5. Asiento de apertura (primero, para que el kárdex referencie su id)
            $partidas = [];
            ksort($porInventario);
            foreach ($porInventario as $cuentaId => $valor) {
                $partidas[] = ['cuenta_id' => (int) $cuentaId, 'debe' => round($valor, 2), 'haber' => 0, 'descripcion' => 'Saldo inicial de inventario'];
            }
            $totalDebe = round(array_sum(array_column($partidas, 'debe')), 2);
            $partidas[] = ['cuenta_id' => $capital->id, 'debe' => 0, 'haber' => $totalDebe, 'descripcion' => 'Capital: saldo inicial de inventario'];

            $asiento = $asientos->crear(
                $empresaId, 'Apertura de inventario al ' . \Carbon\Carbon::parse($fecha)->format('d/m/Y') . ' (carga inicial)',
                $partidas, self::ASIENTO_TIPO, null, 'SALDO-INICIAL-' . substr($fecha, 0, 4), true, $fecha
            );

            // 6. Saldo inicial por producto
            $ids = DB::table('productos')->pluck('id', 'codigo');
            foreach ($plan as $codigo => $p) {
                if ($p['cantidad'] > 0) {
                    $inventario->ingresarStock(
                        (int) $ids[$codigo], $bodegaId, (float) $p['cantidad'], (float) $p['costo'],
                        self::DOC_TIPO, (int) $asiento->id, $asiento->numero, 'Saldo inicial al ' . $fecha
                    );
                }
            }
        });

        // 7. Categoría de paso vacía
        $porReclasificar = DB::table('categorias_producto')->where('nombre', 'ACCESORIOS (por reclasificar)')->first();
        if ($porReclasificar && !DB::table('productos')->where('categoria_id', $porReclasificar->id)->exists()) {
            DB::table('categorias_producto')->where('id', $porReclasificar->id)->update(['estado' => false]);
            $this->line('Categoría «ACCESORIOS (por reclasificar)» desactivada (quedó vacía).');
        }

        $this->info('APLICADO. Valor cargado: $' . number_format($totalValor, 2) . ' en ' . $sinStockSaldo . ' producto(s).');

        return self::SUCCESS;
    }

    // ───────────────────────── utilidades ─────────────────────────

    /** @param list<string> $errores */
    private function leerExcel(string $archivo, array &$errores): array
    {
        $lector = IOFactory::createReaderForFile($archivo);
        $lector->setReadDataOnly(true);
        $filas = $lector->load($archivo)->getSheet(0)->toArray(null, true, false, false);

        $esperada = ['codigo', 'producto', 'marca', 'descripcion', 'ref. importacion', 'unidad', 'categoria', 'subcategoria', 'tipo', 'codigo anterior', 'cantidad fisica 31/10'];
        $cab = array_map(fn ($c) => $this->norm((string) $c), array_slice($filas[0] ?? [], 0, 11));
        if ($cab !== $esperada) {
            $errores[] = 'El Excel no tiene el formato esperado (Código, Producto, Marca, Descripción, Ref. importación, Unidad, Categoría, Subcategoría, Tipo, Código anterior, Cantidad física 31/10…).';

            return [];
        }

        $res = [];
        $vistos = [];
        foreach (array_slice($filas, 1) as $i => $r) {
            $codigo = strtoupper(trim((string) ($r[0] ?? '')));
            if ($codigo === '') {
                continue;
            }
            $n = $i + 2;
            if (!preg_match(self::CODIGO_VALIDO, $codigo)) {
                $errores[] = "Fila {$n}: código inválido «{$codigo}».";
                continue;
            }
            if (isset($vistos[$codigo])) {
                $errores[] = "Fila {$n}: código repetido {$codigo}.";
                continue;
            }
            $vistos[$codigo] = true;
            $tipo = $this->norm((string) ($r[8] ?? ''));
            $num = fn ($v) => ($v === null || $v === '') ? null : (float) $v;
            $res[] = [
                'codigo'       => $codigo,
                'producto'     => trim(preg_replace('/\s+/', ' ', (string) $r[1])),
                'marca'        => trim((string) ($r[2] ?? '')),
                'descripcion'  => trim((string) ($r[3] ?? '')),
                'ref'          => trim((string) ($r[4] ?? '')),
                'unidad'       => strtoupper(trim((string) ($r[5] ?? ''))),
                'categoria'    => trim((string) ($r[6] ?? '')),
                'subcategoria' => trim((string) ($r[7] ?? '')),
                'servicio'     => str_starts_with($tipo, 'servicio'),
                'anterior'     => strtoupper(trim((string) ($r[9] ?? ''))),
                'cantidad'     => (float) ($num($r[10] ?? null) ?? 0),
                'pvp'          => $num($r[13] ?? null),
                'pvd'          => $num($r[14] ?? null),
                'costo'        => $num($r[15] ?? null) ?? 0.0,
            ];
        }

        return $res;
    }

    /** @return array{raiz: array<string,int>, hijos: array<int, array<string,int>>} */
    private function mapaCategorias(): array
    {
        $raiz = [];
        $hijos = [];
        foreach (DB::table('categorias_producto')->get() as $c) {
            if ($c->categoria_padre_id) {
                $hijos[(int) $c->categoria_padre_id][$this->norm($c->nombre)] = (int) $c->id;
            } else {
                $raiz[$this->norm($c->nombre)] = (int) $c->id;
            }
        }

        return ['raiz' => $raiz, 'hijos' => $hijos];
    }

    private function resolverCategoria(string $categoria, string $sub, array $cat): ?int
    {
        $c = $this->norm($categoria);
        $c = self::ALIAS_CATEGORIA[$c] ?? $c;
        $raizId = $cat['raiz'][$c] ?? null;
        if (!$raizId) {
            return null;
        }
        $s = $this->norm($sub);
        if ($s === '' || in_array($s, self::SIN_SUBCATEGORIA, true)) {
            return $raizId;
        }
        if (isset($cat['hijos'][$raizId][$s])) {
            return $cat['hijos'][$raizId][$s];
        }
        $s = trim(preg_replace('/\s*\([^)]*\)\s*$/u', '', $s)); // «Servicio técnico (4.01.08)» → «servicio tecnico»

        return $cat['hijos'][$raizId][$s] ?? null;
    }

    private function norm(string $t): string
    {
        $t = mb_strtolower(trim($t));
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return trim(preg_replace('/\s+/', ' ', $t));
    }

    private function escribirCsv(array $plan, array $bajas, bool $aplicar): string
    {
        $ruta = 'carga-limpia/' . ($aplicar ? 'aplicado' : 'simulacion') . '_' . now()->format('Ymd_His') . '.csv';
        $f = fopen('php://temp', 'w+');
        fputcsv($f, ['accion', 'codigo', 'codigo_anterior', 'producto', 'tipo', 'categoria_id', 'cantidad', 'costo', 'valor'], ';');
        foreach ($bajas as $c) {
            fputcsv($f, ['borrar', $c, '', '', '', '', '', '', ''], ';');
        }
        foreach ($plan as $c => $p) {
            fputcsv($f, [$p['accion'], $c, $p['anterior'], $p['producto'], $p['servicio'] ? 'servicio' : 'producto',
                $p['categoria_id'], $p['cantidad'], $p['costo'], $p['valor']], ';');
        }
        rewind($f);
        Storage::disk('local')->put($ruta, "\xEF\xBB\xBF" . stream_get_contents($f));
        fclose($f);

        return 'storage/app/private/' . $ruta;
    }
}
