<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Inventario consolidado: todos los productos del catálogo con el stock de
 * cada bodega de ambas empresas, en horizontal. Solo se ofrece con Altamira
 * Matriz activa y a los perfiles super_admin / admin (middleware en la ruta).
 */
class ConsolidadoController extends Controller
{
    private const RUC_MATRIZ = '1711293454001';

    /** Bodegas activas de todas las empresas, ordenadas por empresa. */
    private function bodegas()
    {
        return DB::table('bodegas as b')
            ->join('empresas as e', 'e.id', '=', 'b.empresa_id')
            ->where('b.estado', true)
            ->orderBy('b.empresa_id')->orderBy('b.id')
            ->get(['b.id', 'b.empresa_id', 'b.nombre', 'e.nombre_comercial as empresa_nombre', 'e.razon_social']);
    }

    private function autorizarMatriz(): void
    {
        $ruc = Empresa::find(session('empresa_activa_id'))?->ruc;
        abort_unless($ruc === self::RUC_MATRIZ, 403, 'El inventario consolidado solo está disponible en Altamira Matriz.');
    }

    private function consulta(Request $request)
    {
        return DB::table('productos as p')
            ->leftJoin('marcas as m', 'm.id', '=', 'p.marca_id')
            ->select(['p.id', 'p.codigo', 'p.nombre', 'p.descripcion', 'p.ref_importacion', 'p.unidad',
                DB::raw("COALESCE(m.nombre, '') as marca_nombre")])
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('p.codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('p.nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->marca_id, fn ($q) => $q->where('p.marca_id', $request->marca_id))
            ->orderBy('p.nombre');
    }

    /** Agrega ->stock {bodega_id => cantidad}, ->total_empresa {empresa_id => suma} y ->total. */
    private function adjuntarStock($filas, $bodegas): void
    {
        $saldos = DB::table('inventario_saldos')
            ->whereIn('producto_id', $filas->pluck('id'))
            ->whereIn('bodega_id', $bodegas->pluck('id'))
            ->get(['producto_id', 'bodega_id', 'stock_actual'])
            ->groupBy('producto_id');

        foreach ($filas as $f) {
            $porBodega = $saldos->get($f->id, collect())->pluck('stock_actual', 'bodega_id');
            $f->stock = [];
            $f->total_empresa = [];
            $f->total = 0.0;
            foreach ($bodegas as $b) {
                $v = (float) ($porBodega[$b->id] ?? 0);
                $f->stock[$b->id] = $v;
                $f->total_empresa[$b->empresa_id] = ($f->total_empresa[$b->empresa_id] ?? 0) + $v;
                $f->total += $v;
            }
        }
    }

    public function index(Request $request): Response
    {
        $this->autorizarMatriz();
        $bodegas = $this->bodegas();

        $productos = $request->boolean('buscado')
            ? $this->consulta($request)->paginate(50)->withQueryString()
            : null;
        if ($productos) {
            $this->adjuntarStock(collect($productos->items()), $bodegas);
        }

        return Inertia::render('Inventario/Consolidado/Index', [
            'productos' => $productos,
            'bodegas'   => $bodegas,
            'empresas'  => $bodegas->groupBy('empresa_id')->map(fn ($g, $id) => [
                'id' => (int) $id, 'nombre' => $g->first()->empresa_nombre ?: $g->first()->razon_social,
            ])->values(),
            'marcas'    => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filters'   => $request->only(['search', 'marca_id']),
        ]);
    }

    public function excel(Request $request): StreamedResponse
    {
        $this->autorizarMatriz();
        ini_set('memory_limit', '512M');

        $bodegas = $this->bodegas();
        $filas   = $this->consulta($request)->get();
        $this->adjuntarStock($filas, $bodegas);
        $porEmpr = $bodegas->groupBy('empresa_id');

        // Columnas fijas + (bodegas + total) por empresa + total general.
        $fijas = ['N°', 'Código', 'Producto', 'Marca', 'Descripción', 'Ref. Importación', 'Unidad'];
        $hoja  = ($libro = new Spreadsheet())->getActiveSheet();
        $hoja->setTitle('Inventario Consolidado');

        $filaGrupo = 3;
        $filaCab   = 4;
        foreach ($fijas as $i => $t) {
            $hoja->setCellValue([$i + 1, $filaCab], $t);
        }
        $col     = count($fijas) + 1;
        $colores = ['FDE68A', 'BFDBFE', 'BBF7D0'];
        $k       = 0;
        foreach ($porEmpr as $grupo) {
            $ini = $col;
            foreach ($grupo as $b) {
                $hoja->setCellValue([$col++, $filaCab], $b->nombre);
            }
            $hoja->setCellValue([$col++, $filaCab], 'Total');
            $fin = $col - 1;
            $a = Coordinate::stringFromColumnIndex($ini);
            $z = Coordinate::stringFromColumnIndex($fin);
            $hoja->setCellValue([$ini, $filaGrupo], $grupo->first()->empresa_nombre ?: $grupo->first()->razon_social);
            $hoja->mergeCells("{$a}{$filaGrupo}:{$z}{$filaGrupo}");
            $hoja->getStyle("{$a}{$filaGrupo}:{$z}{$filaCab}")
                ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($colores[$k++ % 3]);
        }
        $hoja->setCellValue([$col, $filaCab], 'TOTAL GENERAL');
        $ultima = Coordinate::stringFromColumnIndex($col);

        $hoja->setCellValue('A1', 'INVENTARIO CONSOLIDADO — ' . now()->format('d/m/Y H:i'));
        $hoja->mergeCells("A1:{$ultima}1");
        $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $hoja->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $n = $filaCab;
        foreach ($filas as $i => $f) {
            $n++;
            $v = [$i + 1, $f->codigo, $f->nombre, $f->marca_nombre, $f->descripcion, $f->ref_importacion, $f->unidad];
            foreach ($porEmpr as $empId => $grupo) {
                foreach ($grupo as $b) {
                    $v[] = $f->stock[$b->id];
                }
                $v[] = $f->total_empresa[$empId] ?? 0;
            }
            $v[] = $f->total;
            $hoja->fromArray($v, null, "A{$n}");
        }

        $cab = $hoja->getStyle("A{$filaGrupo}:{$ultima}{$filaCab}");
        $cab->getFont()->setBold(true);
        $cab->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $hoja->getStyle("A{$filaGrupo}:{$ultima}{$n}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        foreach ([1 => 6, 2 => 14, 3 => 44, 4 => 16, 5 => 36, 6 => 18, 7 => 10] as $c => $w) {
            $hoja->getColumnDimensionByColumn($c)->setWidth($w);
        }
        for ($c = 8; $c <= $col; $c++) {
            $hoja->getColumnDimensionByColumn($c)->setWidth(12);
        }
        $hoja->freezePane('D' . ($filaCab + 1));

        return response()->streamDownload(function () use ($libro) {
            (new Xlsx($libro))->save('php://output');
        }, 'inventario_consolidado.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
