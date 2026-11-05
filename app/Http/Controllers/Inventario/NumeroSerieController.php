<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Bodega;
use App\Models\Producto;
use App\Models\ProductoSerie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Números de serie (02-02 §2.4): DJ / controladores, computadoras, pantallas LED y TV exigen un SN por unidad.
 * Esta pantalla registra el SN de cada unidad que entra y sirve de inspección de bodega: cuáles unidades con SN
 * siguen en bodega y cuáles se vendieron. El enlace con la factura de venta (elegir la unidad al vender) es de Ventas.
 */
class NumeroSerieController extends Controller
{
    public function index(Request $request): Response
    {
        $empresaId = (int) session('empresa_activa_id');

        $bodegas = Bodega::where('empresa_id', $empresaId)->where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);
        $bodegaIds = $bodegas->pluck('id');

        $series = null;
        if ($request->boolean('buscado')) {
            $series = ProductoSerie::with(['producto:id,codigo,nombre', 'bodega:id,nombre'])
                ->whereIn('bodega_id', $bodegaIds)
                ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->estado))
                ->when($request->filled('bodega_id'), fn ($q) => $q->where('bodega_id', $request->bodega_id))
                ->when($request->filled('buscar'), fn ($q) => $q->where(function ($w) use ($request) {
                    $w->where('numero_serie', 'ilike', "%{$request->buscar}%")
                      ->orWhereHas('producto', fn ($p) => $p->where('codigo', 'ilike', "%{$request->buscar}%")->orWhere('nombre', 'ilike', "%{$request->buscar}%"));
                }))
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString()
                ->through(fn (ProductoSerie $s) => [
                    'id' => $s->id, 'numero_serie' => $s->numero_serie, 'estado' => $s->estado,
                    'producto' => $s->producto ? "{$s->producto->codigo} — {$s->producto->nombre}" : null,
                    'bodega' => $s->bodega?->nombre, 'factura_venta_id' => $s->factura_venta_id,
                    'fecha' => $s->created_at ? \Carbon\Carbon::parse($s->created_at)->format('d/m/Y') : null,
                ]);
        }

        // Resumen por producto que exige serie: unidades en stock frente a SN registrados en bodega (lo que falta registrar)
        $resumen = DB::table('productos as p')
            ->leftJoin('inventario_saldos as s', fn ($j) => $j->on('s.producto_id', '=', 'p.id')->whereIn('s.bodega_id', $bodegaIds))
            ->where('p.requiere_serie', true)->where('p.estado', true)
            ->groupBy('p.id', 'p.codigo', 'p.nombre')
            ->havingRaw('COALESCE(SUM(s.stock_actual), 0) > 0')
            ->orderBy('p.nombre')->limit(100)
            ->get(['p.id', 'p.codigo', 'p.nombre', DB::raw('COALESCE(SUM(s.stock_actual), 0) as stock')])
            ->map(function ($r) use ($bodegaIds) {
                $reg = ProductoSerie::where('producto_id', $r->id)->whereIn('bodega_id', $bodegaIds)->where('estado', '!=', 'vendido')->count();
                return ['id' => $r->id, 'producto' => "{$r->codigo} — {$r->nombre}", 'stock' => (float) $r->stock,
                        'registradas' => $reg, 'faltan' => max(0, (int) round((float) $r->stock) - $reg)];
            });

        return Inertia::render('Inventario/NumerosSerie/Index', [
            'series'   => $series,
            'resumen'  => $resumen,
            'bodegas'  => $bodegas,
            'productos' => Producto::where('requiere_serie', true)->where('estado', true)->orderBy('nombre')->limit(500)->get(['id', 'codigo', 'nombre']),
            'estados'  => ProductoSerie::ESTADOS,
            'filtros'  => $request->only(['estado', 'bodega_id', 'buscar']),
        ]);
    }

    /** Registra, una por línea, los números de serie de las unidades de un producto en una bodega. */
    public function store(Request $request): RedirectResponse
    {
        $empresaId = (int) session('empresa_activa_id');
        $request->validate([
            'producto_id' => ['required', 'integer', 'exists:productos,id'],
            'bodega_id'   => ['required', 'integer', \Illuminate\Validation\Rule::exists('bodegas', 'id')->where('empresa_id', $empresaId)],
            'series'      => ['required', 'string'],
        ], [
            'series.required' => 'Escriba los números de serie, uno por línea.',
        ]);

        $producto = Producto::findOrFail($request->producto_id);
        if (!$producto->requiere_serie) {
            return back()->with('error', "El producto {$producto->codigo} no exige número de serie.");
        }

        $lista = collect(preg_split('/\R/', (string) $request->series))->map(fn ($s) => trim($s))->filter()->values();
        if ($lista->isEmpty()) {
            return back()->with('error', 'Escriba al menos un número de serie.');
        }
        if ($lista->count() !== $lista->unique()->count()) {
            return back()->with('error', 'Hay números de serie repetidos en la lista: ' . $lista->duplicates()->unique()->implode(', ') . '.')->withInput();
        }

        // Un SN es único por producto (una unidad física no puede estar dos veces)
        $existentes = ProductoSerie::where('producto_id', $producto->id)->whereIn('numero_serie', $lista)->pluck('numero_serie');
        if ($existentes->isNotEmpty()) {
            return back()->with('error', 'Ya están registrados: ' . $existentes->implode(', ') . '.')->withInput();
        }

        DB::transaction(function () use ($lista, $producto, $request) {
            foreach ($lista as $sn) {
                ProductoSerie::create([
                    'producto_id' => $producto->id, 'bodega_id' => $request->bodega_id,
                    'numero_serie' => $sn, 'estado' => 'disponible',
                ]);
            }
        });

        return back()->with('success', $lista->count() . ' número(s) de serie registrado(s) para ' . $producto->codigo . '.');
    }

    /** Corrige el estado de una unidad (p. ej. devuelta o en garantía). La venta se enlaza desde Facturación. */
    public function estado(Request $request, ProductoSerie $serie): RedirectResponse
    {
        $request->validate(['estado' => ['required', 'in:' . implode(',', ProductoSerie::ESTADOS)]]);
        abort_unless(Bodega::where('empresa_id', session('empresa_activa_id'))->whereKey($serie->bodega_id)->exists(), 404);

        $serie->update(['estado' => $request->estado, 'factura_venta_id' => $request->estado === 'vendido' ? $serie->factura_venta_id : null]);

        return back()->with('success', "Serie {$serie->numero_serie} marcada como {$request->estado}.");
    }
}
