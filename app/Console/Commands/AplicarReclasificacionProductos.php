<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Aplica la reclasificación de productos a las subcategorías oficiales (02-02 §2.3). Lee el CSV que genera
 * `inventario:sugerir-reclasificacion`, que el contador o bodega revisan: en la columna «confirmar» escriben SI para
 * los productos que aprueban (y pueden corregir categoría/subcategoría sugeridas). Solo cambia `categoria_id`.
 *
 * Con --aceptar-sugerencias se dan por confirmadas todas las filas «con sugerencia» (para una primera pasada y revisar después).
 * Simula por defecto; con --aplicar guarda con respaldo. Es la condición para poder facturar y comprar en modo estricto:
 * sin subcategoría con cuentas, el producto no se contabiliza.
 */
class AplicarReclasificacionProductos extends Command
{
    protected $signature = 'inventario:aplicar-reclasificacion
                            {csv? : CSV o Excel revisado (por defecto docs/Oct2026/reclasificacion_productos.csv). El Excel es el de «Productos por clasificar» (hoja «Por clasificar», columnas G y H)}
                            {--aceptar-sugerencias : Da por confirmadas todas las filas «con sugerencia»}
                            {--aplicar : Guarda los cambios (sin esto solo simula)}';
    protected $description = 'Aplica la reclasificación de productos desde el CSV revisado (simula por defecto)';

