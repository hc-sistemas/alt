<?php

namespace App\Console\Commands;

use App\Services\ClasificadorProductos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Carga el inventario actualizado desde Excel (02-01 §2.5; solicitado por el cliente): crea los productos
 * nuevos, completa el catálogo de marcas y actualiza nombre, descripción, referencia, marca y precios de los
 * existentes. NO toca existencias (esa decisión quedó aparte: asiento de apertura DEC-011 y bodega de cada
 * columna) ni cambia la categoría de los productos que ya existen.
 *
 * Por defecto SIMULA: muestra todo lo que cambiaría y escribe un CSV con el detalle. Con --aplicar guarda,
 * dentro de una transacción y con respaldo previo de lo que se modifica. Es idempotente: una segunda corrida
 * no encuentra nada que cambiar.
 *
 * Reglas acordadas: un precio vacío en el Excel NO borra el precio actual; una fila con código inválido
 * (formato distinto de AAA.000000) se omite y se informa.
 */
class CargarInventarioExcel extends Command
{
    protected $signature = 'inventario:cargar-excel
                            {archivo? : Ruta del Excel (por defecto docs/INVENTARIO ALTAMIRA JUNTO FINAL.xlsx)}
                            {--aplicar : Guarda los cambios (sin esta opción solo simula)}
                            {--empresa=1 : Empresa dueña de los productos nuevos}';
    protected $description = 'Carga el inventario desde Excel: altas, marcas, nombres y precios (simula por defecto)';

    private const CODIGO_VALIDO = '/^[A-Z]{3}\.\d{6}$/';
    private const IVA_NUEVOS = 15;

    public function handle(): int
    {
        ini_set('memory_limit', '1024M');
        $archivo = $this->argument('archivo') ?: base_path('docs/INVENTARIO ALTAMIRA JUNTO FINAL.xlsx');
        if (!is_file($archivo)) {
            $this->error("No existe el archivo: {$archivo}");

            return self::FAILURE;
        }
        $aplicar = (bool) $this->option('aplicar');
        $empresaId = (int) $this->option('empresa');

        [$filas, $omitidas] = $this->leerExcel($archivo);
        $this->info('Excel: ' . count($filas) . ' productos válidos' . ($omitidas ? ', ' . count($omitidas) . ' fila(s) omitida(s)' : '') . '.');

        $existentes = DB::table('productos')->get()->keyBy('codigo');
        $marcas = $this->mapaMarcas();

        $cambios = [];      // [codigo, campo, antes, despues]
        $updates = [];      // codigo => [campo => valor]
        $antes = [];        // codigo => [campo => valor anterior] (respaldo)
        $nuevos = [];
        $marcasNuevas = []; // normalizado => nombre
        $advertencias = [];

        foreach ($filas as $f) {
            $marcaNorm = $this->normMarca($f['marca']);
            if ($marcaNorm !== '' && !isset($marcas[$marcaNorm]) && !isset($marcasNuevas[$marcaNorm])) {
                $marcasNuevas[$marcaNorm] = mb_strtoupper(trim($f['marca']));
            }

            $p = $existentes->get($f['codigo']);
            if (!$p) {
                $nuevos[] = $f;
                continue;
            }

            $cand = [];
            $this->cmpTexto($cand, 'nombre', $p->nombre, $f['producto']);
            $this->cmpTexto($cand, 'descripcion', $p->descripcion, $f['descripcion'], true);
            $this->cmpTexto($cand, 'ref_importacion', $p->ref_importacion, $f['ref'], true);
            $this->cmpTexto($cand, 'unidad', $p->unidad, $f['unidad']);
            if ($f['pvp'] !== null && abs($f['pvp'] - (float) $p->pvp) > 0.005) {
                $cand['pvp'] = round($f['pvp'], 4);
            }
            if ($f['pvd'] !== null && abs($f['pvd'] - (float) $p->pvd) > 0.005) {
                $cand['pvd'] = round($f['pvd'], 4);
            }
            // La marca se resuelve al aplicar (puede ser una marca nueva): se marca el cambio si no coincide
            $marcaActual = $p->marca_id ? ($marcas['_id'][$p->marca_id] ?? '') : '';
            if ($marcaNorm !== '' && $marcaNorm !== $marcaActual) {
                $cand['_marca'] = $marcaNorm;
            }

            if ($cand) {
                $updates[$f['codigo']] = $cand;
                foreach ($cand as $campo => $nuevo) {
                    $viejo = $campo === '_marca' ? ($marcas['_nombre'][$p->marca_id] ?? '(sin marca)') : $p->{$campo};
                    $cambios[] = [$f['codigo'], $campo === '_marca' ? 'marca' : $campo, (string) $viejo, $campo === '_marca' ? ($marcasNuevas[$nuevo] ?? $marcas['_nombreNorm'][$nuevo] ?? $nuevo) : (string) $nuevo];
                    $antes[$f['codigo']][$campo === '_marca' ? 'marca_id' : $campo] = $campo === '_marca' ? $p->marca_id : $p->{$campo};
                }
            }
        }

        // Productos nuevos: categoría por prefijo del código y nombre
        $altas = [];
        foreach ($nuevos as $f) {
            [$catId, $adv] = ClasificadorProductos::categoriaPara($f['codigo'], $f['producto']);
            $altas[] = $f + ['categoria_id' => $catId];
            if ($adv) {
                $advertencias[] = "{$f['codigo']} {$f['producto']}: {$adv}";
            }
        }

        $this->resumen($updates, $cambios, $altas, $marcasNuevas, $omitidas, $advertencias, $existentes->count(), count($filas));
        $csv = $this->escribirCsv($cambios, $altas);
        $this->line("Detalle: {$csv}");

        if (!$aplicar) {
            $this->warn('SIMULACIÓN: no se guardó nada. Para aplicar use --aplicar.');

            return self::SUCCESS;
        }

        return $this->aplicar($updates, $antes, $altas, $marcasNuevas, $empresaId);
    }

