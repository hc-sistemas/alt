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
        $ordenes->through(function (TallerOrdenTrabajo $o) use ($turnos) {
            $o->setAttribute('turno', $turnos->get($o->id));
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

    public function show(TallerOrdenTrabajo $orden): Response
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        // 'ingreso.componentes' se agregó al fusionar Ingresos+Órdenes en una
        // sola pantalla (D1, CHECKLIST_ERRORES_COMPLICACIONES.md).
        $orden->load(['ingreso.cliente', 'ingreso.equipo.tipo', 'ingreso.componentes', 'tecnico', 'diagnosticos.tecnico', 'repuestos.producto']);

        return Inertia::render('Taller/OrdenesTrabajo/Show', [
            'orden'     => $orden,
            'tecnicos'  => $this->listaTecnicos(),
            'productos' => $this->productosConStockTaller(),
        ]);
    }

    /**
     * Solo productos con stock físico (stock_actual > 0) en Bodega Taller —
     * el buscador de repuestos no debe ofrecer productos sin existencias ahí.
     * Si la bodega no está configurada, no se rompe la vista de la orden:
     * el buscador simplemente queda sin opciones.
     */
    private function productosConStockTaller(): Collection
    {
        $query = Producto::where('estado', true)
            ->select('id', 'codigo', 'nombre', 'costo', 'pvp')
            ->orderBy('nombre');

        try {
            $bodegaTallerId = $this->bodegaTallerId();
        } catch (\RuntimeException) {
            return new Collection();
        }

        $query->whereExists(function ($sub) use ($bodegaTallerId) {
            $sub->select(DB::raw(1))
                ->from('inventario_saldos')
                ->whereColumn('inventario_saldos.producto_id', 'productos.id')
                ->where('inventario_saldos.bodega_id', $bodegaTallerId)
                ->where('inventario_saldos.stock_actual', '>', 0);
        });

        return $query->get();
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
