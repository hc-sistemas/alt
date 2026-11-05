<?php

namespace App\Console\Commands;

use App\Services\ClasificadorProductos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Propone la reclasificación de productos a las categorías y subcategorías oficiales (02-02 §2.3,
 * PC-CAT-R02 y R05) SIN modificar la tabla `productos`: solo lee y genera un CSV para que el
 * contador y bodega lo revisen. Aplicar la reclasificación es una decisión aparte.
 */
class SugerirReclasificacionProductos extends Command
{
    protected $signature = 'inventario:sugerir-reclasificacion {--salida= : Ruta del CSV (por defecto docs/Oct2026/reclasificacion_productos.csv)}';
    protected $description = 'Genera un CSV con la categoría/subcategoría sugerida de cada producto (solo lectura)';

    public function handle(): int
    {
        $salida = $this->option('salida') ?: base_path('docs/Oct2026/reclasificacion_productos.csv');
        $cats   = DB::table('categorias_producto')->pluck('nombre', 'id');
        $padres = DB::table('categorias_producto')->pluck('categoria_padre_id', 'id');

        // Productos que necesitan decisión: los de «Accesorios» (ya no es categoría) y los que están en una
        // categoría con subcategorías sin haber elegido una.
        $conSubs = DB::table('categorias_producto')->whereNotNull('categoria_padre_id')->distinct()->pluck('categoria_padre_id')->all();
        $accesorios = DB::table('categorias_producto')->where('nombre', 'like', 'ACCESORIOS%')->pluck('id')->all();

        $productos = DB::table('productos')
            ->whereIn('categoria_id', array_merge($conSubs, $accesorios))
            ->orderBy('categoria_id')->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'categoria_id']);

        $fh = fopen($salida, 'w');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM para que Excel lea las tildes
        fputcsv($fh, ['producto_id', 'codigo', 'nombre', 'categoria_actual', 'sugerencia_categoria', 'sugerencia_subcategoria', 'estado', 'confirmar'], ';');

        $resumen = ['con sugerencia' => 0, 'categoría sin subcategoría' => 0, 'sin sugerencia' => 0];
        foreach ($productos as $p) {
            [$cat, $sub] = ClasificadorProductos::porNombre($p->nombre);

            // Si el producto ya está en una categoría con subcategorías, solo sirve una sugerencia de esa misma categoría.
            $actual = $cats[$p->categoria_id] ?? '';
            if ($cat && in_array($p->categoria_id, $conSubs, true) && $cat !== $actual) {
                $cat = $sub = null;
            }

            $tieneSubs = $cat && DB::table('categorias_producto')->where('nombre', $cat)->whereNull('categoria_padre_id')
                ->whereIn('id', $conSubs)->exists();
            if (!$cat) {
                $estado = 'sin sugerencia';
            } elseif ($tieneSubs && !$sub) {
                $estado = 'categoría sin subcategoría';
            } else {
                $estado = 'con sugerencia';
            }
            $resumen[$estado]++;

            fputcsv($fh, [$p->id, $p->codigo, $p->nombre, $actual, $cat, $sub, $estado, ''], ';');
        }
        fclose($fh);

        $this->info("Productos a reclasificar: {$productos->count()}");
        foreach ($resumen as $k => $v) {
            $this->line("  {$k}: {$v}");
        }
        $this->info("CSV: {$salida}  (la tabla productos NO se modificó)");

        return self::SUCCESS;
    }
}