    /** @return array{0:array<int,array>,1:array<int,string>} */
    private function leerExcel(string $archivo): array
    {
        $lector = IOFactory::createReaderForFile($archivo);
        $lector->setReadDataOnly(true);
        $hoja = $lector->load($archivo)->getSheet(0);

        $cab = array_map(fn ($c) => mb_strtoupper(trim((string) $hoja->getCell($c . '1')->getValue())), ['B', 'C', 'D', 'H', 'I']);
        if ($cab[0] !== 'CODIGO' || $cab[1] !== 'PRODUCTO' || $cab[2] !== 'MARCA' || $cab[3] !== 'PVP' || $cab[4] !== 'PVD') {
            throw new \RuntimeException('El Excel no tiene el formato esperado (CODIGO, PRODUCTO, MARCA ... PVP, PVD).');
        }

        $filas = [];
        $omitidas = [];
        $vistos = [];
        $max = $hoja->getHighestDataRow();
        for ($r = 2; $r <= $max; $r++) {
            $codigo = strtoupper(trim((string) $hoja->getCell("B{$r}")->getValue()));
            if ($codigo === '') {
                continue;
            }
            $producto = trim(preg_replace('/\s+/', ' ', (string) $hoja->getCell("C{$r}")->getValue()));
            if (!preg_match(self::CODIGO_VALIDO, $codigo)) {
                $omitidas[] = "fila {$r}: código inválido «{$codigo}» ({$producto})";
                continue;
            }
            if (isset($vistos[$codigo])) {
                $omitidas[] = "fila {$r}: código repetido {$codigo}";
                continue;
            }
            $vistos[$codigo] = true;
            $num = function (string $col) use ($hoja, $r) {
                $v = $hoja->getCell("{$col}{$r}")->getValue();

                return is_numeric($v) ? (float) $v : null;
            };
            $txt = fn (string $col) => ($t = trim(preg_replace('/\s+/', ' ', (string) $hoja->getCell("{$col}{$r}")->getValue()))) === '' ? null : $t;
            $filas[] = [
                'fila' => $r, 'codigo' => $codigo, 'producto' => $producto, 'marca' => (string) $txt('D'),
                'descripcion' => $txt('E'), 'ref' => $txt('F'), 'unidad' => $txt('G') ?? 'UNIDAD',
                'pvp' => $num('H'), 'pvd' => $num('I'),
            ];
        }

        return [$filas, $omitidas];
    }

    /** Marcas existentes: _id[id] => normalizado, _nombre[id] => nombre, y normalizado => id. */
    private function mapaMarcas(): array
    {
        $m = ['_id' => [], '_nombre' => [], '_nombreNorm' => []];
        foreach (DB::table('marcas')->get(['id', 'nombre']) as $r) {
            $n = $this->normMarca($r->nombre);
            $m[$n] = (int) $r->id;
            $m['_id'][(int) $r->id] = $n;
            $m['_nombre'][(int) $r->id] = $r->nombre;
            $m['_nombreNorm'][$n] = $r->nombre;
        }

        return $m;
    }

