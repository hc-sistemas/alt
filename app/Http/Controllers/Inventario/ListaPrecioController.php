<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\CategoriaProducto;
use App\Models\ListaPrecio;
use App\Models\Empresa;
use App\Models\Marca;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListaPrecioController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $query = $this->consulta($request, $empresaId);

        $bodegas = $this->bodegasEmpresa($empresaId);

        if ($request->boolean('sin_paginar')) {
            $filas = $query->get();
            $this->adjuntarStockPorBodega($filas, $bodegas);

            return response()->json([
                'props' => [
                    'filas' => $filas,
                ],
            ]);
        }

        $busquedaRealizada = $request->boolean('buscado');
        $listas = null;

        if ($busquedaRealizada) {
            $listas = $query->paginate(25)->withQueryString();
            $this->adjuntarStockPorBodega(collect($listas->items()), $bodegas);
        }

        return Inertia::render('Inventario/ListasPrecio/Index', [
            'listas'     => $listas,
            'filters'    => $request->only(['search', 'marca_id', 'categoria_id']),
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'bodegas'    => $bodegas,
        ]);
    }

    /** Consulta base de la lista de precios (misma para la pantalla y el Excel). */
    private function consulta(Request $request, $empresaId)
    {
        return DB::table('productos as p')
            ->select([
                'p.id as producto_id',
                'p.codigo',
                'p.nombre',
                DB::raw("COALESCE(m.nombre, '—') as marca_nombre"),
                'p.pvp as pvp_base',
                'p.pvd as pvd_base',
                'p.porcentaje_iva',
                'lp.id as lista_pvp_id',
                'lp.precio as lista_pvp_precio',
                'lp.descuento_max as lista_pvp_descuento_max',
                'lp.descuento_max_promo as lista_pvp_descuento_max_promo',
                'ld.id as lista_pvd_id',
                'ld.precio as lista_pvd_precio',
                'ld.descuento_max as lista_pvd_descuento_max',
                DB::raw("COALESCE(lp.vigencia_desde, ld.vigencia_desde) as vigencia_desde"),
                DB::raw("COALESCE(lp.vigencia_hasta, ld.vigencia_hasta) as vigencia_hasta"),
            ])
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->leftJoin('listas_precio as lp', function ($j) use ($empresaId) {
                $j->on('lp.producto_id', '=', 'p.id')
                  ->where('lp.empresa_id', $empresaId)
                  ->where('lp.tipo', 'PVP');
            })
            ->leftJoin('listas_precio as ld', function ($j) use ($empresaId) {
                $j->on('ld.producto_id', '=', 'p.id')
                  ->where('ld.empresa_id', $empresaId)
                  ->where('ld.tipo', 'PVD');
            })
            ->where('p.estado', true)
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('p.codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('p.nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->marca_id, fn ($q) => $q->where('p.marca_id', $request->marca_id))
            ->when($request->categoria_id, fn ($q) => $q->where('p.categoria_id', $request->categoria_id))
            ->orderBy('p.nombre');
    }

    /** Bodegas de la empresa activa que se muestran como columnas de stock. */
    private function bodegasEmpresa($empresaId)
    {
        return Bodega::where('empresa_id', $empresaId)
            ->whereNotNull('centro_costo_id')
            ->activas()
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }

    /**
     * Muta cada fila agregando ->inventario = { bodega_id: stock_actual }.
     * El stock sigue siendo por empresa (vía bodega), aunque el catálogo de
     * productos ya no lo esté.
     */
    private function adjuntarStockPorBodega($filas, $bodegas): void
    {
        $productoIds = $filas->pluck('producto_id');

        $saldos = DB::table('inventario_saldos')
            ->whereIn('producto_id', $productoIds)
            ->whereIn('bodega_id', $bodegas->pluck('id'))
            ->get(['producto_id', 'bodega_id', 'stock_actual']);

        $saldosPorProducto = $saldos->groupBy('producto_id');

        foreach ($filas as $row) {
            $row->inventario = $bodegas->mapWithKeys(function ($bodega) use ($row, $saldosPorProducto) {
                $saldo = optional($saldosPorProducto->get($row->producto_id))
                    ->firstWhere('bodega_id', $bodega->id);
                return [$bodega->id => $saldo->stock_actual ?? 0];
            });
        }
    }

    /**
     * Excel de la lista de precios: mismas columnas y orden que la tabla en
     * pantalla, con encabezado de empresa/bodegas y bordes en todas las celdas.
     * Respeta los filtros activos (búsqueda, marca, categoría).
     */
    public function exportar(Request $request): StreamedResponse
    {
        $empresaId = session('empresa_activa_id');
        $empresa   = Empresa::findOrFail($empresaId);
        $bodegas   = $this->bodegasEmpresa($empresaId);

        $filas = $this->consulta($request, $empresaId)->get();
        $this->adjuntarStockPorBodega($filas, $bodegas);

        $nombreBodega = fn ($b) => trim(preg_replace('/^bodega\s+/i', '', $b->nombre));
        $fmtFecha     = fn ($v) => $v ? substr((string) $v, 0, 10) : '';
        $conIva       = fn ($base, $iva) => round((float) $base * (1 + ((float) $iva) / 100), 2);

        $columnas = ['#', 'Código', 'Nombre', 'Marca', 'PVP+IVA', 'Desc. PVP%', 'PVD+IVA', 'Desc. PVD%'];
        foreach ($bodegas as $b) {
            $columnas[] = 'Stock ' . $nombreBodega($b);
        }
        array_push($columnas, 'Promo %', 'Promo Desde', 'Promo Hasta');

        $totalCols = count($columnas);
        $ultima    = Coordinate::stringFromColumnIndex($totalCols);

        $hoja = ($libro = new Spreadsheet())->getActiveSheet();
        $hoja->setTitle('Lista de Precios');

        // ── Encabezado: empresa y bodegas ────────────────────────────────────
        $encabezado = [
            [$empresa->nombre_comercial ?: $empresa->razon_social, 16, true],
            ['LISTA DE PRECIOS', 13, true],
            ['Empresa: ' . $empresa->razon_social . ($empresa->ruc ? '  ·  RUC: ' . $empresa->ruc : ''), 10, false],
            ['Bodegas: ' . ($bodegas->isEmpty() ? '—' : $bodegas->map($nombreBodega)->implode(', ')), 10, false],
            ['Fecha: ' . now()->format('d/m/Y H:i') . '  ·  Precios con IVA incluido', 10, false],
        ];
        foreach ($encabezado as $i => [$texto, $tam, $negrita]) {
            $fila = $i + 1;
            $hoja->setCellValue("A{$fila}", $texto);
            $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
            $hoja->getStyle("A{$fila}")->getFont()->setBold($negrita)->setSize($tam);
            $hoja->getStyle("A{$fila}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        // ── Tabla ─────────────────────────────────────────────────────────────
        $filaCab = count($encabezado) + 2;
        foreach ($columnas as $i => $titulo) {
            $hoja->setCellValueByColumnAndRow($i + 1, $filaCab, $titulo);
        }

        $n = $filaCab;
        foreach ($filas as $idx => $r) {
            $n++;
            $valores = [
                $idx + 1,
                $r->codigo,
                $r->nombre,
                $r->marca_nombre,
                $conIva($r->lista_pvp_precio ?? $r->pvp_base, $r->porcentaje_iva),
                $r->lista_pvp_descuento_max !== null ? (float) $r->lista_pvp_descuento_max : 0,
                $conIva($r->lista_pvd_precio ?? $r->pvd_base, $r->porcentaje_iva),
                $r->lista_pvd_descuento_max !== null ? (float) $r->lista_pvd_descuento_max : 0,
            ];
            foreach ($bodegas as $b) {
                $valores[] = (float) ($r->inventario[$b->id] ?? 0);
            }
            $valores[] = $r->lista_pvp_descuento_max_promo !== null ? (float) $r->lista_pvp_descuento_max_promo : '';
            $valores[] = $fmtFecha($r->vigencia_desde);
            $valores[] = $fmtFecha($r->vigencia_hasta);

            foreach ($valores as $i => $v) {
                $hoja->setCellValueByColumnAndRow($i + 1, $n, $v);
            }
        }

        $rango = "A{$filaCab}:{$ultima}{$n}";
        $hoja->getStyle($rango)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $cab = $hoja->getStyle("A{$filaCab}:{$ultima}{$filaCab}");
        $cab->getFont()->setBold(true);
        $cab->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F59E0B');
        $cab->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
        $hoja->getRowDimension($filaCab)->setRowHeight(32);

        // Anchos y formatos
        $hoja->getColumnDimension('A')->setWidth(6);
        $hoja->getColumnDimension('B')->setWidth(14);
        $hoja->getColumnDimension('C')->setWidth(46);
        $hoja->getColumnDimension('D')->setWidth(18);
        foreach (['E', 'F', 'G', 'H'] as $c) {
            $hoja->getColumnDimension($c)->setWidth(12);
        }
        for ($c = 9; $c <= 8 + $bodegas->count(); $c++) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(12);
        }
        $ini = 8 + $bodegas->count() + 1;
        foreach ([$ini, $ini + 1, $ini + 2] as $c) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(13);
        }
        if ($n > $filaCab) {
            $hoja->getStyle("E" . ($filaCab + 1) . ":" . Coordinate::stringFromColumnIndex(8 + $bodegas->count()) . $n)
                ->getNumberFormat()->setFormatCode('0.00');
            $hoja->getStyle(Coordinate::stringFromColumnIndex($ini) . ($filaCab + 1) . ":" . Coordinate::stringFromColumnIndex($ini + 2) . $n)
                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }
        $hoja->freezePane('A' . ($filaCab + 1));

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, 'lista_de_precios.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function update(Request $request, $productoId)
    {
        $request->validate([
            'pvp'                  => 'required|numeric|min:0',
            'pvd'                  => 'required|numeric|min:0',
            'descuento_pvp'        => 'required|numeric|min:0|max:100',
            'descuento_pvd'        => 'required|numeric|min:0|max:100',
            'descuento_max_promo'  => 'nullable|numeric|min:0|max:100',
            'vigencia_desde'       => 'nullable|date|required_with:descuento_max_promo',
            'vigencia_hasta'       => 'nullable|date|required_with:descuento_max_promo|after_or_equal:vigencia_desde',
        ]);

        $empresaId = session('empresa_activa_id');

        $base = [
            'vigencia_desde' => $request->vigencia_desde ?: null,
            'vigencia_hasta' => $request->vigencia_hasta ?: null,
        ];

        ListaPrecio::updateOrCreate(
            ['empresa_id' => $empresaId, 'producto_id' => $productoId, 'tipo' => 'PVP'],
            array_merge($base, [
                'precio'              => $request->pvp,
                'descuento_max'       => $request->descuento_pvp,
                'descuento_max_promo' => $request->filled('descuento_max_promo') ? $request->descuento_max_promo : null,
            ])
        );

        ListaPrecio::updateOrCreate(
            ['empresa_id' => $empresaId, 'producto_id' => $productoId, 'tipo' => 'PVD'],
            array_merge($base, ['precio' => $request->pvd, 'descuento_max' => $request->descuento_pvd])
        );

        return back();
    }

    public function importar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls',
        ]);

        $empresaId = session('empresa_activa_id');
        $importados = 0;
        $errores = [];

        $rows = Excel::toArray(new class {}, $request->file('archivo'));

        if (empty($rows) || empty($rows[0])) {
            return back()->with(['importados' => 0, 'errores' => ['El archivo está vacío.']]);
        }

        $hoja = $rows[0];

        // El Excel exportado trae título/empresa arriba: la fila de encabezados es
        // la primera que contiene la columna "Código" (o "codigo").
        foreach ($hoja as $n => $filaHoja) {
            $celdas = array_map(fn ($c) => mb_strtolower(trim((string) $c)), $filaHoja);
            if (in_array('código', $celdas, true) || in_array('codigo', $celdas, true)) {
                $hoja = array_slice($hoja, $n);
                break;
            }
        }
        // El Excel exportado usa los mismos encabezados que la tabla en pantalla;
        // se traducen a las claves internas (los nombres antiguos siguen valiendo).
        $alias = [
            'código'      => 'codigo',
            'pvp+iva'     => 'pvp_iva',
            'desc. pvp%'  => 'descuento_pvp',
            'pvd+iva'     => 'pvd_iva',
            'desc. pvd%'  => 'descuento_pvd',
            'promo %'     => 'descuento_promo',
            'promo desde' => 'vigencia_desde',
            'promo hasta' => 'vigencia_hasta',
        ];
        $headers = array_map(
            fn ($h) => $alias[$h] ?? $h,
            array_map('mb_strtolower', array_map('trim', $hoja[0] ?? []))
        );

        foreach (array_slice($hoja, 1) as $i => $fila) {
            $linea = $i + 2;
            $row = array_combine($headers, array_pad($fila, count($headers), null));

            $codigo = trim((string) ($row['codigo'] ?? ''));
            if ($codigo === '') {
                $errores[] = "Fila {$linea}: código vacío.";
                continue;
            }

            $producto = Producto::where('codigo', $codigo)
                ->first();

            if (!$producto) {
                $errores[] = "Fila {$linea}: producto '{$codigo}' no encontrado.";
                continue;
            }

            $pvp           = is_numeric($row['pvp_lista'] ?? null) ? (float) $row['pvp_lista'] : null;
            $pvd           = is_numeric($row['pvd_lista'] ?? null) ? (float) $row['pvd_lista'] : null;

            // Columnas "PVP+IVA" / "PVD+IVA" del Excel exportado: llevan el IVA
            // incluido y se guardan sin IVA. Si el valor coincide (a 2 decimales)
            // con el precio actual + IVA, la fila no cambió y se conserva el
            // precio guardado, para que exportar e importar no altere decimales.
            $factorIva = 1 + ((float) $producto->porcentaje_iva) / 100;
            foreach (['pvp' => 'PVP', 'pvd' => 'PVD'] as $campo => $tipo) {
                $conIva = $row[$campo . '_iva'] ?? null;
                if (${$campo} !== null || !is_numeric($conIva)) {
                    continue;
                }
                $actual = ListaPrecio::where('empresa_id', $empresaId)
                    ->where('producto_id', $producto->id)
                    ->where('tipo', $tipo)
                    ->value('precio') ?? $producto->{$campo};
                ${$campo} = abs(round((float) $actual * $factorIva, 2) - (float) $conIva) < 0.005
                    ? (float) $actual
                    : round((float) $conIva / $factorIva, 4);
            }
            $descPvp       = is_numeric($row['descuento_pvp'] ?? null) ? (float) $row['descuento_pvp'] : 0;
            $descPvd       = is_numeric($row['descuento_pvd'] ?? null) ? (float) $row['descuento_pvd'] : 0;
            $vigDesde      = !empty($row['vigencia_desde']) ? $row['vigencia_desde'] : null;
            $vigHasta      = !empty($row['vigencia_hasta']) ? $row['vigencia_hasta'] : null;
            $descPromoRaw  = $row['descuento_promo'] ?? null;
            $descPromo     = is_numeric($descPromoRaw) ? (float) $descPromoRaw : null;

            if ($pvp === null && $pvd === null) {
                $errores[] = "Fila {$linea}: sin precios válidos para '{$codigo}'.";
                continue;
            }

            // Misma validación que ListaPrecioController::update(): la promo
            // exige las 2 fechas juntas, y si ambas vienen, hasta >= desde.
            // Una fila que no cumple falla sola, no aborta el archivo.
            if ($descPromo !== null && ($descPromo < 0 || $descPromo > 100)) {
                $errores[] = "Fila {$linea}: descuento_promo debe estar entre 0 y 100.";
                continue;
            }
            if ($descPromo !== null && ($vigDesde === null || $vigHasta === null)) {
                $errores[] = "Fila {$linea}: la promo requiere vigencia_desde y vigencia_hasta juntas.";
                continue;
            }
            if ($vigDesde !== null && $vigHasta !== null) {
                try {
                    $hastaValida = Carbon::parse($vigHasta)->gte(Carbon::parse($vigDesde));
                } catch (\Throwable) {
                    $errores[] = "Fila {$linea}: vigencia_desde/vigencia_hasta con fecha inválida.";
                    continue;
                }
                if (!$hastaValida) {
                    $errores[] = "Fila {$linea}: vigencia_hasta debe ser mayor o igual a vigencia_desde.";
                    continue;
                }
            }

            // $base es "sticky": solo se incluyen las claves con dato en el
            // Excel, para no borrar una vigencia/promo ya guardada cuando la
            // fila importada simplemente no trae esas columnas.
            $base = [];
            if ($vigDesde !== null) { $base['vigencia_desde'] = $vigDesde; }
            if ($vigHasta !== null) { $base['vigencia_hasta'] = $vigHasta; }

            if ($pvp !== null) {
                $datosPvp = array_merge($base, ['precio' => $pvp, 'descuento_max' => $descPvp]);
                if ($descPromo !== null) {
                    $datosPvp['descuento_max_promo'] = $descPromo;
                }
                ListaPrecio::updateOrCreate(
                    ['empresa_id' => $empresaId, 'producto_id' => $producto->id, 'tipo' => 'PVP'],
                    $datosPvp
                );
            }

            if ($pvd !== null) {
                ListaPrecio::updateOrCreate(
                    ['empresa_id' => $empresaId, 'producto_id' => $producto->id, 'tipo' => 'PVD'],
                    array_merge($base, ['precio' => $pvd, 'descuento_max' => $descPvd])
                );
            }

            $importados++;
        }

        return back()->with([
            'importados' => $importados,
            'errores'    => $errores,
        ]);
    }
}
