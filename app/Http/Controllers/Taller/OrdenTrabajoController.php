<?php

namespace App\Http\Controllers\Taller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Taller\Concerns\ResuelveBodegaTaller;
use App\Models\Producto;
use App\Models\TallerOrdenTrabajo;
use App\Models\Usuario;
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

    public function __construct(
        private AuditoriaService $auditoria,
    ) {}

    public function index(Request $request): Response
    {
        $empresaId = session('empresa_activa_id');

        $query = TallerOrdenTrabajo::with(['ingreso.cliente', 'ingreso.equipo', 'tecnico'])
            ->where('empresa_id', $empresaId)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $query->whereHas('ingreso.cliente', fn($q) => $q
                ->where('razon_social', 'ilike', "%{$request->search}%")
                ->orWhere('identificacion', 'ilike', "%{$request->search}%"));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('tecnico_id')) {
            $query->where('tecnico_id', $request->tecnico_id);
        }

        return Inertia::render('Taller/OrdenesTrabajo/Index', [
            'ordenes'  => $query->paginate(15)->withQueryString(),
            'filtros'  => $request->only(['search', 'estado', 'tecnico_id']),
            'tecnicos' => $this->listaTecnicos(),
        ]);
    }

    public function show(TallerOrdenTrabajo $orden): Response
    {
        abort_if((int) $orden->empresa_id !== (int) session('empresa_activa_id'), 403);

        $orden->load(['ingreso.cliente', 'ingreso.equipo.tipo', 'tecnico', 'diagnosticos.tecnico', 'repuestos.producto']);

        return Inertia::render('Taller/OrdenesTrabajo/Show', [
            'orden'     => $orden,
            'tecnicos'  => Usuario::select('id', 'nombre')->orderBy('nombre')->get(),
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
        $query = Producto::where('empresa_id', session('empresa_activa_id'))
            ->where('estado', true)
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
            'estado'     => 'required|string|in:pendiente,en_proceso,listo,entregado,facturado,garantia',
            'tecnico_id' => 'nullable|integer|exists:usuarios,id',
        ]);

        $estadoIngreso = match ($data['estado']) {
            'pendiente' => 0,
            'en_proceso', 'garantia' => 2,
            'listo' => 3,
            'entregado', 'facturado' => 4,
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

    private function listaTecnicos(): Collection
    {
        $tecnicos = Usuario::whereHas('perfil', fn($q) => $q->where('nombre', 'tecnico'))
            ->select('id', 'nombre')
            ->get();

        return $tecnicos->isNotEmpty()
            ? $tecnicos
            : Usuario::select('id', 'nombre')->orderBy('nombre')->get();
    }
}