    /** «EA LIGHT» y «EALIGHT» son la misma marca: se compara sin espacios ni signos. */
    private function normMarca(?string $m): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper(ClasificadorProductos::normalizar((string) $m)));
    }

    private function cmpTexto(array &$cand, string $campo, ?string $actual, ?string $nuevo, bool $permiteVacioNuevo = false): void
    {
        if ($nuevo === null || $nuevo === '') {
            return; // un dato vacío en el Excel no borra el actual
        }
        $a = mb_strtoupper(trim(preg_replace('/\s+/', ' ', (string) $actual)));
        if ($a !== mb_strtoupper($nuevo)) {
            $cand[$campo] = $nuevo;
        }
    }

    private function resumen(array $updates, array $cambios, array $altas, array $marcasNuevas, array $omitidas, array $advertencias, int $enBd, int $enExcel): void
    {
        $porCampo = [];
        foreach ($cambios as [, $campo]) {
            $porCampo[$campo] = ($porCampo[$campo] ?? 0) + 1;
        }
        $this->newLine();
        $this->line("Productos en la base: {$enBd} | en el Excel: {$enExcel}");
        $this->line('Productos NUEVOS a crear: ' . count($altas));
        $this->line('Productos existentes con cambios: ' . count($updates) . ' (' . (collect($porCampo)->map(fn ($v, $k) => "{$k}: {$v}")->implode(', ') ?: 'ninguno') . ')');
        $this->line('Marcas nuevas a crear: ' . count($marcasNuevas));
        foreach ($omitidas as $o) {
            $this->warn('  OMITIDA ' . $o);
        }
        foreach ($advertencias as $a) {
            $this->warn('  ATENCIÓN ' . $a);
        }
        foreach (array_slice($altas, 0, 5) as $a) {
            $this->line("  nuevo: {$a['codigo']} {$a['producto']} | {$a['marca']} | PVP " . ($a['pvp'] ?? '—'));
        }
    }

    private function escribirCsv(array $cambios, array $altas): string
    {
        $ruta = 'inventario_carga_' . now()->format('Ymd_His') . '.csv';
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        fputcsv($fh, ['tipo', 'codigo', 'campo', 'antes', 'despues'], ';');
        foreach ($altas as $a) {
            fputcsv($fh, ['ALTA', $a['codigo'], 'producto', '', $a['producto'] . ' | ' . $a['marca']], ';');
        }
        foreach ($cambios as [$c, $campo, $a, $d]) {
            fputcsv($fh, ['CAMBIO', $c, $campo, $a, $d], ';');
        }
        rewind($fh);
        Storage::disk('local')->put("reportes/{$ruta}", stream_get_contents($fh));
        fclose($fh);

        return storage_path("app/private/reportes/{$ruta}");
    }

    private function aplicar(array $updates, array $antes, array $altas, array $marcasNuevas, int $empresaId): int
    {
        if (!$updates && !$altas && !$marcasNuevas) {
            $this->info('Nada que aplicar: la base ya coincide con el Excel.');

            return self::SUCCESS;
        }

        // Respaldo de lo que se va a modificar
        $respaldo = 'respaldos/inventario_antes_' . now()->format('Ymd_His') . '.json';
        Storage::disk('local')->put($respaldo, json_encode(['productos' => $antes, 'altas' => array_column($altas, 'codigo'), 'marcas_nuevas' => array_values($marcasNuevas)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $this->line('Respaldo: ' . storage_path('app/private/' . $respaldo));

        DB::transaction(function () use ($updates, $altas, $marcasNuevas, $empresaId) {
            $marcas = $this->mapaMarcas();
            foreach ($marcasNuevas as $norm => $nombre) {
                $marcas[$norm] = (int) DB::table('marcas')->insertGetId(['nombre' => $nombre, 'estado' => true]);
            }

            $moda = DB::table('productos')->select('descuento_maximo', DB::raw('count(*) n'))->groupBy('descuento_maximo')->orderByDesc('n')->value('descuento_maximo') ?? 0;
            $reqSerie = DB::table('categorias_producto')->pluck('requiere_serie', 'id');

            foreach ($updates as $codigo => $cand) {
                $set = $cand;
                if (isset($set['_marca'])) {
                    $set['marca_id'] = $marcas[$set['_marca']];
                    unset($set['_marca']);
                }
                $set['updated_at'] = now();
                DB::table('productos')->where('codigo', $codigo)->update($set);
            }

            foreach ($altas as $a) {
                DB::table('productos')->insert([
                    'empresa_id' => $empresaId, 'codigo' => $a['codigo'], 'nombre' => $a['producto'],
                    'descripcion' => $a['descripcion'], 'ref_importacion' => $a['ref'], 'unidad' => $a['unidad'],
                    'marca_id' => ($n = $this->normMarca($a['marca'])) !== '' ? ($marcas[$n] ?? null) : null,
                    'categoria_id' => $a['categoria_id'], 'tipo' => 'producto',
                    'requiere_serie' => (bool) ($reqSerie[$a['categoria_id']] ?? false),
                    'costo' => 0, 'pvp' => $a['pvp'] ?? 0, 'pvd' => $a['pvd'] ?? 0, 'descuento_maximo' => $moda,
                    'porcentaje_iva' => self::IVA_NUEVOS, 'estado' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $this->info('APLICADO: ' . count($altas) . ' producto(s) nuevo(s), ' . count($updates) . ' actualizado(s), ' . count($marcasNuevas) . ' marca(s) creada(s).');

        return self::SUCCESS;
    }
}
