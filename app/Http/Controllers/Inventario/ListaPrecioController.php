<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Models\ListaPrecio;
use App\Models\Marca;
use App\Models\Producto;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;

class ListaPrecioController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $empresaId = session('empresa_activa_id');

        $query = DB::table('productos as p')
            ->select([
                'p.id as producto_id',
                'p.codigo',
                'p.nombre',
                DB::raw("COALESCE(m.nombre, '—') as marca_nombre"),
                'p.pvp as pvp_base',
                'p.pvd as pvd_base',
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
            ->where('p.empresa_id', $empresaId)
            ->where('p.estado', true)
            ->when($request->search, fn ($q) => $q->where(function ($q) use ($request) {
                $q->where('p.codigo', 'ilike', "%{$request->search}%")
                  ->orWhere('p.nombre', 'ilike', "%{$request->search}%");
            }))
            ->when($request->marca_id, fn ($q) => $q->where('p.marca_id', $request->marca_id))
            ->when($request->categoria_id, fn ($q) => $q->where('p.categoria_id', $request->categoria_id))
            ->orderBy('p.nombre');

        if ($request->boolean('sin_paginar')) {
            return response()->json([
                'props' => [
                    'filas' => $query->get(),
                ],
            ]);
        }

        return Inertia::render('Inventario/ListasPrecio/Index', [
            'listas'     => $query->paginate(25)->withQueryString(),
            'filters'    => $request->only(['search', 'marca_id', 'categoria_id']),
            'marcas'     => Marca::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaProducto::where('estado', true)->orderBy('nombre')->get(['id', 'nombre']),
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
        $headers = array_map('strtolower', array_map('trim', $hoja[0] ?? []));

        foreach (array_slice($hoja, 1) as $i => $fila) {
            $linea = $i + 2;
            $row = array_combine($headers, array_pad($fila, count($headers), null));

            $codigo = trim((string) ($row['codigo'] ?? ''));
            if ($codigo === '') {
                $errores[] = "Fila {$linea}: código vacío.";
                continue;
            }

            $producto = Producto::where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->first();

            if (!$producto) {
                $errores[] = "Fila {$linea}: producto '{$codigo}' no encontrado.";
                continue;
            }

            $pvp           = is_numeric($row['pvp_lista'] ?? null) ? (float) $row['pvp_lista'] : null;
            $pvd           = is_numeric($row['pvd_lista'] ?? null) ? (float) $row['pvd_lista'] : null;
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
