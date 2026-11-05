<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Taller\Concerns\ListaTecnicosTaller;
use App\Http\Controllers\Taller\Concerns\ResuelveBodegaTaller;
use App\Models\Producto;
use App\Models\TallerOrdenTrabajo;
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OrdenTrabajoController extends Controller
{
    use ResuelveBodegaTaller;
    use ListaTecnicosTaller;

    /** Estados que forman parte de la cola de trabajo del taller. */
    private const ESTADOS_COLA = ['pendiente', 'en_proceso', 'esperando_repuesto'];

    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = TallerOrdenTrabajo::with(['ingreso.cliente', 'ingreso.equipo', 'tecnico'])
            ->where('empresa_id', $empresaId);

        // Cola FIFO común: las órdenes abiertas (pendiente / en proceso / esperando
        // repuesto) van primero en estricto orden de llegada (id ascendente); el
        // resto del historial queda debajo, de la más reciente a la más antigua.
        $abiertas = "'" . implode("','", self::ESTADOS_COLA) . "'";
        $query->orderByRaw("CASE WHEN estado IN ({$abiertas}) THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN estado IN ({$abiertas}) THEN id END ASC")
            ->orderByRaw("CASE WHEN estado NOT IN ({$abiertas}) THEN id END DESC");

        if ($request->filled('search')) {
            $term = "%{$request->search}%";
            $query->where(function ($w) use ($term) {
                $w->whereHas('ingreso.cliente', fn($q) => $q
                        ->where('razon_social', 'ilike', $term)
                        ->orWhere('identificacion', 'ilike', $term))
                    ->orWhereHas('ingreso.equipo', fn($q) => $q
                        ->where('marca', 'ilike', $term)
                        ->orWhere('modelo', 'ilike', $term)
                        ->orWhere('numero_serie', 'ilike', $term))
                    ->orWhere('numero', 'ilike', $term);
            });
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tecnico_id')) {
            $query->where('tecnico_id', $request->tecnico_id);
        }
        if ($request->filled('desde')) {
            $query->whereDate('fecha_inicio', '>=', $request->desde);
        }
        if ($request->filled('hasta')) {
            $query->whereDate('fecha_inicio', '<=', $request->hasta);
        }

        // Turno en la cola: solo las órdenes "pendiente" esperan turno. Una orden
        // en "esperando_repuesto" no ocupa lugar, así que el técnico sigue con la
        // siguiente en la fila.
        $colaIds = TallerOrdenTrabajo::where('empresa_id', $empresaId)
            ->where('estado', 'pendiente')
            ->orderBy('id')
            ->pluck('id')
            ->values();
        $turnos = $colaIds->flip()->map(fn ($i) => $i + 1);

        $ordenes = $query->paginate(15)->withQueryString();
        // 'turno' es el número asignado por orden de llegada (queda en la orden); 'posicion_cola' es el lugar
        // actual en la fila de las que siguen pendientes.
        $ordenes->through(function (TallerOrdenTrabajo $o) use ($turnos) {
            $o->setAttribute('posicion_cola', $turnos->get($o->id));
            return $o;
        });

        $conteos = TallerOrdenTrabajo::where('empresa_id', $empresaId)
            ->whereIn('estado', self::ESTADOS_COLA)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return Inertia::render('Taller/OrdenesTrabajo/Index', [
            'ordenes'  => $ordenes,
            'cola'     => [
                'pendientes'         => (int) ($conteos['pendiente'] ?? 0),
                'en_proceso'         => (int) ($conteos['en_proceso'] ?? 0),
                'esperando_repuesto' => (int) ($conteos['esperando_repuesto'] ?? 0),
                'siguiente_id'       => $colaIds->first(),
            ],
            'filtros'  => $request->only(['search', 'estado', 'tecnico_id', 'desde', 'hasta']),
            'tecnicos' => $this->listaTecnicos(),
        ]);
    }

    public function show(Request $request, TallerOrdenTrabajo $orden): Response
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        // 'ingreso.componentes' se agregó al fusionar Ingresos+Órdenes en una
        // sola pantalla (D1, CHECKLIST_ERRORES_COMPLICACIONES.md).
        $orden->load(['ingreso.cliente', 'ingreso.equipo.tipo', 'ingreso.componentes', 'tecnico', 'diagnosticos.tecnico', 'repuestos.producto']);

        return Inertia::render('Taller/OrdenesTrabajo/Show', [
            'orden'     => $orden,
            'tecnicos'  => $this->listaTecnicos(),
            'productos' => $this->productosConStockTaller(),
            // Tras guardar un ingreso se abre el documento para imprimirlo (02-10 §2.3)
            'imprimir'  => $request->boolean('imprimir'),
        ]);
    }

    /** Nombre de la categoría de la que salen los repuestos (PC-CAT-16). */
    public const CATEGORIA_REPUESTOS = 'Repuestos e Insumos de Taller';

    /** ¿El producto pertenece a la categoría de repuestos de taller (o a una de sus subcategorías)? */
    public static function esRepuestoDeTaller(Producto $producto): bool
    {
        $raiz = DB::table('categorias_producto')->where('nombre', self::CATEGORIA_REPUESTOS)->value('id');
        if (!$raiz || !$producto->categoria_id) {
            return false;
        }
        $ids = [(int) $raiz];
        $hijos = DB::table('categorias_producto')->where('categoria_padre_id', $raiz)->pluck('id')->all();

        return in_array((int) $producto->categoria_id, array_merge($ids, array_map('intval', $hijos)), true);
    }

    /**
     * Buscador de repuestos (02-10 §2.6): los productos de la categoría «Repuestos e Insumos de Taller» con
     * su stock en la bodega de Taller. Se listan también los que están en cero (se ve el stock) y el
     * formulario bloquea cuando la cantidad pedida supera lo disponible.
     * Si la bodega no está configurada, no se rompe la vista de la orden: el buscador queda sin opciones.
     */
    private function productosConStockTaller(): Collection
    {
        try {
            $bodegaTallerId = $this->bodegaTallerId();
        } catch (\RuntimeException) {
            return new Collection();
        }

        $raiz = DB::table('categorias_producto')->where('nombre', self::CATEGORIA_REPUESTOS)->value('id');
        if (!$raiz) {
            return new Collection();
        }
        $categorias = array_merge([(int) $raiz], DB::table('categorias_producto')->where('categoria_padre_id', $raiz)->pluck('id')->map(fn ($i) => (int) $i)->all());

        return Producto::where('productos.estado', true)
            ->whereIn('productos.categoria_id', $categorias)
            ->leftJoin('inventario_saldos as s', function ($j) use ($bodegaTallerId) {
                $j->on('s.producto_id', '=', 'productos.id')->where('s.bodega_id', '=', $bodegaTallerId);
            })
            ->select('productos.id', 'productos.codigo', 'productos.nombre', 'productos.costo', 'productos.pvp',
                DB::raw('COALESCE(s.stock_actual, 0) - COALESCE(s.cantidad_reservada, 0) as stock'))
            ->orderBy('productos.nombre')
            ->get();
    }

    /** Costo de garantías por producto (02-10 §2.8): repuestos usados en órdenes cerradas como garantía. */
    public function garantias(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $filas = DB::table('taller_ot_repuestos as r')
            ->join('taller_ordenes_trabajo as o', 'o.id', '=', 'r.orden_id')
            ->join('productos as p', 'p.id', '=', 'r.producto_id')
            // Costo real de la salida (kárdex, costo promedio al momento de la salida)
            ->leftJoin('inventario_movimientos as m', function ($j) {
                $j->on('m.doc_id', '=', 'r.id')->where('m.doc_tipo', '=', 'TALLER_OT')->where('m.tipo', '=', 'salida');
            })
            ->where('o.empresa_id', $empresaId)
            ->where('o.es_garantia', true)
            ->whereNotNull('o.garantia_cerrada_at')
            ->where('r.estado', 'usado')
            ->when($request->filled('desde'), fn ($q) => $q->whereDate('o.fecha_fin_real', '>=', $request->desde))
            ->when($request->filled('hasta'), fn ($q) => $q->whereDate('o.fecha_fin_real', '<=', $request->hasta))
            ->groupBy('p.id', 'p.codigo', 'p.nombre')
            ->selectRaw('p.id, p.codigo, p.nombre, SUM(r.cantidad) as unidades, COUNT(DISTINCT o.id) as ordenes, COALESCE(SUM(m.costo_total), SUM(r.cantidad * r.costo_unitario)) as costo')
            ->orderByDesc('costo')
            ->get();

        return Inertia::render('Taller/Garantias/Index', [
            'filas'   => $filas,
            'total'   => round((float) $filas->sum('costo'), 2),
            'filtros' => $request->only(['desde', 'hasta']),
        ]);
    }

    public function cambiarEstado(Request $request, TallerOrdenTrabajo $orden): RedirectResponse
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $data = $request->validate([
            // "facturado" solo lo fija la liquidación (genera la factura y descuenta el stock).
            'estado'     => 'required|string|in:pendiente,en_proceso,esperando_repuesto,listo,entregado,garantia',
            'tecnico_id' => 'nullable|integer|exists:usuarios,id',
        ]);

        if ($orden->estado === 'facturado') {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'La orden ya fue facturada; su estado no se puede cambiar.']);
        }
        if ($data['estado'] === 'entregado' && !in_array($orden->estado, ['listo', 'entregado'], true)) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'Solo una orden en estado "Listo" puede marcarse como entregada.']);
        }
        if (!empty($data['tecnico_id']) && !$this->listaTecnicos()->contains('id', (int) $data['tecnico_id'])) {
            return back()->with('flash', ['tipo' => 'error', 'mensaje' => 'El técnico seleccionado no pertenece a esta empresa.']);
        }

        $estadoIngreso = match ($data['estado']) {
            'pendiente' => 0,
            'en_proceso', 'esperando_repuesto', 'garantia' => 2,
            'listo' => 3,
            'entregado' => 4,
        };

        DB::transaction(function () use ($orden, $data, $estadoIngreso) {
            $orden->estado = $data['estado'];
            if (array_key_exists('tecnico_id', $data)) {
                $orden->tecnico_id = $data['tecnico_id'];
            }
            if ($data['estado'] === 'listo') {
                $orden->fecha_fin_real = now()->toDateString();
            }
            $orden->save();

            $orden->ingreso()->update(['estado' => $estadoIngreso]);
        });

        $this->auditoria->documento('editar', 'taller', 'ordenes_trabajo', $orden->id,
            "Orden de trabajo {$orden->numero} cambiada a estado {$data['estado']}");

        return back()->with('flash', ['tipo' => 'exito', 'mensaje' => 'Estado de la orden actualizado correctamente.']);
    }
}