    public function handle(): int
    {
        $ruta = $this->argument('csv') ?: base_path('docs/Oct2026/reclasificacion_productos.csv');
        if (!is_file($ruta)) {
            $this->error("No existe el CSV: {$ruta}");

            return self::FAILURE;
        }
        $aceptarTodas = (bool) $this->option('aceptar-sugerencias');

        $cats = DB::table('categorias_producto')->get(['id', 'nombre', 'categoria_padre_id']);
        $raices = $cats->whereNull('categoria_padre_id')->keyBy(fn ($c) => mb_strtolower(trim($c->nombre)));
        $conSubs = $cats->whereNotNull('categoria_padre_id')->pluck('categoria_padre_id')->unique()->all();

        $filas = str_ends_with(strtolower($ruta), '.xlsx')
            ? $this->leerExcel($ruta, $cats)
            : $this->leerCsv($ruta, $aceptarTodas);
        $cambios = [];
        $omitidas = ['sin confirmar' => 0, 'sin sugerencia' => 0, 'falta subcategoría' => 0, 'categoría inexistente' => 0, 'sin cambio' => 0];
        $detalleOmitidas = [];

        foreach ($filas as $r) {
            if (!$r['confirmada']) {
                $omitidas['sin confirmar']++;
                continue;
            }
            $catNombre = trim($r['categoria']);
            $subNombre = trim($r['subcategoria']);
            if ($catNombre === '' && $subNombre === '') {
                $omitidas['sin sugerencia']++;
                continue;
            }
            if ($catNombre === '' && $subNombre !== '') {
                // Solo eligieron la subcategoría: su categoría es la del padre de esa subcategoría
                $sub0 = $cats->first(fn ($c) => $c->categoria_padre_id && mb_strtolower(trim($c->nombre)) === mb_strtolower($subNombre));
                $catNombre = $sub0 ? (string) $cats->firstWhere('id', $sub0->categoria_padre_id)?->nombre : '';
            }
            $raiz = $raices[mb_strtolower($catNombre)] ?? null;
            if (!$raiz) {
                $omitidas['categoría inexistente']++;
                $detalleOmitidas[] = $catNombre === '' && $subNombre !== ''
                    ? "{$r['codigo']}: la subcategoría «{$subNombre}» no existe"
                    : "{$r['codigo']}: categoría «{$catNombre}» no existe";
                continue;
            }
            if ($subNombre !== '') {
                $sub = $cats->first(fn ($c) => (int) $c->categoria_padre_id === (int) $raiz->id && mb_strtolower(trim($c->nombre)) === mb_strtolower($subNombre));
                if (!$sub) {
                    $omitidas['categoría inexistente']++;
                    $detalleOmitidas[] = "{$r['codigo']}: subcategoría «{$subNombre}» no existe en «{$catNombre}»";
                    continue;
                }
                $destino = (int) $sub->id;
            } elseif (in_array((int) $raiz->id, array_map('intval', $conSubs), true)) {
                $omitidas['falta subcategoría']++;
                $detalleOmitidas[] = "{$r['codigo']}: «{$catNombre}» exige elegir subcategoría";
                continue;
            } else {
                $destino = (int) $raiz->id;
            }
            $r['producto_id'] = $r['producto_id'] ?: (int) DB::table('productos')->where('codigo', $r['codigo'])->value('id');
            if (!$r['producto_id']) {
                $omitidas['categoría inexistente']++;
                $detalleOmitidas[] = "{$r['codigo']}: el producto no existe";
                continue;
            }
            $actual = (int) DB::table('productos')->where('id', (int) $r['producto_id'])->value('categoria_id');
            if ($actual === $destino) {
                $omitidas['sin cambio']++;
                continue;
            }
            $cambios[] = ['id' => (int) $r['producto_id'], 'codigo' => $r['codigo'], 'antes' => $actual, 'despues' => $destino];
        }

        $this->info('Productos a reclasificar: ' . count($cambios));
        foreach ($omitidas as $k => $v) {
            if ($v) {
                $this->line("  omitidos ({$k}): {$v}");
            }
        }
        foreach (array_slice($detalleOmitidas, 0, 8) as $d) {
            $this->warn('  ' . $d);
        }

        if (!$this->option('aplicar')) {
            $this->warn('SIMULACIÓN: no se cambió nada. Para aplicar use --aplicar.');

            return self::SUCCESS;
        }
        if (!$cambios) {
            $this->info('Nada que aplicar.');

            return self::SUCCESS;
        }

        $respaldo = 'respaldos/reclasificacion_antes_' . now()->format('Ymd_His') . '.json';
        Storage::disk('local')->put($respaldo, json_encode($cambios, JSON_PRETTY_PRINT));
        $this->line('Respaldo: ' . storage_path('app/private/' . $respaldo));
        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $c) {
                DB::table('productos')->where('id', $c['id'])->update(['categoria_id' => $c['despues'], 'updated_at' => now()]);
            }
        });
        $this->info('APLICADO: ' . count($cambios) . ' producto(s) reclasificado(s).');

        return self::SUCCESS;
    }

    /** @return array<int,array{codigo:string,producto_id:int,categoria:string,subcategoria:string,confirmada:bool}> */
    private function leerCsv(string $ruta, bool $aceptarTodas): array
    {
        $fh = fopen($ruta, 'r');
        $cab = fgetcsv($fh, 0, ';');
        $cab[0] = ltrim((string) $cab[0], "\xEF\xBB\xBF");
        $filas = [];
        while (($f = fgetcsv($fh, 0, ';')) !== false) {
            $r = array_combine($cab, array_pad($f, count($cab), ''));
            $filas[] = [
                'codigo'       => (string) ($r['codigo'] ?? ''),
                'producto_id'  => (int) ($r['producto_id'] ?? 0),
                'categoria'    => (string) ($r['sugerencia_categoria'] ?? ''),
                'subcategoria' => (string) ($r['sugerencia_subcategoria'] ?? ''),
                'confirmada'   => in_array(strtoupper(trim($r['confirmar'] ?? '')), ['SI', 'SÍ', 'S', 'X', '1'], true)
                    || ($aceptarTodas && ($r['estado'] ?? '') === 'con sugerencia'),
            ];
        }
        fclose($fh);

        return $filas;
    }

    /** Excel «Productos por clasificar»: A=código, E=categoría actual, G=categoría correcta, H=subcategoría correcta. */
    private function leerExcel(string $ruta, $cats): array
    {
        $lector = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($ruta);
        $lector->setReadDataOnly(true);
        $hoja = $lector->load($ruta)->getSheetByName('Por clasificar') ?? $lector->load($ruta)->getSheet(1);
        $filas = [];
        for ($r = 2, $max = $hoja->getHighestDataRow(); $r <= $max; $r++) {
            $codigo = trim((string) $hoja->getCell("A{$r}")->getValue());
            if ($codigo === '') {
                continue;
            }
            $actual = trim((string) $hoja->getCell("E{$r}")->getValue());
            $catElegida = trim((string) $hoja->getCell("G{$r}")->getValue());
            $subElegida = trim((string) $hoja->getCell("H{$r}")->getValue());
            // Si no cambian la categoría, vale la actual (salvo «ACCESORIOS», que ya no es categoría)
            $categoria = $catElegida !== '' ? $catElegida : (str_starts_with(strtoupper($actual), 'ACCESORIOS') ? '' : $actual);
            $filas[] = [
                'codigo' => $codigo, 'producto_id' => 0, 'categoria' => $categoria, 'subcategoria' => $subElegida,
                'confirmada' => $catElegida !== '' || $subElegida !== '',
            ];
        }

        return $filas;
    }
}
